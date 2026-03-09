<?php

// app/Models/PipelineConfig.php
// Pipeline configuration templates — controls which agents run, conditional rules,
// approval gates, environment thresholds, and retry settings.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PipelineConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'description',
        'is_default',
        'agent_archaeologist',
        'agent_historian',
        'agent_negotiator',
        'agent_chronicler',
        'require_approval_after_scoring',
        'auto_approve_below_score',
        'auto_block_above_score',
        'conditional_rules',
        'environment_thresholds',
        'max_retries_per_agent',
        'retry_on_timeout',
        'high_traffic_windows',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'agent_archaeologist' => 'boolean',
            'agent_historian' => 'boolean',
            'agent_negotiator' => 'boolean',
            'agent_chronicler' => 'boolean',
            'require_approval_after_scoring' => 'boolean',
            'auto_approve_below_score' => 'integer',
            'auto_block_above_score' => 'integer',
            'conditional_rules' => 'array',
            'environment_thresholds' => 'array',
            'max_retries_per_agent' => 'integer',
            'retry_on_timeout' => 'boolean',
            'high_traffic_windows' => 'array',
        ];
    }

    /**
     * Get the default pipeline config, or create built-in defaults if none exist.
     */
    public static function getDefault(): self
    {
        $default = static::where('is_default', true)->first();

        if ($default) {
            return $default;
        }

        // Seed built-in templates if no configs exist
        if (static::count() === 0) {
            static::seedBuiltInTemplates();

            return static::where('is_default', true)->first();
        }

        return static::first();
    }

    /**
     * Seed the 3 built-in pipeline templates.
     */
    public static function seedBuiltInTemplates(): void
    {
        static::create([
            'name' => 'full',
            'label' => 'Full Analysis',
            'description' => 'All 4 agents run sequentially. Best for production deployments.',
            'is_default' => true,
            'agent_archaeologist' => true,
            'agent_historian' => true,
            'agent_negotiator' => true,
            'agent_chronicler' => true,
            'require_approval_after_scoring' => false,
            'auto_approve_below_score' => 50,
            'auto_block_above_score' => 85,
            'conditional_rules' => [],
            'environment_thresholds' => [
                'production' => ['risk_threshold' => 60, 'require_approval' => true],
                'staging' => ['risk_threshold' => 70, 'require_approval' => false],
                'development' => ['risk_threshold' => 100, 'require_approval' => false],
            ],
            'max_retries_per_agent' => 1,
            'retry_on_timeout' => true,
            'high_traffic_windows' => [
                ['day' => 'monday', 'start_hour' => 9, 'end_hour' => 11, 'label' => 'Morning Peak'],
                ['day' => 'friday', 'start_hour' => 14, 'end_hour' => 17, 'label' => 'Pre-Weekend'],
            ],
        ]);

        static::create([
            'name' => 'quick',
            'label' => 'Quick Scan',
            'description' => 'Archaeologist only — fast blast radius check without historical analysis.',
            'is_default' => false,
            'agent_archaeologist' => true,
            'agent_historian' => false,
            'agent_negotiator' => false,
            'agent_chronicler' => false,
            'require_approval_after_scoring' => false,
            'auto_approve_below_score' => 30,
            'auto_block_above_score' => 90,
            'conditional_rules' => [
                ['type' => 'path_match', 'pattern' => 'docs/**', 'action' => 'skip_all', 'label' => 'Skip analysis for docs-only changes'],
                ['type' => 'path_match', 'pattern' => '*.md', 'action' => 'skip_all', 'label' => 'Skip analysis for markdown-only changes'],
            ],
            'environment_thresholds' => [
                'production' => ['risk_threshold' => 50, 'require_approval' => false],
                'staging' => ['risk_threshold' => 80, 'require_approval' => false],
            ],
            'max_retries_per_agent' => 0,
            'retry_on_timeout' => false,
        ]);

        static::create([
            'name' => 'gated',
            'label' => 'Gated Deployment',
            'description' => 'Full analysis with mandatory human approval after risk scoring.',
            'is_default' => false,
            'agent_archaeologist' => true,
            'agent_historian' => true,
            'agent_negotiator' => true,
            'agent_chronicler' => true,
            'require_approval_after_scoring' => true,
            'auto_approve_below_score' => 40,
            'auto_block_above_score' => 90,
            'conditional_rules' => [
                ['type' => 'path_match', 'pattern' => 'migrations/**', 'action' => 'force_gate', 'label' => 'Always require approval for migrations'],
                ['type' => 'path_match', 'pattern' => '**/config/**', 'action' => 'force_gate', 'label' => 'Always require approval for config changes'],
                ['type' => 'file_count_above', 'threshold' => 20, 'action' => 'force_gate', 'label' => 'Gate PRs touching 20+ files'],
            ],
            'environment_thresholds' => [
                'production' => ['risk_threshold' => 50, 'require_approval' => true],
                'staging' => ['risk_threshold' => 70, 'require_approval' => true],
                'development' => ['risk_threshold' => 80, 'require_approval' => false],
            ],
            'max_retries_per_agent' => 2,
            'retry_on_timeout' => true,
        ]);
    }

    /**
     * Check if a given set of files matches any conditional rules.
     *
     * @param  array<string>  $files
     * @return array{should_skip: bool, force_gate: bool, matched_rules: array}
     */
    public function evaluateConditionalRules(array $files, int $fileCount = 0): array
    {
        $rules = $this->conditional_rules ?? [];
        $shouldSkip = false;
        $forceGate = false;
        $matchedRules = [];

        foreach ($rules as $rule) {
            $matched = false;

            if (($rule['type'] ?? '') === 'path_match') {
                $pattern = $rule['pattern'] ?? '';
                foreach ($files as $file) {
                    if (fnmatch($pattern, $file) || fnmatch($pattern, basename($file))) {
                        $matched = true;
                        break;
                    }
                }
                // For skip_all: ALL files must match the pattern
                if (($rule['action'] ?? '') === 'skip_all') {
                    $allMatch = true;
                    foreach ($files as $file) {
                        if (! fnmatch($pattern, $file) && ! fnmatch($pattern, basename($file))) {
                            $allMatch = false;
                            break;
                        }
                    }
                    $matched = $allMatch;
                }
            } elseif (($rule['type'] ?? '') === 'file_count_above') {
                $matched = $fileCount > ($rule['threshold'] ?? PHP_INT_MAX);
            }

            if ($matched) {
                $matchedRules[] = $rule;
                $action = $rule['action'] ?? '';
                if ($action === 'skip_all') {
                    $shouldSkip = true;
                }
                if ($action === 'force_gate') {
                    $forceGate = true;
                }
            }
        }

        return [
            'should_skip' => $shouldSkip,
            'force_gate' => $forceGate,
            'matched_rules' => $matchedRules,
        ];
    }

    /**
     * Get the risk threshold for a given environment.
     */
    public function getThresholdForEnvironment(string $environment): int
    {
        $thresholds = $this->environment_thresholds ?? [];

        return $thresholds[$environment]['risk_threshold'] ?? 50;
    }

    /**
     * Check if current time falls within a high-traffic window.
     */
    public function isHighTrafficWindow(): bool
    {
        $windows = $this->high_traffic_windows ?? [];
        $now = now();
        $dayName = strtolower($now->format('l'));
        $currentHour = (int) $now->format('G');

        foreach ($windows as $window) {
            if (strtolower($window['day'] ?? '') === $dayName) {
                $start = $window['start_hour'] ?? 0;
                $end = $window['end_hour'] ?? 24;
                if ($currentHour >= $start && $currentHour < $end) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the active agents for this config as an array of names.
     *
     * @return array<string>
     */
    public function getActiveAgents(): array
    {
        $agents = [];
        if ($this->agent_archaeologist) {
            $agents[] = 'archaeologist';
        }
        if ($this->agent_historian) {
            $agents[] = 'historian';
        }
        if ($this->agent_negotiator) {
            $agents[] = 'negotiator';
        }
        if ($this->agent_chronicler) {
            $agents[] = 'chronicler';
        }

        return $agents;
    }
}
