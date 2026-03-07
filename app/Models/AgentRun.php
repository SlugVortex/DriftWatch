<?php
// app/Models/AgentRun.php
// Tracks every AI agent invocation — input, output, timing, cost, and score contribution.
// Used for the debug panel on PR detail page and analytics.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'pull_request_id',
        'agent_name',
        'status',
        'input_payload',
        'output_payload',
        'score_contribution',
        'reasoning',
        'tokens_used',
        'cost_usd',
        'duration_ms',
        'model_used',
        'input_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'output_payload' => 'array',
            'score_contribution' => 'integer',
            'tokens_used' => 'integer',
            'cost_usd' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }

    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * Returns Bootstrap badge color based on agent status.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scored' => 'success',
            'insufficient_data' => 'warning',
            'error' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Returns the agent display name with proper capitalization.
     */
    public function getAgentDisplayNameAttribute(): string
    {
        return ucfirst($this->agent_name);
    }
}
