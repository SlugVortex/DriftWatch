<?php

// app/Http/Controllers/GitHubWebhookController.php
// Receives GitHub webhook events for pull_request.opened and pull_request.synchronize.
// Creates PullRequest records and dispatches the configurable agent pipeline.
// Supports: pipeline templates, conditional rules, approval gates, stacked PR detection,
// environment-aware thresholds, per-agent retry, and full artifact traceability.

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\BlastRadiusResult;
use App\Models\DeploymentDecision;
use App\Models\DeploymentOutcome;
use App\Models\Incident;
use App\Models\PipelineConfig;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubWebhookController extends Controller
{
    /**
     * Handle incoming GitHub webhook.
     */
    public function handle(Request $request)
    {
        $event = $request->header('X-GitHub-Event');
        $payload = $request->all();

        Log::info('[GitHubWebhook] Received event.', [
            'event' => $event,
            'action' => $payload['action'] ?? 'unknown',
        ]);

        // SECURITY: Verify webhook signature if secret is configured
        $secret = config('services.github.webhook_secret');
        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256');
            $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

            if (! hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('[GitHubWebhook] Invalid signature. Rejecting request.');

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        if ($event !== 'pull_request') {
            return response()->json(['message' => 'Event ignored', 'event' => $event], 200);
        }

        $action = $payload['action'] ?? '';
        if (! in_array($action, ['opened', 'synchronize', 'reopened'])) {
            return response()->json(['message' => 'Action ignored', 'action' => $action], 200);
        }

        $prData = $payload['pull_request'] ?? [];
        $repoData = $payload['repository'] ?? [];

        // Create or update the PR record
        $pullRequest = PullRequest::updateOrCreate(
            ['github_pr_id' => (string) ($prData['id'] ?? 'unknown')],
            [
                'repo_full_name' => $repoData['full_name'] ?? 'unknown/unknown',
                'pr_number' => $prData['number'] ?? 0,
                'pr_title' => $prData['title'] ?? 'Untitled',
                'pr_author' => $prData['user']['login'] ?? 'unknown',
                'pr_url' => $prData['html_url'] ?? '#',
                'base_branch' => $prData['base']['ref'] ?? 'main',
                'head_branch' => $prData['head']['ref'] ?? 'unknown',
                'files_changed' => $prData['changed_files'] ?? 0,
                'additions' => $prData['additions'] ?? 0,
                'deletions' => $prData['deletions'] ?? 0,
                'status' => 'analyzing',
            ]
        );

        Log::info("[GitHubWebhook] PR #{$pullRequest->pr_number} created/updated. Starting agent pipeline.", [
            'pr_id' => $pullRequest->id,
            'repo' => $pullRequest->repo_full_name,
        ]);

        $this->runAgentPipeline($pullRequest);

        return response()->json([
            'message' => 'PR analyzed successfully',
            'pr_id' => $pullRequest->id,
            'status' => $pullRequest->fresh()->status,
        ], 200);
    }

    /**
     * Public accessor for the agent pipeline (called from DriftWatchController).
     */
    public function runAgentPipelinePublic(PullRequest $pullRequest): void
    {
        $this->runAgentPipeline($pullRequest);
    }

    /**
     * Resume a paused pipeline from where it left off.
     */
    public function resumePipeline(PullRequest $pullRequest): void
    {
        if (! $pullRequest->pipeline_paused) {
            Log::warning("[Pipeline] PR #{$pullRequest->pr_number} is not paused.");

            return;
        }

        $pausedStage = $pullRequest->paused_at_stage;

        Log::info("[Pipeline] Resuming PR #{$pullRequest->pr_number} from stage: {$pausedStage}");

        $pullRequest->update([
            'pipeline_paused' => false,
            'paused_at_stage' => null,
            'paused_at' => null,
            'paused_reason' => null,
            'status' => 'analyzing',
        ]);

        // Load the pipeline config
        $config = $this->getPipelineConfig($pullRequest);

        // Load existing results to resume from
        $pullRequest->load(['blastRadius', 'riskAssessment', 'deploymentDecision']);

        $briefingPack = $this->generateBriefingPack($pullRequest);

        // Resume based on paused stage
        if ($pausedStage === 'negotiator' && $pullRequest->riskAssessment) {
            $blastResult = $pullRequest->blastRadius
                ? $pullRequest->blastRadius->toArray()
                : $this->getMockArchaeologistResult();
            $riskResult = [
                'risk_score' => $pullRequest->riskAssessment->risk_score,
                'risk_level' => $pullRequest->riskAssessment->risk_level,
                'historical_incidents' => $pullRequest->riskAssessment->historical_incidents ?? [],
                'contributing_factors' => $pullRequest->riskAssessment->contributing_factors ?? [],
                'recommendation' => $pullRequest->riskAssessment->recommendation,
            ];

            $this->runNegotiatorStage($pullRequest, $config, $riskResult, $blastResult, $briefingPack);
        }

        // Run Chronicler if active
        $activeAgents = $config->getActiveAgents();
        if (in_array('chronicler', $activeAgents)) {
            $riskResult = $riskResult ?? [];
            $blastResult = $blastResult ?? [];
            $chroniclerResult = $this->runWithRetry('chronicler', $config, function () use ($pullRequest, $riskResult, $blastResult) {
                return $this->runChronicler($pullRequest, $riskResult, $blastResult);
            });

            DeploymentOutcome::updateOrCreate(
                ['pull_request_id' => $pullRequest->id],
                [
                    'predicted_risk_score' => $chroniclerResult['predicted_risk_score'] ?? $riskResult['risk_score'] ?? 0,
                    'incident_occurred' => $chroniclerResult['incident_occurred'] ?? false,
                    'actual_severity' => $chroniclerResult['actual_severity'] ?? null,
                    'actual_affected_services' => $chroniclerResult['actual_affected_services'] ?? [],
                    'prediction_accurate' => $chroniclerResult['prediction_accurate'] ?? true,
                    'post_mortem_notes' => $chroniclerResult['post_mortem_notes'] ?? '',
                ]
            );

            $this->recordAgentRun($pullRequest, 'chronicler', $chroniclerResult, $chroniclerResult['duration_ms'] ?? 0, $briefingPack);
        }

        $pullRequest->update(['status' => 'scored']);

        Log::info("[Pipeline] PR #{$pullRequest->pr_number} pipeline resumed and completed.");
    }

    /**
     * Run the configurable agent pipeline.
     * Respects pipeline templates, conditional rules, approval gates, and retries.
     */
    private function runAgentPipeline(PullRequest $pullRequest): void
    {
        // Clear previous agent runs for this PR (re-analysis)
        AgentRun::where('pull_request_id', $pullRequest->id)->delete();

        // Load pipeline configuration
        $config = $this->getPipelineConfig($pullRequest);
        $activeAgents = $config->getActiveAgents();

        Log::info("[Pipeline] Using template '{$config->name}' for PR #{$pullRequest->pr_number}.", [
            'active_agents' => $activeAgents,
            'environment' => $pullRequest->target_environment,
        ]);

        // Evaluate conditional rules
        $affectedFiles = $this->getChangedFilesList($pullRequest);
        $ruleResult = $config->evaluateConditionalRules($affectedFiles, $pullRequest->files_changed);

        if ($ruleResult['should_skip']) {
            Log::info("[Pipeline] Skipping analysis for PR #{$pullRequest->pr_number} — conditional rules matched.", [
                'matched_rules' => $ruleResult['matched_rules'],
            ]);
            $pullRequest->update(['status' => 'approved']);
            DeploymentDecision::updateOrCreate(
                ['pull_request_id' => $pullRequest->id],
                [
                    'decision' => 'approved',
                    'decided_by' => 'Pipeline Rule: '.($ruleResult['matched_rules'][0]['label'] ?? 'auto-skip'),
                    'decided_at' => now(),
                    'notification_message' => 'Auto-approved by conditional pipeline rule.',
                ]
            );

            return;
        }

        // Detect stacked PRs
        $stackedPrData = $this->detectStackedPRs($pullRequest);

        // Generate Briefing Pack
        $briefingPack = $this->generateBriefingPack($pullRequest);
        $briefingPack['pipeline_template'] = $config->name;
        $briefingPack['active_agents'] = $activeAgents;
        $briefingPack['target_environment'] = $pullRequest->target_environment;
        $briefingPack['conditional_rules_evaluated'] = $ruleResult;
        $briefingPack['stacked_prs'] = $stackedPrData;

        Log::info('[Pipeline] Briefing Pack generated.', ['briefing_id' => $briefingPack['briefing_id']]);

        // === Agent 1: Archaeologist (Blast Radius) ===
        $blastResult = [];
        if (in_array('archaeologist', $activeAgents)) {
            $pullRequest->update(['pipeline_stage' => 'archaeologist', 'stage_started_at' => now()]);

            $blastResult = $this->runWithRetry('archaeologist', $config, function () use ($pullRequest) {
                return $this->runArchaeologist($pullRequest);
            });

            BlastRadiusResult::updateOrCreate(
                ['pull_request_id' => $pullRequest->id],
                [
                    'affected_files' => $blastResult['affected_files'] ?? [],
                    'affected_services' => $blastResult['affected_services'] ?? [],
                    'affected_endpoints' => $blastResult['affected_endpoints'] ?? [],
                    'dependency_graph' => $blastResult['dependency_graph'] ?? [],
                    'file_descriptions' => $blastResult['file_descriptions'] ?? $blastResult['file_summaries'] ?? [],
                    'total_affected_files' => $blastResult['total_affected_files'] ?? count($blastResult['affected_files'] ?? []),
                    'total_affected_services' => $blastResult['total_affected_services'] ?? count($blastResult['affected_services'] ?? []),
                    'total_blast_radius_score' => $blastResult['total_blast_radius_score'] ?? 0,
                    'summary' => $blastResult['summary'] ?? 'Analysis complete.',
                ]
            );

            $briefingPack['archaeologist'] = [
                'status' => $blastResult['status'] ?? 'scored',
                'blast_radius_score' => $blastResult['total_blast_radius_score'] ?? 0,
                'affected_files' => $blastResult['affected_files'] ?? [],
                'affected_services' => $blastResult['affected_services'] ?? [],
                'change_classifications' => $blastResult['change_classifications'] ?? [],
                'ci_status' => $blastResult['ci_status'] ?? 'unknown',
                'bot_findings' => $blastResult['bot_findings'] ?? [],
                'summary' => $blastResult['summary'] ?? '',
            ];

            $this->recordAgentRun($pullRequest, 'archaeologist', $blastResult, $blastResult['duration_ms'] ?? 0, $briefingPack);
        }

        // === Agent 2: Historian (Risk Assessment) ===
        $riskResult = [];
        if (in_array('historian', $activeAgents)) {
            $pullRequest->update(['pipeline_stage' => 'historian', 'stage_started_at' => now()]);

            $riskResult = $this->runWithRetry('historian', $config, function () use ($pullRequest, $blastResult) {
                return $this->runHistorian($pullRequest, $blastResult);
            });

            RiskAssessment::updateOrCreate(
                ['pull_request_id' => $pullRequest->id],
                [
                    'risk_score' => $riskResult['risk_score'] ?? 50,
                    'risk_level' => $riskResult['risk_level'] ?? 'medium',
                    'historical_incidents' => $riskResult['historical_incidents'] ?? [],
                    'contributing_factors' => $riskResult['contributing_factors'] ?? [],
                    'recommendation' => $riskResult['recommendation'] ?? 'Review recommended.',
                ]
            );

            $briefingPack['historian'] = [
                'status' => $riskResult['status'] ?? 'scored',
                'risk_score' => $riskResult['risk_score'] ?? 0,
                'risk_level' => $riskResult['risk_level'] ?? 'unknown',
                'historical_incidents' => $riskResult['historical_incidents'] ?? [],
                'contributing_factors' => $riskResult['contributing_factors'] ?? [],
                'recommendation' => $riskResult['recommendation'] ?? '',
            ];

            $this->recordAgentRun($pullRequest, 'historian', $riskResult, $riskResult['duration_ms'] ?? 0, $briefingPack);
        }

        // === Approval Gate Check ===
        $riskScore = $riskResult['risk_score'] ?? 50;
        $shouldPause = $this->shouldPausePipeline($config, $riskScore, $pullRequest->target_environment ?? 'production', $ruleResult['force_gate']);

        if ($shouldPause) {
            $reason = $this->getPauseReason($config, $riskScore, $pullRequest->target_environment ?? 'production', $ruleResult);
            $pullRequest->update([
                'pipeline_paused' => true,
                'paused_at_stage' => 'negotiator',
                'paused_at' => now(),
                'paused_reason' => $reason,
                'status' => 'pending_review',
            ]);

            Log::info("[Pipeline] PR #{$pullRequest->pr_number} PAUSED at approval gate.", [
                'risk_score' => $riskScore,
                'reason' => $reason,
            ]);

            return;
        }

        // === Agent 3: Negotiator (Deployment Decision) ===
        if (in_array('negotiator', $activeAgents)) {
            $pullRequest->update(['pipeline_stage' => 'negotiator', 'stage_started_at' => now()]);

            $this->runNegotiatorStage($pullRequest, $config, $riskResult, $blastResult, $briefingPack, $stackedPrData);
        }

        // === Agent 4: Chronicler (Feedback Loop) ===
        if (in_array('chronicler', $activeAgents)) {
            $pullRequest->update(['pipeline_stage' => 'chronicler', 'stage_started_at' => now()]);

            $chroniclerResult = $this->runWithRetry('chronicler', $config, function () use ($pullRequest, $riskResult, $blastResult) {
                return $this->runChronicler($pullRequest, $riskResult, $blastResult);
            });

            DeploymentOutcome::updateOrCreate(
                ['pull_request_id' => $pullRequest->id],
                [
                    'predicted_risk_score' => $chroniclerResult['predicted_risk_score'] ?? $riskResult['risk_score'] ?? 0,
                    'incident_occurred' => $chroniclerResult['incident_occurred'] ?? false,
                    'actual_severity' => $chroniclerResult['actual_severity'] ?? null,
                    'actual_affected_services' => $chroniclerResult['actual_affected_services'] ?? [],
                    'prediction_accurate' => $chroniclerResult['prediction_accurate'] ?? true,
                    'post_mortem_notes' => $chroniclerResult['post_mortem_notes'] ?? '',
                ]
            );

            $briefingPack['chronicler'] = [
                'status' => $chroniclerResult['status'] ?? 'scored',
                'predicted_risk_score' => $chroniclerResult['predicted_risk_score'] ?? 0,
                'prediction_accurate' => $chroniclerResult['prediction_accurate'] ?? true,
                'post_mortem_notes' => $chroniclerResult['post_mortem_notes'] ?? '',
            ];

            $this->recordAgentRun($pullRequest, 'chronicler', $chroniclerResult, $chroniclerResult['duration_ms'] ?? 0, $briefingPack);
        }

        // Update PR status — pipeline complete
        $pullRequest->update(['status' => 'scored', 'pipeline_stage' => 'complete', 'stage_started_at' => now()]);

        $totalDuration = collect(AgentRun::where('pull_request_id', $pullRequest->id)->pluck('duration_ms'))->sum();

        Log::info("[Pipeline] Agent pipeline complete for PR #{$pullRequest->pr_number}.", [
            'risk_score' => $riskScore,
            'decision' => $pullRequest->fresh()->deploymentDecision?->decision ?? 'unknown',
            'total_duration_ms' => $totalDuration,
            'template' => $config->name,
        ]);

        // Post summary comment to the GitHub PR
        $this->postGitHubPrComment($pullRequest, $riskScore, $totalDuration);
    }

    /**
     * Run the Negotiator stage (extracted for resume support).
     */
    private function runNegotiatorStage(
        PullRequest $pullRequest,
        PipelineConfig $config,
        array $riskResult,
        array $blastResult,
        array $briefingPack,
        ?array $stackedPrData = null
    ): void {
        $decisionResult = $this->runWithRetry('negotiator', $config, function () use ($pullRequest, $riskResult) {
            return $this->runNegotiator($pullRequest, $riskResult);
        });

        // Apply environment-aware thresholds
        $riskScore = $riskResult['risk_score'] ?? 50;
        $envThreshold = $config->getThresholdForEnvironment($pullRequest->target_environment ?? 'production');
        if ($riskScore >= $envThreshold && ($decisionResult['decision'] ?? '') === 'approved') {
            $decisionResult['decision'] = 'pending_review';
            $decisionResult['notification_message'] = "Risk score {$riskScore} exceeds {$pullRequest->target_environment} threshold of {$envThreshold}. Manual review required.";
        }

        // === Deployment Weather Score ===
        $affectedServices = $blastResult['affected_services'] ?? [];
        $weatherResult = $this->computeWeatherScore($pullRequest, $config, $affectedServices);
        $weatherScore = $weatherResult['total_score'];
        $weatherChecks = $weatherResult['checks'];

        // Weather can escalate a decision
        if ($weatherScore >= 40 && ($decisionResult['decision'] ?? '') === 'approved') {
            $decisionResult['decision'] = 'pending_review';
            $decisionResult['notification_message'] = "Deployment weather score {$weatherScore} — conditions are unfavorable. Manual review required.";
        }

        Log::info("[Pipeline] Weather score for PR #{$pullRequest->pr_number}: {$weatherScore}", [
            'checks_fired' => collect($weatherChecks)->where('fired', true)->pluck('name')->toArray(),
        ]);

        // MRP generation
        $existingDecision = DeploymentDecision::where('pull_request_id', $pullRequest->id)->first();
        $mrpVersion = $existingDecision ? ($existingDecision->mrp_version ?? 0) + 1 : 1;

        $mrpPayload = $this->generateMrpPayload($pullRequest, $mrpVersion, $riskResult, $blastResult, $decisionResult, $briefingPack);
        $mrpPayload['weather'] = [
            'score' => $weatherScore,
            'checks' => $weatherChecks,
        ];

        // Compute combined blast radius for stacked PRs
        $combinedScore = null;
        $stackedIds = null;
        if (! empty($stackedPrData)) {
            $stackedIds = collect($stackedPrData)->pluck('id')->values()->toArray();
            $combinedScore = ($blastResult['total_blast_radius_score'] ?? 0)
                + collect($stackedPrData)->sum('blast_radius_score');
        }

        DeploymentDecision::updateOrCreate(
            ['pull_request_id' => $pullRequest->id],
            [
                'decision' => $decisionResult['decision'] ?? 'pending_review',
                'decided_by' => $decisionResult['decided_by'] ?? null,
                'has_concurrent_deploys' => $decisionResult['has_concurrent_deploys'] ?? false,
                'in_freeze_window' => $decisionResult['in_freeze_window'] ?? false,
                'notified_oncall' => $decisionResult['notified_oncall'] ?? false,
                'notification_message' => $decisionResult['notification_message'] ?? null,
                'mrp_payload' => $mrpPayload,
                'mrp_version' => $mrpVersion,
                'weather_score' => $weatherScore,
                'weather_checks' => $weatherChecks,
                'stacked_pr_ids' => $stackedIds,
                'combined_blast_radius_score' => $combinedScore,
                'decided_at' => $decisionResult['decided_at'] ?? null,
            ]
        );

        $briefingPack['negotiator'] = [
            'decision' => $decisionResult['decision'] ?? 'pending_review',
            'mrp_id' => $mrpPayload['mrp_id'],
            'mrp_version' => $mrpVersion,
            'stacked_prs' => $stackedIds,
            'combined_blast_radius' => $combinedScore,
        ];

        $this->recordAgentRun($pullRequest, 'negotiator', $decisionResult, $decisionResult['duration_ms'] ?? 0, $briefingPack);

        // === Teams Notification (Human Decision Loop) ===
        $this->sendTeamsNotification($pullRequest, $decisionResult, $riskResult, $blastResult, $weatherScore);
    }

    /**
     * Post a summary comment on the GitHub PR with risk score, verdict, and key findings.
     */
    private function postGitHubPrComment(PullRequest $pullRequest, int $riskScore, int $totalDurationMs): void
    {
        $ghToken = config('services.github.token');

        if (! $ghToken) {
            Log::info("[GitHub] No token configured — skipping PR comment for PR #{$pullRequest->pr_number}.");

            return;
        }

        try {
            $pullRequest->load(['blastRadius', 'riskAssessment', 'deploymentDecision']);

            $decision = $pullRequest->deploymentDecision;
            $risk = $pullRequest->riskAssessment;
            $blast = $pullRequest->blastRadius;

            // Determine verdict emoji and label
            $verdict = $decision->decision ?? 'pending_review';
            if ($verdict === 'approved') {
                $verdictEmoji = ':white_check_mark:';
                $verdictLabel = 'APPROVED';
            } elseif ($verdict === 'blocked') {
                $verdictEmoji = ':no_entry:';
                $verdictLabel = 'BLOCKED';
            } else {
                $verdictEmoji = ':warning:';
                $verdictLabel = 'NEEDS REVIEW';
            }

            // Risk level badge
            $riskLevel = ucfirst($risk->risk_level ?? 'medium');

            // Actionable summary
            if ($riskScore < 25) {
                $actionSummary = 'Low risk — likely safe to deploy.';
            } elseif ($riskScore < 50) {
                $actionSummary = 'Moderate risk — review recommended before deploying.';
            } elseif ($riskScore < 75) {
                $actionSummary = 'Elevated risk — could break production. Careful review required.';
            } else {
                $actionSummary = 'High risk — high probability of breaking production. Do NOT deploy without thorough review.';
            }

            // Contributing factors
            $factors = $risk->contributing_factors ?? [];
            $factorLines = '';
            foreach (array_slice($factors, 0, 5) as $factor) {
                $factorName = $factor['factor'] ?? $factor['name'] ?? 'Unknown';
                $factorImpact = $factor['impact'] ?? $factor['score'] ?? '';
                $factorLines .= "- **{$factorName}**" . ($factorImpact ? " (impact: {$factorImpact})" : '') . "\n";
            }

            // Blast radius summary
            $filesAffected = $blast->total_affected_files ?? 0;
            $servicesAffected = $blast->total_affected_services ?? 0;
            $blastScore = $blast->total_blast_radius_score ?? 0;

            // Weather score
            $weatherScore = $decision->weather_score ?? 0;
            $weatherEmoji = $weatherScore < 20 ? ':sunny:' : ($weatherScore < 40 ? ':partly_sunny:' : ':thunder_cloud_and_rain:');

            // Negotiator's PR comment if available
            $negotiatorComment = '';
            $negotiatorRun = AgentRun::where('pull_request_id', $pullRequest->id)
                ->where('agent_name', 'negotiator')
                ->first();
            if ($negotiatorRun && $negotiatorRun->reasoning) {
                $negotiatorComment = "\n### :speech_balloon: Negotiator Says\n> " . str_replace("\n", "\n> ", mb_substr($negotiatorRun->reasoning, 0, 500)) . "\n";
            }

            $durationSec = round($totalDurationMs / 1000, 1);

            $body = <<<MD
            ## {$verdictEmoji} DriftWatch Risk Analysis — {$verdictLabel}

            | Metric | Value |
            |--------|-------|
            | **Risk Score** | **{$riskScore}/100** ({$riskLevel}) |
            | **Blast Radius** | {$filesAffected} files, {$servicesAffected} services (score: {$blastScore}) |
            | **Deployment Weather** | {$weatherEmoji} {$weatherScore}/100 |
            | **Pipeline Duration** | {$durationSec}s |

            ### :dart: Bottom Line
            {$actionSummary}
            {$negotiatorComment}
            MD;

            if ($factorLines) {
                $body .= "\n### :mag: Key Risk Factors\n{$factorLines}";
            }

            $body .= "\n---\n:robot: *Analyzed by [DriftWatch](https://github.com) — Multi-Agent Pre-Deployment Risk Intelligence*";

            // Remove leading whitespace from heredoc indentation
            $body = implode("\n", array_map('ltrim', explode("\n", $body)));

            $url = "https://api.github.com/repos/{$pullRequest->repo_full_name}/issues/{$pullRequest->pr_number}/comments";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(15)->post($url, [
                'body' => $body,
            ]);

            if ($response->successful()) {
                Log::info("[GitHub] Posted risk summary comment on PR #{$pullRequest->pr_number}.", [
                    'comment_id' => $response->json('id'),
                    'html_url' => $response->json('html_url'),
                ]);
            } else {
                Log::warning("[GitHub] Failed to post comment on PR #{$pullRequest->pr_number}.", [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("[GitHub] Exception posting PR comment: {$e->getMessage()}", [
                'pr' => $pullRequest->pr_number,
                'repo' => $pullRequest->repo_full_name,
            ]);
        }
    }

    /**
     * Generate the Briefing Pack — structured input shared across all agents.
     */
    private function generateBriefingPack(PullRequest $pullRequest): array
    {
        return [
            'briefing_id' => "PR-{$pullRequest->pr_number}-".now()->timestamp,
            'pr_number' => $pullRequest->pr_number,
            'pr_title' => $pullRequest->pr_title,
            'pr_author' => $pullRequest->pr_author,
            'repo_full_name' => $pullRequest->repo_full_name,
            'base_branch' => $pullRequest->base_branch,
            'head_branch' => $pullRequest->head_branch,
            'files_changed_count' => $pullRequest->files_changed,
            'additions' => $pullRequest->additions,
            'deletions' => $pullRequest->deletions,
            'pr_url' => $pullRequest->pr_url,
            'requested_analysis_time' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate the Merge-Readiness Pack payload.
     */
    private function generateMrpPayload(
        PullRequest $pullRequest,
        int $mrpVersion,
        array $riskResult,
        array $blastResult,
        array $decisionResult,
        array $briefingPack
    ): array {
        $riskScore = $riskResult['risk_score'] ?? 50;

        return [
            'mrp_id' => "MRP-{$pullRequest->pr_number}-v{$mrpVersion}",
            'version' => $mrpVersion,
            'generated_at' => now()->toIso8601String(),
            'decision' => strtoupper($decisionResult['decision'] ?? 'PENDING_REVIEW'),
            'overall_risk_score' => $riskScore,
            'risk_level' => strtoupper($riskResult['risk_level'] ?? 'MEDIUM'),
            'evidence' => [
                'blast_radius' => [
                    'score' => $blastResult['total_blast_radius_score'] ?? 0,
                    'summary' => $blastResult['summary'] ?? '',
                    'affected_services' => $blastResult['affected_services'] ?? [],
                    'affected_files_count' => count($blastResult['affected_files'] ?? []),
                ],
                'incident_history' => [
                    'score' => $riskResult['match_summary']['history_score'] ?? 0,
                    'summary' => $riskResult['recommendation'] ?? '',
                    'matching_incidents' => $riskResult['historical_incidents'] ?? [],
                ],
                'ci_status' => [
                    'status' => $blastResult['ci_status'] ?? 'unknown',
                    'failing_checks' => $blastResult['failing_checks'] ?? [],
                    'ci_risk_addition' => $blastResult['ci_risk_addition'] ?? 0,
                ],
                'bot_findings' => $blastResult['bot_findings'] ?? [],
            ],
            'conditions_for_approval' => $riskResult['contributing_factors'] ?? [],
            'approved_by' => $decisionResult['decided_by'] ?? null,
            'approved_at' => $decisionResult['decided_at'] ?? null,
            'audit_trail' => [
                [
                    'action' => 'generated',
                    'by' => 'DriftWatch AI Pipeline',
                    'at' => now()->toIso8601String(),
                    'details' => "MRP v{$mrpVersion} generated with risk score {$riskScore}/100",
                ],
            ],
            'briefing_id' => $briefingPack['briefing_id'],
        ];
    }

    /**
     * Get the pipeline config for a PR (by template name or default).
     */
    private function getPipelineConfig(PullRequest $pullRequest): PipelineConfig
    {
        $templateName = $pullRequest->pipeline_template ?? 'full';

        $config = PipelineConfig::where('name', $templateName)->first();

        if (! $config) {
            $config = PipelineConfig::where('is_default', true)->first();
        }

        if (! $config) {
            // Create default templates on first use
            PipelineConfig::seedBuiltInTemplates();
            $config = PipelineConfig::where('is_default', true)->first();
        }

        return $config;
    }

    /**
     * Determine if the pipeline should pause for approval.
     */
    private function shouldPausePipeline(PipelineConfig $config, int $riskScore, string $environment, bool $forceGate): bool
    {
        // Forced gate from conditional rules always pauses
        if ($forceGate) {
            return true;
        }

        // Config-level gate
        if ($config->require_approval_after_scoring) {
            // Unless auto-approve threshold is met
            if ($riskScore <= $config->auto_approve_below_score) {
                return false;
            }

            return true;
        }

        // Environment-level gate
        $thresholds = $config->environment_thresholds ?? [];
        $envConfig = $thresholds[$environment] ?? [];
        if (! empty($envConfig['require_approval'])) {
            $envThreshold = $envConfig['risk_threshold'] ?? 50;
            if ($riskScore >= $envThreshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a human-readable pause reason.
     */
    private function getPauseReason(PipelineConfig $config, int $riskScore, string $environment, array $ruleResult): string
    {
        $reasons = [];

        if (! empty($ruleResult['force_gate'])) {
            $labels = collect($ruleResult['matched_rules'])
                ->where('action', 'force_gate')
                ->pluck('label')
                ->implode(', ');
            $reasons[] = "Conditional rule: {$labels}";
        }

        if ($config->require_approval_after_scoring) {
            $reasons[] = "Pipeline template '{$config->label}' requires approval after scoring";
        }

        $thresholds = $config->environment_thresholds ?? [];
        $envConfig = $thresholds[$environment] ?? [];
        if (! empty($envConfig['require_approval'])) {
            $envThreshold = $envConfig['risk_threshold'] ?? 50;
            if ($riskScore >= $envThreshold) {
                $reasons[] = "Risk score {$riskScore} exceeds {$environment} threshold of {$envThreshold}";
            }
        }

        return implode('. ', $reasons) ?: "Manual approval required (score: {$riskScore})";
    }

    /**
     * Run an agent with retry logic.
     */
    private function runWithRetry(string $agentName, PipelineConfig $config, callable $agentFn): array
    {
        $maxRetries = $config->max_retries_per_agent;
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $maxRetries) {
            $start = microtime(true);
            try {
                $result = $agentFn();
                $result['duration_ms'] = $result['duration_ms'] ?? (int) ((microtime(true) - $start) * 1000);
                $result['attempt'] = $attempt + 1;

                return $result;
            } catch (\Exception $e) {
                $lastError = $e;
                $attempt++;
                Log::warning("[Pipeline] {$agentName} attempt {$attempt} failed.", [
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt <= $maxRetries,
                ]);

                if ($attempt <= $maxRetries && $config->retry_on_timeout) {
                    usleep(500000); // 500ms between retries
                }
            }
        }

        Log::error("[Pipeline] {$agentName} exhausted all retries. Using mock.", [
            'max_retries' => $maxRetries,
            'last_error' => $lastError?->getMessage(),
        ]);

        // Fall through to mock (the agent methods already have mock fallback)
        return $agentFn();
    }

    /**
     * Detect stacked/dependent PRs targeting the same files or branches.
     *
     * @return array<int, array{id: int, pr_number: int, pr_title: string, overlap_files: array, blast_radius_score: int, relationship: string}>
     */
    private function detectStackedPRs(PullRequest $pullRequest): array
    {
        $stacked = [];

        // Find PRs that target this PR's head branch (stacked on top)
        $childPRs = PullRequest::where('id', '!=', $pullRequest->id)
            ->where('repo_full_name', $pullRequest->repo_full_name)
            ->where('base_branch', $pullRequest->head_branch)
            ->whereIn('status', ['open', 'pending_review', 'analyzing', 'scored'])
            ->get();

        foreach ($childPRs as $child) {
            $stacked[] = [
                'id' => $child->id,
                'pr_number' => $child->pr_number,
                'pr_title' => $child->pr_title,
                'overlap_files' => [],
                'blast_radius_score' => $child->blastRadius?->total_affected_files ?? 0,
                'relationship' => 'child',
                'status' => $child->status,
            ];
        }

        // Find the parent PR (this PR's base branch is another PR's head branch)
        $parentPRs = PullRequest::where('id', '!=', $pullRequest->id)
            ->where('repo_full_name', $pullRequest->repo_full_name)
            ->where('head_branch', $pullRequest->base_branch)
            ->whereIn('status', ['open', 'pending_review', 'analyzing', 'scored'])
            ->get();

        foreach ($parentPRs as $parent) {
            $stacked[] = [
                'id' => $parent->id,
                'pr_number' => $parent->pr_number,
                'pr_title' => $parent->pr_title,
                'overlap_files' => [],
                'blast_radius_score' => $parent->blastRadius?->total_affected_files ?? 0,
                'relationship' => 'parent',
                'status' => $parent->status,
            ];
        }

        // Find PRs touching overlapping files
        $currentFiles = $pullRequest->blastRadius?->affected_files ?? [];
        if (! empty($currentFiles)) {
            $siblingPRs = PullRequest::where('id', '!=', $pullRequest->id)
                ->where('repo_full_name', $pullRequest->repo_full_name)
                ->whereIn('status', ['open', 'pending_review', 'analyzing', 'scored'])
                ->whereHas('blastRadius')
                ->with('blastRadius')
                ->get();

            foreach ($siblingPRs as $sibling) {
                // Skip if already found as parent/child
                if (collect($stacked)->contains('id', $sibling->id)) {
                    continue;
                }

                $siblingFiles = $sibling->blastRadius->affected_files ?? [];
                $overlap = array_intersect($currentFiles, $siblingFiles);

                if (count($overlap) > 0) {
                    $stacked[] = [
                        'id' => $sibling->id,
                        'pr_number' => $sibling->pr_number,
                        'pr_title' => $sibling->pr_title,
                        'overlap_files' => array_values($overlap),
                        'blast_radius_score' => $sibling->blastRadius->total_affected_files ?? 0,
                        'relationship' => 'sibling',
                        'status' => $sibling->status,
                    ];
                }
            }
        }

        if (! empty($stacked)) {
            Log::info('[Pipeline] Detected '.count($stacked)." stacked/related PR(s) for PR #{$pullRequest->pr_number}.", [
                'stacked' => collect($stacked)->pluck('pr_number')->toArray(),
            ]);
        }

        return $stacked;
    }

    /**
     * Get list of changed files for a PR (from blast radius or GitHub API).
     *
     * @return array<string>
     */
    private function getChangedFilesList(PullRequest $pullRequest): array
    {
        // Try from existing blast radius
        $blast = BlastRadiusResult::where('pull_request_id', $pullRequest->id)->first();
        if ($blast && ! empty($blast->affected_files)) {
            return $blast->affected_files;
        }

        // Try from GitHub API
        try {
            $ghToken = config('services.github.token');
            if ($ghToken) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$ghToken}",
                    'Accept' => 'application/vnd.github.v3+json',
                ])->timeout(10)->get("https://api.github.com/repos/{$pullRequest->repo_full_name}/pulls/{$pullRequest->pr_number}/files");

                if ($response->successful()) {
                    return collect($response->json())->pluck('filename')->toArray();
                }
            }
        } catch (\Exception $e) {
            Log::debug("[Pipeline] Could not fetch file list from GitHub: {$e->getMessage()}");
        }

        return [];
    }

    /**
     * Record an AgentRun entry for tracking and the debug panel.
     */
    private function recordAgentRun(PullRequest $pullRequest, string $agentName, array $result, int $durationMs, ?array $briefingPack = null): void
    {
        $status = $result['status'] ?? 'scored';
        $scoreContribution = 0;
        $reasoning = '';

        if ($agentName === 'archaeologist') {
            $scoreContribution = $result['total_blast_radius_score'] ?? 0;
            $reasoning = $result['summary'] ?? '';
        } elseif ($agentName === 'historian') {
            $scoreContribution = $result['risk_score'] ?? 0;
            $reasoning = $result['recommendation'] ?? '';
        } elseif ($agentName === 'negotiator') {
            $reasoning = $result['pr_comment'] ?? $result['notification_message'] ?? '';
        } elseif ($agentName === 'chronicler') {
            $reasoning = $result['post_mortem_notes'] ?? '';
        }

        $tokensUsed = $result['tokens_used'] ?? 0;
        // Estimate tokens from response size if not reported by the agent
        if ($tokensUsed === 0 && !empty($result)) {
            $responseText = json_encode($result);
            $tokensUsed = (int) ceil(mb_strlen($responseText) / 3.5); // ~3.5 chars per token estimate
        }

        $inputPayload = $briefingPack ?? [
            'repo' => $pullRequest->repo_full_name,
            'pr_number' => $pullRequest->pr_number,
        ];

        AgentRun::create([
            'pull_request_id' => $pullRequest->id,
            'agent_name' => $agentName,
            'status' => $status,
            'input_payload' => $inputPayload,
            'output_payload' => $result,
            'score_contribution' => $scoreContribution,
            'reasoning' => mb_substr($reasoning, 0, 5000),
            'tokens_used' => $tokensUsed,
            'cost_usd' => $tokensUsed * 0.000003, // ~$3/1M tokens blended rate for GPT-4.1-mini
            'duration_ms' => $result['duration_ms'] ?? $durationMs,
            'model_used' => config('services.azure_openai.deployment', 'gpt-4.1-mini'),
            'input_hash' => md5($pullRequest->repo_full_name.':'.$pullRequest->pr_number.':'.$agentName),
        ]);

        Log::info("[AgentRun] Recorded {$agentName} run for PR #{$pullRequest->pr_number}.", [
            'status' => $status,
            'score_contribution' => $scoreContribution,
            'duration_ms' => $durationMs,
            'attempt' => $result['attempt'] ?? 1,
        ]);
    }

    /**
     * Build an HTTP client with the Azure Function Key header.
     */
    private function agentHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $headers = [];
        $functionKey = config('services.agents.function_key');
        if ($functionKey) {
            $headers['x-functions-key'] = $functionKey;
        }

        return Http::timeout(60)->withHeaders($headers);
    }

    /**
     * Call Azure OpenAI directly to analyze code when Azure Function agents are unavailable.
     * This gives us real AI-powered analysis instead of dumb pattern matching.
     */
    private function analyzeWithAzureOpenAI(string $agentType, array $context, PullRequest $pullRequest): ?array
    {
        $endpoint = config('services.azure_openai.endpoint');
        $apiKey = config('services.azure_openai.api_key');
        $deployment = config('services.azure_openai.deployment');

        if (! $endpoint || ! $apiKey || ! $deployment) {
            Log::debug("[DirectAI:{$agentType}] No Azure OpenAI credentials — skipping direct analysis.");

            return null;
        }

        try {
            if ($agentType === 'archaeologist') {
                return $this->directAIArchaeologist($context, $pullRequest, $endpoint, $apiKey, $deployment);
            } elseif ($agentType === 'historian') {
                return $this->directAIHistorian($context, $pullRequest, $endpoint, $apiKey, $deployment);
            }
        } catch (\Exception $e) {
            Log::warning("[DirectAI:{$agentType}] Failed: {$e->getMessage()}");
        }

        return null;
    }

    private function directAIArchaeologist(array $codeContext, PullRequest $pullRequest, string $endpoint, string $apiKey, string $deployment): ?array
    {
        $changedFiles = $codeContext['changed_files'] ?? [];
        $diffText = $codeContext['diff_text'] ?? '';

        if (empty($changedFiles) && empty($diffText)) {
            return null;
        }

        // Build a compact file summary for the prompt
        $fileSummary = '';
        foreach ($changedFiles as $file) {
            $status = strtoupper($file['status'] ?? 'modified');
            $fileSummary .= "\n- [{$status}] {$file['filename']} (+{$file['additions']}/-{$file['deletions']})";
        }

        // Truncate diff for token limits (~15k chars ≈ ~4k tokens)
        $truncatedDiff = substr($diffText, 0, 15000);

        $systemPrompt = <<<'PROMPT'
You are the Archaeologist Agent — a blast radius analyzer for code changes. You must be STACK-AWARE.

CRITICAL RULES:
1. If a core framework file is DELETED (status: removed), this is almost always CRITICAL risk (score 50+)
2. Core files include: routes/web.php, routes/api.php, config/app.php, bootstrap/app.php, composer.json, package.json, .env, manage.py, urls.py, settings.py, requirements.txt
3. Look at the DIFF — if lines are only removed with nothing added, functionality is being destroyed
4. Understand framework conventions: in Laravel, routes/web.php defines ALL web routes. Deleting it = zero routes = app is broken
5. Check for semantic issues: removed error handling, exposed secrets, broken imports, missing dependencies
6. Check what other files DEPEND on the changed files — these are downstream blast radius

Scoring rubric:
- 0-10: Cosmetic (CSS, docs, tests only)
- 11-25: Low risk (views, minor logic, non-critical files)
- 26-50: Medium risk (controllers, models, services, config)
- 51-75: High risk (auth, middleware, migrations, multiple services affected)
- 76-100: Critical (core files deleted, breaking API changes, security vulnerabilities)

You MUST respond with valid JSON only. No markdown, no explanation outside JSON.
PROMPT;

        $userPrompt = "Analyze this PR for {$pullRequest->repo_full_name} PR #{$pullRequest->pr_number}.\n\nChanged files:{$fileSummary}\n\nDiff (first 15KB):\n```\n{$truncatedDiff}\n```\n\nRespond with this exact JSON structure:\n{\"status\":\"scored\",\"affected_files\":[\"file1.php\"],\"affected_services\":[\"service-name\"],\"affected_endpoints\":[\"/path\"],\"dependency_graph\":{\"file.php\":[\"dep1.php\"]},\"change_classifications\":[{\"file\":\"file.php\",\"change_type\":\"type\",\"risk_score\":0,\"reasoning\":\"why\"}],\"total_blast_radius_score\":0,\"risk_indicators\":[\"indicator\"],\"summary\":\"what this PR does and why it's risky or safe\",\"code_analysis\":\"detailed analysis\"}";

        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment}/chat/completions?api-version=2024-10-21";

        $response = Http::timeout(30)->withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.1,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (! $response->successful()) {
            Log::warning('[DirectAI:Archaeologist] Azure OpenAI call failed.', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);

        if (! $data || ! isset($data['total_blast_radius_score'])) {
            Log::warning('[DirectAI:Archaeologist] Invalid JSON response.', ['content' => substr($content, 0, 500)]);

            return null;
        }

        Log::info('[DirectAI:Archaeologist] AI-powered analysis complete.', [
            'score' => $data['total_blast_radius_score'],
            'files' => count($data['affected_files'] ?? []),
        ]);

        return [
            'status' => $data['status'] ?? 'scored',
            'affected_files' => $data['affected_files'] ?? [],
            'affected_services' => $data['affected_services'] ?? [],
            'affected_endpoints' => $data['affected_endpoints'] ?? [],
            'dependency_graph' => $data['dependency_graph'] ?? [],
            'change_classifications' => $data['change_classifications'] ?? [],
            'total_blast_radius_score' => (int) ($data['total_blast_radius_score'] ?? 0),
            'total_affected_files' => count($data['affected_files'] ?? []),
            'total_affected_services' => count($data['affected_services'] ?? []),
            'risk_indicators' => $data['risk_indicators'] ?? [],
            'ci_status' => $data['ci_status'] ?? 'unknown',
            'failing_checks' => $data['failing_checks'] ?? [],
            'ci_risk_addition' => $data['ci_risk_addition'] ?? 0,
            'bot_findings' => $data['bot_findings'] ?? [],
            'bot_risk_addition' => $data['bot_risk_addition'] ?? 0,
            'file_summaries' => $data['file_summaries'] ?? [],
            'code_analysis' => $data['code_analysis'] ?? '',
            'summary' => $data['summary'] ?? '',
        ];
    }

    private function directAIHistorian(array $blastResult, PullRequest $pullRequest, string $endpoint, string $apiKey, string $deployment): ?array
    {
        // Fetch real incidents from DB
        $incidents = [];
        try {
            $dbIncidents = \App\Models\Incident::where('occurred_at', '>=', now()->subDays(90))
                ->orderByDesc('severity')->limit(20)->get();
            foreach ($dbIncidents as $inc) {
                $incidents[] = [
                    'id' => $inc->id,
                    'title' => $inc->title,
                    'severity' => $inc->severity,
                    'affected_services' => $inc->affected_services,
                    'root_cause_file' => $inc->root_cause_file,
                    'days_ago' => $inc->occurred_at ? (int) now()->diffInDays($inc->occurred_at) : 0,
                ];
            }
        } catch (\Exception $e) {
            Log::debug('[DirectAI:Historian] DB query failed: ' . $e->getMessage());
        }

        $blastJson = json_encode([
            'score' => $blastResult['total_blast_radius_score'] ?? 0,
            'files' => $blastResult['affected_files'] ?? [],
            'services' => $blastResult['affected_services'] ?? [],
            'classifications' => array_slice($blastResult['change_classifications'] ?? [], 0, 10),
            'risk_indicators' => $blastResult['risk_indicators'] ?? [],
            'summary' => $blastResult['summary'] ?? '',
        ], JSON_PRETTY_PRINT);

        $incidentsJson = json_encode($incidents, JSON_PRETTY_PRINT);

        $systemPrompt = <<<'PROMPT'
You are the Historian Agent — a risk calculator that correlates code changes with historical incident data.

RULES:
1. The blast_radius_score from the Archaeologist is your baseline
2. Add points for matching historical incidents (file matches +25, service matches +10, change type matches +15)
3. Cap history score at 40 additional points
4. Final risk_score = blast_radius_score + history_score + ci_risk + bot_risk (max 100)
5. If a critical file was deleted and it would break the application, score should be 75+
6. Be specific about WHY something is risky — "this file controls all routes" not "routing change detected"

Risk levels: low (0-24), medium (25-49), high (50-74), critical (75-100)

Respond with valid JSON only.
PROMPT;

        $userPrompt = "Blast radius analysis:\n{$blastJson}\n\nHistorical incidents (last 90 days):\n{$incidentsJson}\n\nRespond with:\n{\"status\":\"scored\",\"risk_score\":0,\"risk_level\":\"low\",\"historical_incidents\":[{\"id\":\"INC-001\",\"title\":\"x\",\"severity\":1,\"days_ago\":0,\"relevance\":\"why\",\"match_type\":\"file|service\",\"match_score\":0}],\"match_summary\":{\"file_matches\":0,\"service_matches\":0,\"history_score\":0,\"blast_radius_score\":0},\"contributing_factors\":[\"factor1\"],\"recommendation\":\"what to do\"}";

        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment}/chat/completions?api-version=2024-10-21";

        $response = Http::timeout(30)->withHeaders([
            'api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.1,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'],
        ]);

        if (! $response->successful()) {
            Log::warning('[DirectAI:Historian] Azure OpenAI call failed.', ['status' => $response->status()]);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        $data = json_decode($content, true);

        if (! $data || ! isset($data['risk_score'])) {
            return null;
        }

        Log::info('[DirectAI:Historian] AI-powered risk scoring complete.', [
            'risk_score' => $data['risk_score'],
            'risk_level' => $data['risk_level'] ?? 'unknown',
        ]);

        return [
            'status' => $data['status'] ?? 'scored',
            'risk_score' => (int) ($data['risk_score'] ?? 0),
            'risk_level' => $data['risk_level'] ?? 'medium',
            'historical_incidents' => $data['historical_incidents'] ?? [],
            'match_summary' => $data['match_summary'] ?? [],
            'contributing_factors' => $data['contributing_factors'] ?? [],
            'recommendation' => $data['recommendation'] ?? '',
        ];
    }

    /**
     * Fetch PR diff and changed file contents from the GitHub API.
     * This gives agents actual code to analyze, not just file names.
     *
     * @return array{diff_text: string, changed_files: array<int, array{filename: string, status: string, patch: string, full_file_content: string|null}>}
     */
    private function fetchPrCodeContext(PullRequest $pullRequest): array
    {
        $ghToken = config('services.github.token');
        $repo = $pullRequest->repo_full_name;
        $prNumber = $pullRequest->pr_number;
        $diffText = '';
        $changedFiles = [];

        if (! $ghToken) {
            Log::info('[CodeContext] No GitHub token configured — skipping code fetch.');

            return ['diff_text' => '', 'changed_files' => []];
        }

        $ghHttp = Http::withHeaders([
            'Authorization' => "Bearer {$ghToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->timeout(15);

        // 1. Fetch the unified diff
        try {
            $diffResponse = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3.diff',
            ])->timeout(15)->get("https://api.github.com/repos/{$repo}/pulls/{$prNumber}");

            if ($diffResponse->successful()) {
                $diffText = $diffResponse->body();
                // Truncate very large diffs to prevent token overflow (max ~200KB)
                if (strlen($diffText) > 200000) {
                    $diffText = substr($diffText, 0, 200000)."\n\n... [diff truncated at 200KB]";
                }
                Log::info('[CodeContext] Fetched PR diff.', ['size_bytes' => strlen($diffText)]);
            }
        } catch (\Exception $e) {
            Log::warning("[CodeContext] Failed to fetch PR diff: {$e->getMessage()}");
        }

        // 2. Fetch the list of changed files with patches
        try {
            $filesResponse = $ghHttp->get("https://api.github.com/repos/{$repo}/pulls/{$prNumber}/files", [
                'per_page' => 100,
            ]);

            if ($filesResponse->successful()) {
                $rawFiles = $filesResponse->json();
                Log::info('[CodeContext] Fetched changed files list.', ['count' => count($rawFiles)]);

                // Skip binary, vendor, and lock files
                $skipPatterns = [
                    '/vendor\//', '/node_modules\//', '/\.lock$/', '/package-lock\.json$/',
                    '/composer\.lock$/', '/\.min\.js$/', '/\.min\.css$/',
                    '/\.png$/', '/\.jpg$/', '/\.gif$/', '/\.ico$/', '/\.woff/',
                    '/\.ttf$/', '/\.eot$/', '/\.svg$/', '/\.map$/',
                ];

                foreach ($rawFiles as $file) {
                    $filename = $file['filename'] ?? '';

                    // Skip binary/vendor files
                    $skip = false;
                    foreach ($skipPatterns as $pattern) {
                        if (preg_match($pattern, $filename)) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) {
                        continue;
                    }

                    $fileEntry = [
                        'filename' => $filename,
                        'status' => $file['status'] ?? 'modified',
                        'additions' => $file['additions'] ?? 0,
                        'deletions' => $file['deletions'] ?? 0,
                        'patch' => $file['patch'] ?? '',
                        'full_file_content' => null,
                    ];

                    $changedFiles[] = $fileEntry;
                }

                // 3. Fetch full file contents for high-risk files (max 30 files)
                $highRiskPatterns = [
                    '/migration/', '/middleware/', '/auth/', '/config\//',
                    '/\.env/', '/routes\//', '/Kernel\.php$/', '/Controller\.php$/',
                    '/Service\.php$/', '/Model\.php$/', '/Provider\.php$/',
                    '/Guard\.php$/', '/Policy\.php$/', '/\.sql$/',
                ];

                $filesToFetch = [];
                foreach ($changedFiles as $idx => $file) {
                    $isHighRisk = false;
                    foreach ($highRiskPatterns as $pattern) {
                        if (preg_match($pattern, $file['filename'])) {
                            $isHighRisk = true;
                            break;
                        }
                    }
                    // Also fetch if the file is small (< 500 lines changed)
                    if ($isHighRisk || ($file['additions'] + $file['deletions']) > 20) {
                        $filesToFetch[] = $idx;
                    }
                }

                // Limit to 30 files max
                $filesToFetch = array_slice($filesToFetch, 0, 30);

                foreach ($filesToFetch as $idx) {
                    $filename = $changedFiles[$idx]['filename'];
                    try {
                        $contentResponse = $ghHttp->get("https://api.github.com/repos/{$repo}/contents/{$filename}", [
                            'ref' => $pullRequest->head_branch,
                        ]);

                        if ($contentResponse->successful()) {
                            $contentData = $contentResponse->json();
                            $encoding = $contentData['encoding'] ?? '';
                            $content = $contentData['content'] ?? '';

                            if ($encoding === 'base64' && $content) {
                                $decoded = base64_decode($content);
                                // Truncate very large files (max 50KB per file)
                                if (strlen($decoded) > 50000) {
                                    $decoded = substr($decoded, 0, 50000)."\n\n// ... [file truncated at 50KB]";
                                }
                                $changedFiles[$idx]['full_file_content'] = $decoded;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::debug("[CodeContext] Could not fetch content for {$filename}: {$e->getMessage()}");
                    }
                }

                $fetchedCount = collect($changedFiles)->whereNotNull('full_file_content')->count();
                Log::info("[CodeContext] Fetched full content for {$fetchedCount}/{$prNumber} files.");
            }
        } catch (\Exception $e) {
            Log::warning("[CodeContext] Failed to fetch changed files: {$e->getMessage()}");
        }

        return [
            'diff_text' => $diffText,
            'changed_files' => $changedFiles,
        ];
    }

    private function runArchaeologist(PullRequest $pullRequest): array
    {
        $url = config('services.agents.archaeologist_url');

        // Fetch actual PR code context from GitHub
        $codeContext = $this->fetchPrCodeContext($pullRequest);

        try {
            if ($url) {
                $response = $this->agentHttp()->post($url, [
                    'repo_full_name' => $pullRequest->repo_full_name,
                    'pr_number' => $pullRequest->pr_number,
                    'base_branch' => $pullRequest->base_branch,
                    'head_branch' => $pullRequest->head_branch,
                    'diff_text' => $codeContext['diff_text'],
                    'changed_files' => $codeContext['changed_files'],
                ]);

                if ($response->successful()) {
                    Log::info("[Agent:Archaeologist] Got response for PR #{$pullRequest->pr_number}.");
                    $data = $response->json();

                    return [
                        'status' => $data['status'] ?? 'scored',
                        'affected_files' => $data['affected_files'] ?? [],
                        'affected_services' => $data['affected_services'] ?? [],
                        'affected_endpoints' => $data['affected_endpoints'] ?? [],
                        'dependency_graph' => $data['dependency_graph'] ?? [],
                        'change_classifications' => $data['change_classifications'] ?? [],
                        'total_blast_radius_score' => $data['total_blast_radius_score'] ?? 0,
                        'total_affected_files' => $data['total_affected_files'] ?? count($data['affected_files'] ?? []),
                        'total_affected_services' => $data['total_affected_services'] ?? count($data['affected_services'] ?? []),
                        'risk_indicators' => $data['risk_indicators'] ?? [],
                        'ci_status' => $data['ci_status'] ?? 'unknown',
                        'failing_checks' => $data['failing_checks'] ?? [],
                        'ci_risk_addition' => $data['ci_risk_addition'] ?? 0,
                        'bot_findings' => $data['bot_findings'] ?? [],
                        'bot_risk_addition' => $data['bot_risk_addition'] ?? 0,
                        'file_summaries' => $data['file_summaries'] ?? [],
                        'code_analysis' => $data['code_analysis'] ?? '',
                        'summary' => $data['summary'] ?? 'Analysis complete.',
                        'duration_ms' => $data['duration_ms'] ?? 0,
                    ];
                }

                Log::warning('[Agent:Archaeologist] Non-200 response.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[Agent:Archaeologist] Agent call failed, using mock.', ['error' => $e->getMessage()]);
        }

        // Try direct Azure OpenAI analysis before falling back to pattern matching
        $aiResult = $this->analyzeWithAzureOpenAI('archaeologist', $codeContext, $pullRequest);
        if ($aiResult) {
            return $aiResult;
        }

        return $this->getMockArchaeologistResult($codeContext);
    }

    private function runHistorian(PullRequest $pullRequest, array $blastResult): array
    {
        $url = config('services.agents.historian_url');

        try {
            if ($url) {
                $response = $this->agentHttp()->post($url, [
                    'affected_services' => $blastResult['affected_services'] ?? [],
                    'affected_files' => $blastResult['affected_files'] ?? [],
                    'risk_indicators' => $blastResult['risk_indicators'] ?? [],
                    'pr_number' => $pullRequest->pr_number,
                    'repo_full_name' => $pullRequest->repo_full_name,
                    'blast_radius_score' => $blastResult['total_blast_radius_score'] ?? 0,
                    'change_classifications' => $blastResult['change_classifications'] ?? [],
                    'ci_status' => $blastResult['ci_status'] ?? 'unknown',
                    'ci_risk_addition' => $blastResult['ci_risk_addition'] ?? 0,
                    'bot_findings' => $blastResult['bot_findings'] ?? [],
                    'bot_risk_addition' => $blastResult['bot_risk_addition'] ?? 0,
                ]);

                if ($response->successful()) {
                    Log::info("[Agent:Historian] Got response for PR #{$pullRequest->pr_number}.");

                    return $response->json();
                }

                Log::warning('[Agent:Historian] Non-200 response.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[Agent:Historian] Agent call failed, using mock.', ['error' => $e->getMessage()]);
        }

        // Try direct Azure OpenAI analysis before falling back to pattern matching
        $aiResult = $this->analyzeWithAzureOpenAI('historian', $blastResult, $pullRequest);
        if ($aiResult) {
            return $aiResult;
        }

        return $this->getMockHistorianResult($blastResult);
    }

    private function runNegotiator(PullRequest $pullRequest, array $riskResult): array
    {
        $url = config('services.agents.negotiator_url');

        try {
            if ($url) {
                $response = $this->agentHttp()->post($url, [
                    'risk_score' => $riskResult['risk_score'],
                    'risk_level' => $riskResult['risk_level'],
                    'repo_full_name' => $pullRequest->repo_full_name,
                    'pr_number' => $pullRequest->pr_number,
                    'recommendation' => $riskResult['recommendation'] ?? '',
                    'contributing_factors' => $riskResult['contributing_factors'] ?? [],
                    'summary' => $riskResult['match_summary'] ?? '',
                ]);

                if ($response->successful()) {
                    Log::info("[Agent:Negotiator] Got response for PR #{$pullRequest->pr_number}.");

                    return $response->json();
                }

                Log::warning('[Agent:Negotiator] Non-200 response.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[Agent:Negotiator] Agent call failed, using mock.', ['error' => $e->getMessage()]);
        }

        return $this->getMockNegotiatorResult($riskResult);
    }

    /**
     * Compute the Deployment Weather Score — environmental risk at deploy time.
     * Runs 5 independent checks and sums their points into a weather score (0-95).
     *
     * @param  array<string>  $affectedServices
     * @return array{total_score: int, checks: array, recommendation: string}
     */
    private function computeWeatherScore(PullRequest $pullRequest, PipelineConfig $config, array $affectedServices): array
    {
        $checks = [];
        $totalScore = 0;

        // --- Check 1: Concurrent Deploys (20 pts) ---
        $concurrentResult = $this->checkConcurrentDeploys($pullRequest, $affectedServices);
        $checks[] = $concurrentResult;
        if ($concurrentResult['fired']) {
            $totalScore += $concurrentResult['points'];
        }

        // --- Check 2: Active Incidents (30 pts) ---
        $incidentResult = $this->checkActiveIncidents($affectedServices);
        $checks[] = $incidentResult;
        if ($incidentResult['fired']) {
            $totalScore += $incidentResult['points'];
        }

        // --- Check 3: Infrastructure Health (20 pts) ---
        $infraResult = $this->checkInfrastructureHealth($affectedServices);
        $checks[] = $infraResult;
        if ($infraResult['fired']) {
            $totalScore += $infraResult['points'];
        }

        // --- Check 4: High Traffic Window (15 pts) ---
        $trafficResult = $this->checkHighTrafficWindow($config);
        $checks[] = $trafficResult;
        if ($trafficResult['fired']) {
            $totalScore += $trafficResult['points'];
        }

        // --- Check 5: Recent Related Deploy (10 pts) ---
        $recentResult = $this->checkRecentRelatedDeploy($pullRequest, $affectedServices);
        $checks[] = $recentResult;
        if ($recentResult['fired']) {
            $totalScore += $recentResult['points'];
        }

        $recommendation = $totalScore <= 10
            ? 'Now is a good time to deploy. All environmental checks are clear.'
            : ($totalScore <= 30
                ? 'Conditions are acceptable but not ideal. Proceed with monitoring.'
                : ($totalScore <= 50
                    ? 'Conditions are unfavorable — consider waiting for a better window.'
                    : 'Conditions are dangerous. Strongly recommend delaying this deployment.'));

        return [
            'total_score' => $totalScore,
            'checks' => $checks,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Check 1: Are there other deploys in progress to services in the blast radius?
     *
     * @param  array<string>  $affectedServices
     * @return array{name: string, label: string, fired: bool, points: int, detail: string, icon: string}
     */
    private function checkConcurrentDeploys(PullRequest $pullRequest, array $affectedServices): array
    {
        $fired = false;
        $detail = 'No concurrent deployments detected.';

        // Check for PRs recently approved/merged (within last 30 min) in same repo or overlapping services
        $recentDeploys = PullRequest::where('id', '!=', $pullRequest->id)
            ->where('repo_full_name', $pullRequest->repo_full_name)
            ->where('status', 'approved')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->with('blastRadius')
            ->get();

        $overlapping = [];
        foreach ($recentDeploys as $deploy) {
            $deployServices = $deploy->blastRadius?->affected_services ?? [];
            $overlap = array_intersect($affectedServices, $deployServices);
            if (! empty($overlap)) {
                $overlapping[] = "PR #{$deploy->pr_number} (".implode(', ', $overlap).')';
            }
        }

        if (! empty($overlapping)) {
            $fired = true;
            $detail = 'Concurrent deploys to overlapping services: '.implode('; ', $overlapping);
        } elseif ($recentDeploys->isNotEmpty()) {
            $detail = $recentDeploys->count().' recent deploy(s) in this repo but no service overlap.';
        }

        // Also check via GitHub API for recent workflow runs if token available
        try {
            $ghToken = config('services.github.token');
            if ($ghToken && ! $fired) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$ghToken}",
                    'Accept' => 'application/vnd.github.v3+json',
                ])->timeout(8)->get("https://api.github.com/repos/{$pullRequest->repo_full_name}/actions/runs", [
                    'status' => 'in_progress',
                    'per_page' => 5,
                ]);

                if ($response->successful()) {
                    $runs = $response->json('workflow_runs', []);
                    $inProgress = count($runs);
                    if ($inProgress > 0) {
                        $fired = true;
                        $detail = "{$inProgress} GitHub Actions workflow(s) currently in progress.";
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("[Weather] Could not check GitHub Actions: {$e->getMessage()}");
        }

        return [
            'name' => 'concurrent_deploys',
            'label' => 'Concurrent Deployments',
            'fired' => $fired,
            'points' => 20,
            'detail' => $detail,
            'icon' => 'sync',
        ];
    }

    /**
     * Check 2: Are there any active (unresolved) incidents affecting blast radius services?
     *
     * @param  array<string>  $affectedServices
     * @return array{name: string, label: string, fired: bool, points: int, detail: string, icon: string}
     */
    private function checkActiveIncidents(array $affectedServices): array
    {
        $fired = false;
        $detail = 'No active incidents.';

        $activeIncidents = Incident::whereNull('resolved_at')->get();

        $matchingIncidents = [];
        foreach ($activeIncidents as $incident) {
            $incidentServices = is_array($incident->affected_services) ? $incident->affected_services : [];
            $overlap = array_intersect($affectedServices, $incidentServices);
            if (! empty($overlap)) {
                $matchingIncidents[] = "{$incident->title} (P{$incident->severity}) — ".implode(', ', $overlap);
            }
        }

        if (! empty($matchingIncidents)) {
            $fired = true;
            $detail = count($matchingIncidents).' active incident(s) in blast radius: '.implode('; ', array_slice($matchingIncidents, 0, 3));
        } elseif ($activeIncidents->isNotEmpty()) {
            $detail = $activeIncidents->count().' active incident(s) but none overlap with this PR\'s services.';
        }

        return [
            'name' => 'active_incidents',
            'label' => 'Active Incidents',
            'fired' => $fired,
            'points' => 30,
            'detail' => $detail,
            'icon' => 'error',
        ];
    }

    /**
     * Check 3: Infrastructure health — error rates and latency from monitoring.
     * Falls back to DB-based heuristic when Azure Monitor is not available.
     *
     * @param  array<string>  $affectedServices
     * @return array{name: string, label: string, fired: bool, points: int, detail: string, icon: string}
     */
    private function checkInfrastructureHealth(array $affectedServices): array
    {
        $fired = false;
        $detail = 'Infrastructure health checks passed.';

        // Try Azure Application Insights if configured
        try {
            $appInsightsKey = config('services.app_insights.api_key');
            $appInsightsId = config('services.app_insights.app_id');

            if ($appInsightsKey && $appInsightsId) {
                $response = Http::withHeaders([
                    'x-api-key' => $appInsightsKey,
                ])->timeout(10)->get("https://api.applicationinsights.io/v1/apps/{$appInsightsId}/metrics/requests/failed", [
                    'timespan' => 'PT1H',
                ]);

                if ($response->successful()) {
                    $errorRate = $response->json('value.requests/failed.sum', 0);
                    if ($errorRate > 0) {
                        $fired = true;
                        $detail = "Application Insights reports {$errorRate} failed requests in the last hour.";

                        return [
                            'name' => 'infrastructure_health',
                            'label' => 'Infrastructure Health',
                            'fired' => $fired,
                            'points' => 20,
                            'detail' => $detail,
                            'icon' => 'monitor_heart',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug("[Weather] App Insights check failed: {$e->getMessage()}");
        }

        // Fallback: check recent incidents resolved in last 24h as a proxy for instability
        $recentResolved = Incident::whereNotNull('resolved_at')
            ->where('resolved_at', '>=', now()->subHours(24))
            ->get();

        $recentServiceIncidents = [];
        foreach ($recentResolved as $incident) {
            $incidentServices = is_array($incident->affected_services) ? $incident->affected_services : [];
            if (! empty(array_intersect($affectedServices, $incidentServices))) {
                $recentServiceIncidents[] = $incident;
            }
        }

        if (count($recentServiceIncidents) >= 2) {
            $fired = true;
            $detail = count($recentServiceIncidents).' incidents resolved in the last 24h for services in the blast radius — infrastructure may still be unstable.';
        } elseif (count($recentServiceIncidents) === 1) {
            $detail = '1 incident resolved recently for a service in the blast radius. Monitor closely.';
        }

        return [
            'name' => 'infrastructure_health',
            'label' => 'Infrastructure Health',
            'fired' => $fired,
            'points' => 20,
            'detail' => $detail,
            'icon' => 'monitor_heart',
        ];
    }

    /**
     * Check 4: Is the current time within a configured high-traffic window?
     *
     * @return array{name: string, label: string, fired: bool, points: int, detail: string, icon: string}
     */
    private function checkHighTrafficWindow(PipelineConfig $config): array
    {
        $fired = $config->isHighTrafficWindow();
        $detail = $fired
            ? 'Currently in a high-traffic window. Deployments during peak hours carry elevated risk.'
            : 'Outside of configured high-traffic windows.';

        // Add detail about which window
        if ($fired) {
            $now = now();
            $dayName = strtolower($now->format('l'));
            $currentHour = (int) $now->format('G');
            $windows = $config->high_traffic_windows ?? [];

            foreach ($windows as $window) {
                if (strtolower($window['day'] ?? '') === $dayName) {
                    $start = $window['start_hour'] ?? 0;
                    $end = $window['end_hour'] ?? 24;
                    if ($currentHour >= $start && $currentHour < $end) {
                        $label = $window['label'] ?? 'Peak Hours';
                        $detail = "Currently in \"{$label}\" window ({$dayName} {$start}:00–{$end}:00). Deployments during peak hours carry elevated risk.";
                        break;
                    }
                }
            }
        }

        return [
            'name' => 'high_traffic_window',
            'label' => 'High Traffic Window',
            'fired' => $fired,
            'points' => 15,
            'detail' => $detail,
            'icon' => 'trending_up',
        ];
    }

    /**
     * Check 5: Was another PR touching the same services merged in the last 2 hours?
     *
     * @param  array<string>  $affectedServices
     * @return array{name: string, label: string, fired: bool, points: int, detail: string, icon: string}
     */
    private function checkRecentRelatedDeploy(PullRequest $pullRequest, array $affectedServices): array
    {
        $fired = false;
        $detail = 'No recent related deployments in the last 2 hours.';

        $recentMerged = PullRequest::where('id', '!=', $pullRequest->id)
            ->where('repo_full_name', $pullRequest->repo_full_name)
            ->whereIn('status', ['approved', 'merged'])
            ->where('updated_at', '>=', now()->subHours(2))
            ->with('blastRadius')
            ->get();

        $overlapping = [];
        foreach ($recentMerged as $pr) {
            $prServices = $pr->blastRadius?->affected_services ?? [];
            $overlap = array_intersect($affectedServices, $prServices);
            if (! empty($overlap)) {
                $overlapping[] = "PR #{$pr->pr_number} (".implode(', ', $overlap).')';
            }
        }

        if (! empty($overlapping)) {
            $fired = true;
            $detail = 'Recent deploy(s) to same services in the last 2h: '.implode('; ', $overlapping).'. Stacked deploys increase blast radius correlation.';
        }

        return [
            'name' => 'recent_related_deploy',
            'label' => 'Recent Related Deploy',
            'fired' => $fired,
            'points' => 10,
            'detail' => $detail,
            'icon' => 'schedule',
        ];
    }

    /**
     * Send a Teams Adaptive Card notification for high-risk or blocked PRs.
     * Uses Microsoft Teams Incoming Webhook with an Adaptive Card payload.
     * Includes approve/block Action.OpenUrl buttons for human decision loop.
     */
    private function sendTeamsNotification(PullRequest $pullRequest, array $decisionResult, array $riskResult, array $blastResult, int $weatherScore): void
    {
        $webhookUrl = config('services.teams.webhook_url');
        $notifyThreshold = config('services.teams.notify_above_score', 60);

        if (! $webhookUrl) {
            Log::debug('[Teams] No webhook URL configured — skipping notification.');

            return;
        }

        $riskScore = $riskResult['risk_score'] ?? 50;
        $decision = $decisionResult['decision'] ?? 'pending_review';

        // Only notify when risk is above threshold or decision is blocked/pending
        if ($riskScore < $notifyThreshold && $decision === 'approved') {
            Log::debug("[Teams] Risk score {$riskScore} below threshold {$notifyThreshold} — skipping.");

            return;
        }

        // Generate HMAC decision tokens for approve/block callbacks
        $decisionRecord = DeploymentDecision::where('pull_request_id', $pullRequest->id)->first();
        $decisionId = $decisionRecord?->id ?? 0;
        $approveToken = hash_hmac('sha256', "decision-{$decisionId}", config('app.key'));
        $blockToken = $approveToken; // Same token since the HMAC is based on decision ID

        $appUrl = config('app.url');
        $approveUrl = "{$appUrl}/api/decisions/{$decisionId}/approve?token={$approveToken}";
        $blockUrl = "{$appUrl}/api/decisions/{$decisionId}/block?token={$blockToken}";
        $dashboardUrl = "{$appUrl}/driftwatch/pr/{$pullRequest->id}";

        // Determine accent color
        $accentColor = match (true) {
            $riskScore >= 75 => 'attention',
            $riskScore >= 50 => 'warning',
            default => 'good',
        };
        $hexColor = match (true) {
            $riskScore >= 75 => 'FF0000',
            $riskScore >= 50 => 'FFA500',
            default => '00CC00',
        };

        // Build affected services text
        $affectedServices = $blastResult['affected_services'] ?? [];
        $servicesText = ! empty($affectedServices)
            ? implode(', ', array_slice($affectedServices, 0, 5))
            : 'None identified';

        // Build top action items from contributing factors
        $factors = $riskResult['contributing_factors'] ?? [];
        $topFactors = array_slice($factors, 0, 2);
        $factorItems = [];
        foreach ($topFactors as $factor) {
            $factorItems[] = [
                'type' => 'TextBlock',
                'text' => "- {$factor}",
                'wrap' => true,
                'size' => 'small',
            ];
        }

        // Build the Adaptive Card
        $card = [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'msteams' => ['width' => 'Full'],
                        'body' => [
                            // Header
                            [
                                'type' => 'TextBlock',
                                'text' => 'DriftWatch Deploy Alert',
                                'weight' => 'bolder',
                                'size' => 'large',
                                'color' => $accentColor,
                            ],
                            // PR Info
                            [
                                'type' => 'ColumnSet',
                                'columns' => [
                                    [
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => [
                                            [
                                                'type' => 'TextBlock',
                                                'text' => "PR #{$pullRequest->pr_number}",
                                                'weight' => 'bolder',
                                                'size' => 'extraLarge',
                                                'color' => $accentColor,
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'Column',
                                        'width' => 'stretch',
                                        'items' => [
                                            [
                                                'type' => 'TextBlock',
                                                'text' => $pullRequest->pr_title,
                                                'weight' => 'bolder',
                                                'wrap' => true,
                                            ],
                                            [
                                                'type' => 'TextBlock',
                                                'text' => "{$pullRequest->repo_full_name} by {$pullRequest->pr_author}",
                                                'spacing' => 'none',
                                                'isSubtle' => true,
                                                'size' => 'small',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            // Risk Score
                            [
                                'type' => 'ColumnSet',
                                'columns' => [
                                    [
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => [
                                            [
                                                'type' => 'TextBlock',
                                                'text' => (string) $riskScore,
                                                'weight' => 'bolder',
                                                'size' => 'extraLarge',
                                                'color' => $accentColor,
                                            ],
                                            [
                                                'type' => 'TextBlock',
                                                'text' => 'Risk Score',
                                                'spacing' => 'none',
                                                'size' => 'small',
                                                'isSubtle' => true,
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => [
                                            [
                                                'type' => 'TextBlock',
                                                'text' => strtoupper($decision),
                                                'weight' => 'bolder',
                                                'size' => 'extraLarge',
                                                'color' => $decision === 'blocked' ? 'attention' : 'warning',
                                            ],
                                            [
                                                'type' => 'TextBlock',
                                                'text' => 'Decision',
                                                'spacing' => 'none',
                                                'size' => 'small',
                                                'isSubtle' => true,
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'Column',
                                        'width' => 'auto',
                                        'items' => [
                                            [
                                                'type' => 'TextBlock',
                                                'text' => (string) $weatherScore,
                                                'weight' => 'bolder',
                                                'size' => 'extraLarge',
                                                'color' => $weatherScore >= 40 ? 'attention' : ($weatherScore >= 20 ? 'warning' : 'good'),
                                            ],
                                            [
                                                'type' => 'TextBlock',
                                                'text' => 'Weather',
                                                'spacing' => 'none',
                                                'size' => 'small',
                                                'isSubtle' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            // Affected Services
                            [
                                'type' => 'FactSet',
                                'facts' => [
                                    ['title' => 'Affected Services', 'value' => $servicesText],
                                    ['title' => 'Files Changed', 'value' => (string) ($pullRequest->files_changed ?? 0)],
                                    ['title' => 'Blast Radius', 'value' => (string) ($blastResult['total_blast_radius_score'] ?? 0).'/100'],
                                ],
                            ],
                            // Action Items header
                            [
                                'type' => 'TextBlock',
                                'text' => 'Key Concerns:',
                                'weight' => 'bolder',
                                'spacing' => 'medium',
                            ],
                            // Action Items
                            ...$factorItems,
                        ],
                        'actions' => [
                            [
                                'type' => 'Action.OpenUrl',
                                'title' => 'APPROVE Deployment',
                                'url' => $approveUrl,
                                'style' => 'positive',
                            ],
                            [
                                'type' => 'Action.OpenUrl',
                                'title' => 'BLOCK Deployment',
                                'url' => $blockUrl,
                                'style' => 'destructive',
                            ],
                            [
                                'type' => 'Action.OpenUrl',
                                'title' => 'View in DriftWatch',
                                'url' => $dashboardUrl,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(10)->post($webhookUrl, $card);

            if ($response->successful()) {
                Log::info("[Teams] Notification sent for PR #{$pullRequest->pr_number}.", [
                    'risk_score' => $riskScore,
                    'decision' => $decision,
                ]);
            } else {
                Log::warning('[Teams] Notification failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("[Teams] Failed to send notification: {$e->getMessage()}");
        }
    }

    // --- Mock fallback data ---

    /**
     * Generate mock Archaeologist result with code-aware analysis.
     * When code context is available, incorporates actual file names and patch data.
     *
     * @param  array{diff_text: string, changed_files: array}  $codeContext
     */
    private function getMockArchaeologistResult(array $codeContext = []): array
    {
        $changedFiles = $codeContext['changed_files'] ?? [];
        $diffText = $codeContext['diff_text'] ?? '';

        // If we have real code context, build analysis from actual files
        if (! empty($changedFiles)) {
            $affectedFiles = [];
            $classifications = [];
            $services = [];
            $endpoints = [];
            $depGraph = [];
            $fileSummaries = [];
            $riskIndicators = [];
            $totalScore = 0;

            foreach ($changedFiles as $file) {
                $filename = $file['filename'];
                $affectedFiles[] = $filename;
                $patch = $file['patch'] ?? '';
                $fullContent = $file['full_file_content'] ?? null;
                $additions = $file['additions'] ?? 0;
                $deletions = $file['deletions'] ?? 0;
                $fileStatus = $file['status'] ?? 'modified'; // GitHub sends: added, removed, modified, renamed

                // === STEP 1: Detect file deletion — this is critical ===
                $isDeleted = $fileStatus === 'removed';
                $isPureDelete = $additions === 0 && $deletions > 0;
                $isRenamed = $fileStatus === 'renamed';
                $isAdded = $fileStatus === 'added';

                // === STEP 2: Check if this is a core framework file ===
                // Deleting or heavily modifying these files WILL break the application
                $isCoreFile = (bool) preg_match('/^(routes\/(web|api|console|channels)\.php|bootstrap\/(app|providers)\.php|config\/(app|database|services|auth)\.php|composer\.json|package\.json|artisan|\.env|public\/index\.php|app\/Http\/Kernel\.php|app\/Console\/Kernel\.php|app\/Providers\/(AppServiceProvider|RouteServiceProvider)\.php|manage\.py|settings\.py|urls\.py|wsgi\.py|asgi\.py|requirements\.txt|Gemfile|go\.mod|Cargo\.toml)$/i', $filename);

                // === STEP 3: Classify the change ===
                $changeType = 'general_change';
                $riskScore = 5;
                $reasoning = "Modified file with {$additions} additions and {$deletions} deletions.";

                // --- Core file deletion = CRITICAL (application will break) ---
                if ($isCoreFile && $isDeleted) {
                    $changeType = 'critical_file_deleted';
                    $riskScore = 50;
                    $reasoning = "CRITICAL: Core framework file DELETED. This WILL break the application. {$filename} is essential for the application to function.";
                    $riskIndicators[] = "CRITICAL: Core file deleted: {$filename} — application will not start";
                }
                // --- Any file deletion is more serious than a modification ---
                elseif ($isDeleted) {
                    $changeType = 'file_deleted';
                    $baseScore = $this->classifyFileRisk($filename);
                    $riskScore = min(50, (int) ($baseScore * 1.8)); // Deletions are ~2x riskier
                    $reasoning = "File DELETED ({$deletions} lines removed). Any code depending on this file will break. Downstream imports, routes, or references will throw errors.";
                    $riskIndicators[] = "File deleted: {$filename} ({$deletions} lines removed)";
                }
                // --- Pure deletion within a file (gutted the file) ---
                elseif ($isPureDelete && $deletions > 20) {
                    $changeType = 'code_gutted';
                    $baseScore = $this->classifyFileRisk($filename);
                    $riskScore = min(45, (int) ($baseScore * 1.5));
                    $reasoning = "File gutted — {$deletions} lines removed with 0 additions. This may remove critical functionality without replacement.";
                    $riskIndicators[] = "File gutted: {$filename} ({$deletions} lines removed, 0 added)";
                }
                // --- Core file modified (not deleted, but still high risk) ---
                elseif ($isCoreFile) {
                    $changeType = 'core_file_modified';
                    $riskScore = 30;
                    $reasoning = "Core framework file modified. Changes to {$filename} affect the entire application. Verify all functionality still works.";
                    $riskIndicators[] = "Core file modified: {$filename}";
                }
                // --- Standard classification by file type ---
                elseif (preg_match('/migration/i', $filename)) {
                    $changeType = 'sql_migration';
                    $riskScore = 25;
                    $reasoning = 'Database migration file — schema changes affect all services reading this data.';
                    $riskIndicators[] = "Database migration: {$filename}";
                } elseif (preg_match('/middleware|auth|guard|policy/i', $filename)) {
                    $changeType = 'auth_middleware';
                    $riskScore = 30;
                    $reasoning = 'Authentication/authorization change — impacts all protected routes.';
                    $riskIndicators[] = "Auth/middleware change: {$filename}";
                } elseif (preg_match('/config\//i', $filename)) {
                    $changeType = 'config_change';
                    $riskScore = 20;
                    $reasoning = 'Configuration file change — may affect application behavior globally.';
                    $riskIndicators[] = "Config change: {$filename}";
                } elseif (preg_match('/routes\//i', $filename)) {
                    $changeType = 'routing_change';
                    $riskScore = 20;
                    $reasoning = 'Route definition change — may add, modify, or remove API endpoints.';
                    if (preg_match_all("/Route::(get|post|put|delete|patch)\(['\"]([^'\"]+)/", $patch, $matches)) {
                        foreach ($matches[2] as $ep) {
                            $endpoints[] = $ep;
                        }
                    }
                } elseif (preg_match('/Controller\.php$/i', $filename)) {
                    $changeType = 'controller_change';
                    $riskScore = 15;
                    $reasoning = 'Controller logic changed — may alter API behavior or response format.';
                } elseif (preg_match('/Model\.php$/i', $filename)) {
                    $changeType = 'model_change';
                    $riskScore = 15;
                    $reasoning = 'Eloquent model changed — may affect data retrieval, relationships, or casts.';
                } elseif (preg_match('/Service\.php$/i', $filename)) {
                    $changeType = 'service_change';
                    $riskScore = 20;
                    $reasoning = 'Service class changed — business logic that may be called from multiple controllers.';
                } elseif (preg_match('/\.(blade\.php|css|scss)$/i', $filename)) {
                    $changeType = 'view_style_change';
                    $riskScore = 2;
                    $reasoning = 'View or stylesheet change — low risk, cosmetic only.';
                } elseif (preg_match('/test/i', $filename)) {
                    $changeType = 'test_change';
                    $riskScore = 2;
                    $reasoning = 'Test file change — does not affect production behavior.';
                }

                // === STEP 4: Analyze the actual diff for dangerous patterns ===
                if ($patch) {
                    // Check for function signature changes
                    if (preg_match('/^[-+]\s*(public|protected|private)\s+function\s+\w+\(/m', $patch)) {
                        if ($changeType === 'general_change') {
                            $changeType = 'function_signature_change';
                            $riskScore = max($riskScore, 20);
                        }
                        $reasoning .= ' Function signature modified — callers may need updates.';
                    }

                    // Check for env/secret patterns being removed or exposed
                    if (preg_match('/^[-+].*(password|secret|key|token|credential)/im', $patch)) {
                        $riskScore = max($riskScore, 25);
                        $reasoning .= ' Sensitive data pattern detected (password/secret/key).';
                        $riskIndicators[] = "Sensitive data pattern in: {$filename}";
                    }

                    // Check for large deletions within a modification
                    if (! $isDeleted && $deletions > 50 && $deletions > $additions * 3) {
                        $riskScore = max($riskScore, (int) ($riskScore * 1.3));
                        $reasoning .= " Heavy deletion ({$deletions} lines removed vs {$additions} added) — may remove important logic.";
                    }

                    // Check for removing error handling, try/catch, validation
                    if (preg_match('/^-\s*(try|catch|throw|validate|abort|assert)/m', $patch)) {
                        $riskScore = max($riskScore, 15);
                        $reasoning .= ' Error handling or validation code removed.';
                    }
                }

                // === STEP 5: Scale score by size for large changes ===
                $totalLines = $additions + $deletions;
                if ($totalLines > 500 && $riskScore >= 10) {
                    $riskScore = min(50, (int) ($riskScore * 1.2));
                    $reasoning .= " Large change ({$totalLines} total lines affected).";
                }

                // Build file summary from actual code
                $fileSummary = "File: {$filename} ({$file['status']})";
                if ($fullContent) {
                    // Extract class/function names for summary
                    if (preg_match('/class\s+(\w+)/', $fullContent, $classMatch)) {
                        $fileSummary = "Class {$classMatch[1]} in {$filename}";
                    }
                    // Count methods
                    $methodCount = preg_match_all('/function\s+\w+\(/', $fullContent);
                    $fileSummary .= " — {$methodCount} method(s)";
                }
                $fileSummaries[$filename] = $fileSummary;

                // Infer service from file path
                if (preg_match('/services?\/(\w+)/i', $filename, $svcMatch)) {
                    $services[] = strtolower($svcMatch[1]).'-service';
                } elseif (preg_match('/api\/|routes\/api/i', $filename)) {
                    $services[] = 'api-gateway';
                } elseif (preg_match('/payment|billing|invoice/i', $filename)) {
                    $services[] = 'payment-service';
                }

                // Build dependency graph from imports in full content
                if ($fullContent) {
                    $deps = [];
                    // PHP use statements
                    if (preg_match_all('/use\s+([A-Za-z\\\\]+);/', $fullContent, $useMatches)) {
                        foreach ($useMatches[1] as $use) {
                            $depFile = str_replace('\\', '/', $use).'.php';
                            $deps[] = $depFile;
                        }
                    }
                    // Python/JS imports
                    if (preg_match_all('/(?:from|import)\s+([a-zA-Z_.]+)/', $fullContent, $importMatches)) {
                        foreach ($importMatches[1] as $imp) {
                            $deps[] = str_replace('.', '/', $imp);
                        }
                    }
                    if (! empty($deps)) {
                        $depGraph[$filename] = array_slice($deps, 0, 10);
                    }
                }

                $totalScore += $riskScore;

                $classifications[] = [
                    'file' => $filename,
                    'change_type' => $changeType,
                    'risk_score' => $riskScore,
                    'reasoning' => $reasoning,
                    'full_file_read' => $fullContent !== null,
                    'lines_changed' => $additions + $deletions,
                ];
            }

            $services = array_values(array_unique($services));
            if (empty($services)) {
                $services = ['unknown-service'];
            }

            // Cap score at 100
            $totalScore = min($totalScore, 100);

            $fileCount = count($affectedFiles);
            $codeAnalysis = "Analyzed {$fileCount} changed file(s) with actual source code. ";
            $highRisk = collect($classifications)->where('risk_score', '>=', 15)->count();
            $codeAnalysis .= "{$highRisk} file(s) flagged as elevated risk. ";
            if (! empty($diffText)) {
                $diffLines = substr_count($diffText, "\n");
                $codeAnalysis .= "Total diff: ~{$diffLines} lines.";
            }

            return [
                'status' => 'scored',
                'affected_files' => $affectedFiles,
                'affected_services' => $services,
                'affected_endpoints' => $endpoints,
                'dependency_graph' => $depGraph,
                'change_classifications' => $classifications,
                'total_blast_radius_score' => $totalScore,
                'total_affected_files' => $fileCount,
                'total_affected_services' => count($services),
                'risk_indicators' => $riskIndicators,
                'ci_status' => 'unknown',
                'failing_checks' => [],
                'ci_risk_addition' => 0,
                'bot_findings' => [],
                'bot_risk_addition' => 0,
                'file_summaries' => $fileSummaries,
                'code_analysis' => $codeAnalysis,
                'summary' => $codeAnalysis,
            ];
        }

        // Fallback: no code context available — use static mock data
        return [
            'status' => 'scored',
            'affected_files' => [
                'src/services/payment/calculator.py',
                'src/api/routes/billing.py',
                'src/utils/rate_helper.py',
            ],
            'affected_services' => [
                'payment-service',
                'billing-api',
                'notification-service',
                'invoice-generator',
            ],
            'affected_endpoints' => [
                '/api/v1/billing/calculate',
                '/api/v1/payments/process',
                '/api/v1/invoices/generate',
            ],
            'dependency_graph' => [
                'src/services/payment/calculator.py' => [
                    'src/api/routes/billing.py',
                    'src/services/notification/sender.py',
                    'src/workers/invoice_generator.py',
                ],
            ],
            'change_classifications' => [
                [
                    'file' => 'src/services/payment/calculator.py',
                    'change_type' => 'changed_function_signature',
                    'risk_score' => 20,
                    'reasoning' => 'Modified calculate_rate() function — changed parameters from (amount, currency) to (amount, currency, discount_tier). All callers must be updated.',
                    'full_file_read' => true,
                    'lines_changed' => 45,
                ],
                [
                    'file' => 'src/api/routes/billing.py',
                    'change_type' => 'new_api_endpoint',
                    'risk_score' => 15,
                    'reasoning' => 'Added POST /api/v1/billing/calculate-discount endpoint. New route handler calls calculator.calculate_rate() with the new discount_tier parameter.',
                    'full_file_read' => false,
                    'lines_changed' => 28,
                ],
                [
                    'file' => 'src/utils/rate_helper.py',
                    'change_type' => 'shared_utility_change',
                    'risk_score' => 25,
                    'reasoning' => 'rate_helper.get_base_rate() modified to accept optional discount_tier. This utility is imported by 4 modules: calculator.py, billing.py, invoice_generator.py, and analytics_worker.py.',
                    'full_file_read' => true,
                    'lines_changed' => 32,
                ],
            ],
            'total_blast_radius_score' => 60,
            'total_affected_files' => 3,
            'total_affected_services' => 4,
            'risk_indicators' => [
                'Payment calculation logic modified — calculate_rate() signature changed',
                'Shared utility rate_helper.get_base_rate() changed — imported by 4 modules',
                'New API endpoint added to billing routes',
                'Multiple downstream services affected: billing-api, notification-service, invoice-generator',
            ],
            'ci_status' => 'passing',
            'failing_checks' => [],
            'ci_risk_addition' => 0,
            'bot_findings' => [],
            'bot_risk_addition' => 0,
            'file_summaries' => [
                'src/services/payment/calculator.py' => 'Class PaymentCalculator — core billing logic with calculate_rate(), apply_discount(), and validate_currency() methods. 156 lines.',
                'src/api/routes/billing.py' => 'FastAPI router with 5 billing endpoints. Imports PaymentCalculator and RateHelper. Handles request validation and response serialization.',
                'src/utils/rate_helper.py' => 'Shared utility module with get_base_rate(), convert_currency(), and format_amount(). Imported by 4 other modules across payment and billing services.',
            ],
            'code_analysis' => 'Analyzed 3 changed files with full source code. The PR modifies the PaymentCalculator.calculate_rate() function signature to add a discount_tier parameter, updates the shared rate_helper utility, and adds a new billing API endpoint. The function signature change is the highest risk because all existing callers of calculate_rate() must pass the new parameter. The rate_helper change cascades to 4 importing modules.',
            'summary' => 'This PR modifies the payment rate calculator which is used by the billing API, notification service, and invoice generator. The shared rate_helper utility is imported by 4 modules, making this a wide-blast-radius change. Code analysis shows the calculate_rate() function signature was changed (new discount_tier param) and all callers need updating. Score: 60/100.',
        ];
    }

    private function getMockHistorianResult(array $blastResult = []): array
    {
        // --- Dynamic scoring from actual blast radius data ---
        $blastScore = $blastResult['total_blast_radius_score'] ?? 0;
        $classifications = $blastResult['change_classifications'] ?? [];
        $affectedFiles = $blastResult['affected_files'] ?? [];
        $affectedServices = $blastResult['affected_services'] ?? [];
        $riskIndicators = $blastResult['risk_indicators'] ?? [];
        $ciRisk = $blastResult['ci_risk_addition'] ?? 0;
        $botRisk = $blastResult['bot_risk_addition'] ?? 0;

        // --- Look up real incidents from DB that match affected services/files ---
        $incidents = [];
        $historyScore = 0;
        $fileMatches = 0;
        $serviceMatches = 0;
        $changeTypeMatches = 0;

        try {
            $query = \App\Models\Incident::where('occurred_at', '>=', now()->subDays(90));

            // Match by affected services (JSON column) and file paths
            if (! empty($affectedServices) || ! empty($affectedFiles)) {
                $query->where(function ($q) use ($affectedServices, $affectedFiles) {
                    foreach ($affectedServices as $svc) {
                        $q->orWhere('affected_services', 'LIKE', "%{$svc}%");
                    }
                    foreach ($affectedFiles as $file) {
                        $basename = basename($file);
                        $q->orWhere('description', 'LIKE', "%{$basename}%")
                          ->orWhere('affected_files', 'LIKE', "%{$basename}%")
                          ->orWhere('root_cause_file', 'LIKE', "%{$basename}%");
                    }
                });
            }

            $dbIncidents = $query->orderByDesc('severity')->limit(10)->get();

            foreach ($dbIncidents as $inc) {
                $matchType = 'general';
                $matchScore = 5;

                $incServices = is_array($inc->affected_services) ? implode(', ', $inc->affected_services) : ($inc->affected_services ?? '');

                // Check file match
                foreach ($affectedFiles as $file) {
                    $bn = basename($file);
                    if (stripos($inc->description ?? '', $bn) !== false || stripos($inc->root_cause_file ?? '', $bn) !== false) {
                        $matchType = 'file';
                        $matchScore = 25;
                        $fileMatches++;
                        break;
                    }
                }

                // Check service match
                if ($matchType !== 'file') {
                    foreach ($affectedServices as $svc) {
                        if (stripos($incServices, $svc) !== false) {
                            $matchType = 'service';
                            $matchScore = 10;
                            $serviceMatches++;
                            break;
                        }
                    }
                }

                $daysAgo = $inc->occurred_at ? (int) now()->diffInDays($inc->occurred_at) : 0;

                $incidents[] = [
                    'id' => 'INC-' . str_pad($inc->id, 3, '0', STR_PAD_LEFT),
                    'title' => $inc->title,
                    'severity' => $inc->severity,
                    'days_ago' => $daysAgo,
                    'relevance' => $matchType === 'file'
                        ? "Direct file match — {$incServices}"
                        : "Same service area — {$incServices}",
                    'match_type' => $matchType,
                    'match_score' => $matchScore,
                ];

                $historyScore += $matchScore;
            }
        } catch (\Exception $e) {
            Log::debug('[MockHistorian] DB incident lookup failed: ' . $e->getMessage());
        }

        // Cap history score
        $historyScore = min($historyScore, 40);

        // --- Compute risk score: blast_radius + history + CI/bot ---
        $riskScore = min(100, $blastScore + $historyScore + $ciRisk + $botRisk);

        // --- Determine risk level ---
        $riskLevel = match (true) {
            $riskScore >= 75 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 25 => 'medium',
            default => 'low',
        };

        // --- Build contributing factors from actual data ---
        $factors = [];

        // Describe what the PR actually does based on classifications
        $criticalChanges = collect($classifications)->where('risk_score', '>=', 20);
        $moderateChanges = collect($classifications)->whereBetween('risk_score', [10, 19]);

        foreach ($criticalChanges as $c) {
            $typeLabel = str_replace('_', ' ', $c['change_type']);
            $factors[] = "Critical change: {$c['file']} — {$typeLabel} (risk +{$c['risk_score']})";
        }

        if ($moderateChanges->count() > 0) {
            $factors[] = $moderateChanges->count() . ' moderate-risk file(s) changed';
        }

        if (count($incidents) > 0) {
            $p1Count = collect($incidents)->where('severity', 1)->count();
            if ($p1Count > 0) {
                $factors[] = "{$p1Count} P1 incident(s) found in the same area within 90 days";
            } else {
                $factors[] = count($incidents) . ' related incident(s) found in 90-day history';
            }
        }

        if ($ciRisk > 0) {
            $factors[] = "CI checks failing — adds +{$ciRisk} risk points";
        }

        if ($botRisk > 0) {
            $factors[] = "Security bot findings detected — adds +{$botRisk} risk points";
        }

        if (count($affectedServices) > 1) {
            $factors[] = count($affectedServices) . ' services in blast radius: ' . implode(', ', array_slice($affectedServices, 0, 4));
        }

        if (count($affectedFiles) > 10) {
            $factors[] = 'Large PR with ' . count($affectedFiles) . ' files changed — wide blast radius';
        }

        // If nothing notable, say so
        if (empty($factors)) {
            $factors[] = 'No critical patterns detected in this change';
            $factors[] = 'No matching historical incidents found';
        }

        // --- Build recommendation ---
        if ($riskScore >= 75) {
            $recommendation = 'BLOCK: This PR carries critical risk. ' . implode('. ', array_slice($factors, 0, 2)) . '. Deploy only after thorough review and with a rollback plan ready.';
        } elseif ($riskScore >= 50) {
            $recommendation = 'CAUTION: Elevated risk detected. ' . implode('. ', array_slice($factors, 0, 2)) . '. Consider deploying during a low-traffic window with enhanced monitoring.';
        } elseif ($riskScore >= 25) {
            $recommendation = 'MODERATE: Some risk factors present. Standard review process recommended. ' . ($factors[0] ?? '');
        } else {
            $recommendation = 'LOW RISK: This PR looks safe to deploy. No significant risk patterns found. Standard deployment process is fine.';
        }

        return [
            'status' => 'scored',
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'historical_incidents' => $incidents,
            'match_summary' => [
                'file_matches' => $fileMatches,
                'service_matches' => $serviceMatches,
                'change_type_matches' => $changeTypeMatches,
                'history_score' => $historyScore,
                'blast_radius_score' => $blastScore,
                'ci_risk_addition' => $ciRisk,
                'bot_risk_addition' => $botRisk,
            ],
            'contributing_factors' => $factors,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Classify file risk by path pattern — used for deletion/gutting score scaling.
     */
    private function classifyFileRisk(string $filename): int
    {
        return match (true) {
            (bool) preg_match('/routes\/(web|api)\.php/i', $filename) => 35,
            (bool) preg_match('/migration/i', $filename) => 25,
            (bool) preg_match('/middleware|auth|guard|policy/i', $filename) => 30,
            (bool) preg_match('/config\//i', $filename) => 20,
            (bool) preg_match('/Controller\.php$/i', $filename) => 15,
            (bool) preg_match('/Model\.php$/i', $filename) => 15,
            (bool) preg_match('/Service\.php$/i', $filename) => 20,
            (bool) preg_match('/routes\//i', $filename) => 20,
            (bool) preg_match('/\.blade\.php|\.css|\.scss$/i', $filename) => 2,
            (bool) preg_match('/test/i', $filename) => 2,
            default => 10,
        };
    }

    private function getMockNegotiatorResult(array $riskResult): array
    {
        $score = $riskResult['risk_score'] ?? 50;

        if ($score >= 75) {
            $decision = 'blocked';
        } elseif ($score >= 50) {
            $decision = 'pending_review';
        } else {
            $decision = 'approved';
        }

        $factors = $riskResult['contributing_factors'] ?? [];
        $recommendation = $riskResult['recommendation'] ?? '';

        // Build a code-aware PR comment
        $prComment = "### Deployment Risk Assessment\n\n";
        $prComment .= "**Risk Score: {$score}/100** | **Decision: ".strtoupper($decision)."**\n\n";
        $prComment .= "---\n\n";
        $prComment .= "#### Code Analysis Summary\n\n";

        if (! empty($factors)) {
            foreach ($factors as $factor) {
                $prComment .= "- {$factor}\n";
            }
            $prComment .= "\n";
        }

        if ($recommendation) {
            $prComment .= "#### Recommendation\n\n{$recommendation}\n\n";
        }

        if ($score >= 75) {
            $prComment .= "**This deployment has been automatically blocked.** The risk score exceeds the safety threshold. Please address the identified concerns and re-submit for analysis.\n";
        } elseif ($score >= 50) {
            $prComment .= "**Manual review required.** The risk score indicates elevated deployment risk. A team lead or on-call engineer should review the blast radius and incident history before approving.\n";
        } else {
            $prComment .= "**Approved for deployment.** The risk score is within acceptable thresholds. Standard monitoring is recommended post-deploy.\n";
        }

        $prComment .= "\n---\n*Generated by DriftWatch AI Pipeline*";

        return [
            'decision' => $decision,
            'decided_by' => $score >= 50 ? null : 'DriftWatch AI',
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => $score >= 50,
            'notification_message' => $score >= 50
                ? "Risk score {$score}/100. ".($score >= 75 ? 'Blocked automatically.' : 'Review recommended.')
                : null,
            'decided_at' => $score < 50 ? now() : null,
            'pr_comment' => $prComment,
        ];
    }

    /**
     * Run the Chronicler agent (feedback loop — records predictions for later validation).
     *
     * @return array<string, mixed>
     */
    private function runChronicler(PullRequest $pullRequest, array $riskResult, array $blastResult): array
    {
        $startMs = round(microtime(true) * 1000);

        $agentUrl = config('services.agents.chronicler_url');
        $functionKey = config('services.agents.function_key');

        if ($agentUrl) {
            try {
                $response = Http::timeout(30)->withHeaders([
                    'x-functions-key' => $functionKey ?? '',
                ])->post($agentUrl, [
                    'pr_number' => $pullRequest->pr_number,
                    'repo' => $pullRequest->repo_full_name,
                    'predicted_risk_score' => $riskResult['risk_score'] ?? 0,
                    'predicted_risk_level' => $riskResult['risk_level'] ?? 'unknown',
                    'affected_services' => $blastResult['affected_services'] ?? [],
                    'affected_files_count' => $blastResult['total_affected_files'] ?? 0,
                    'decision' => $pullRequest->deploymentDecision?->decision ?? 'unknown',
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $result['duration_ms'] = round(microtime(true) * 1000) - $startMs;

                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('[Chronicler] Azure Function failed, using mock.', ['error' => $e->getMessage()]);
            }
        }

        return $this->getMockChroniclerResult($pullRequest, $riskResult, $blastResult, $startMs);
    }

    /**
     * Mock Chronicler result — records the pipeline's predictions for future feedback loop.
     *
     * @return array<string, mixed>
     */
    private function getMockChroniclerResult(PullRequest $pullRequest, array $riskResult, array $blastResult, float $startMs): array
    {
        $score = $riskResult['risk_score'] ?? 0;
        $decision = $pullRequest->deploymentDecision?->decision ?? 'unknown';

        return [
            'status' => 'scored',
            'predicted_risk_score' => $score,
            'incident_occurred' => false,
            'actual_severity' => null,
            'actual_affected_services' => [],
            'prediction_accurate' => true,
            'post_mortem_notes' => 'Pipeline predicted risk score of '.$score.'/100 ('.($riskResult['risk_level'] ?? 'unknown').'). '
                .'Decision: '.$decision.'. '
                .'Affecting '.($blastResult['total_affected_files'] ?? 0).' files across '
                .($blastResult['total_affected_services'] ?? 0).' services. '
                .'Feedback will be validated after deployment outcome is recorded.',
            'duration_ms' => round(microtime(true) * 1000) - $startMs,
        ];
    }

    /**
     * Generate a smart local mock response for Impact Chat when no AI backend is available.
     * Matches the user's query against the PR context to return relevant answers.
     *
     * @param  array<string, mixed>  $prContext
     * @return array<string, mixed>
     */
    public function generateImpactChatMock(string $query, array $prContext): array
    {
        $q = strtolower($query);
        $files = $prContext['changed_files'] ?? [];
        $depGraph = $prContext['dependency_graph'] ?? [];
        $services = $prContext['affected_services'] ?? [];
        $fileDescs = $prContext['file_summaries'] ?? [];
        $riskScore = $prContext['risk_score'] ?? 0;
        $riskLevel = $prContext['risk_level'] ?? 'unknown';
        $filesChanged = $prContext['files_changed'] ?? 0;
        $highlights = [];
        $followups = ['What files have the highest risk?', 'Which services are affected?', 'Show me the dependency chain'];

        // Sort files by risk score
        $sortedFiles = collect($files)->filter(fn ($f) => is_array($f))->sortByDesc('risk_score')->values();

        // --- Query matching ---
        if (preg_match('/high.*risk|riski|danger|critical|most.*risk/i', $q)) {
            $topFiles = $sortedFiles->take(5);
            $fileList = $topFiles->map(fn ($f) => "**{$f['file']}** — {$f['risk_score']} pts ({$f['change_type']})")->implode("\n");
            $highlights = $topFiles->pluck('file')->toArray();

            return [
                'response' => "Here are the highest risk files in this PR:\n\n{$fileList}\n\nThe overall risk score is **{$riskScore}/100** ({$riskLevel}).",
                'highlight_nodes' => $highlights,
                'suggested_followups' => ['Why is the top file risky?', 'What depends on these files?', 'Show affected services'],
            ];
        }

        if (preg_match('/service|affect.*service|impact.*service/i', $q)) {
            $svcList = count($services) > 0 ? implode(', ', $services) : 'No services identified';

            return [
                'response' => 'This PR affects **'.count($services)." service(s)**: {$svcList}.\n\nThese were identified by the Archaeologist agent based on the dependency graph and file path analysis.",
                'highlight_nodes' => [],
                'suggested_followups' => ['What files belong to each service?', 'Show the highest risk files', 'Explain the risk score'],
            ];
        }

        if (preg_match('/depend|chain|upstream|downstream|what.*depend/i', $q)) {
            $depLines = [];
            foreach ($depGraph as $src => $deps) {
                if (is_array($deps) && count($deps) > 0) {
                    $depLines[] = "**{$src}** → ".implode(', ', array_slice($deps, 0, 5));
                    $highlights[] = $src;
                }
            }
            $depText = count($depLines) > 0 ? implode("\n", array_slice($depLines, 0, 8)) : 'No dependency chains detected.';

            return [
                'response' => "Here's the dependency graph for this PR:\n\n{$depText}\n\nClick any file card to highlight its full dependency path in the tree.",
                'highlight_nodes' => array_slice($highlights, 0, 10),
                'suggested_followups' => ['Which file has the most dependencies?', 'Show the highest risk files', 'Summarize the blast radius'],
            ];
        }

        if (preg_match('/summar|overview|tell me about|what.*pr|explain/i', $q)) {
            $summary = $prContext['blast_summary'] ?: "This PR changes {$filesChanged} files.";
            $rec = $prContext['recommendation'] ?: '';

            return [
                'response' => "**PR #{$prContext['pr_number']}**: {$prContext['pr_title']}\n\n{$summary}\n\nRisk: **{$riskScore}/100** ({$riskLevel}). "
                    .($rec ? "\n\nRecommendation: {$rec}" : ''),
                'highlight_nodes' => [],
                'suggested_followups' => ['Show me the highest risk files', 'What services are affected?', 'Explain the dependency chain'],
            ];
        }

        if (preg_match('/auth|middleware|security|login|permission/i', $q)) {
            $authFiles = $sortedFiles->filter(fn ($f) => preg_match('/auth|middleware|security|login|permission|guard|policy/i', $f['file'] ?? ''));
            if ($authFiles->isEmpty()) {
                return [
                    'response' => 'No authentication or middleware files were changed in this PR.',
                    'highlight_nodes' => [],
                    'suggested_followups' => ['What files were changed?', 'Show the highest risk files'],
                ];
            }
            $fileList = $authFiles->map(fn ($f) => "**{$f['file']}** — {$f['risk_score']} pts")->implode("\n");

            return [
                'response' => "Auth/middleware files in this PR:\n\n{$fileList}\n\nThese files affect authentication and access control — review carefully.",
                'highlight_nodes' => $authFiles->pluck('file')->toArray(),
                'suggested_followups' => ['Why are these risky?', 'What depends on these files?'],
            ];
        }

        if (preg_match('/migrat|database|schema|table/i', $q)) {
            $dbFiles = $sortedFiles->filter(fn ($f) => preg_match('/migrat|schema|database|\.sql/i', $f['file'] ?? ''));
            if ($dbFiles->isEmpty()) {
                return [
                    'response' => 'No database migration files were detected in this PR.',
                    'highlight_nodes' => [],
                    'suggested_followups' => ['What files were changed?', 'Show the highest risk files'],
                ];
            }
            $fileList = $dbFiles->map(fn ($f) => "**{$f['file']}** — {$f['risk_score']} pts")->implode("\n");

            return [
                'response' => "Database migration files:\n\n{$fileList}\n\nMigrations are high-risk — they modify the schema and can't be easily rolled back in production.",
                'highlight_nodes' => $dbFiles->pluck('file')->toArray(),
                'suggested_followups' => ['What services depend on the database?', 'Show the risk breakdown'],
            ];
        }

        // Code snippet analysis — when user sends code via "Send to Chat"
        if (preg_match('/analyze this code|code snippet|potential issues|what are the|```/i', $q)) {
            // Extract file name from query
            $snippetFile = '';
            if (preg_match('/from\s+(\S+\.?\w+)/i', $query, $snipMatch)) {
                $snippetFile = rtrim($snipMatch[1], ':');
            }

            // Extract the code block
            $codeBlock = '';
            if (preg_match('/```\s*([\s\S]*?)\s*```/s', $query, $codeMatch)) {
                $codeBlock = trim($codeMatch[1]);
            }

            // Find matching file in the PR's changed files
            $matchedFile = $sortedFiles->first(function ($f) use ($snippetFile) {
                $fname = strtolower($f['file'] ?? '');

                return str_contains($fname, strtolower($snippetFile)) || strtolower(basename($fname)) === strtolower($snippetFile);
            });

            // Analyze the code snippet for patterns
            $issues = [];
            $riskFlags = [];
            if (! empty($codeBlock)) {
                // Check for common patterns
                if (preg_match('/import\s+.*from/i', $codeBlock)) {
                    $importCount = preg_match_all('/import\s+/i', $codeBlock);
                    $issues[] = "**{$importCount} import(s)** detected — this file has external dependencies that could cascade if broken.";
                }
                if (preg_match('/async|await|Promise|setTimeout/i', $codeBlock)) {
                    $issues[] = 'Contains **async/concurrency** patterns — check for race conditions and proper error handling.';
                }
                if (preg_match('/catch|try|throw|Error/i', $codeBlock)) {
                    $issues[] = 'Has **error handling** — verify catch blocks handle all edge cases.';
                }
                if (preg_match('/password|secret|token|api[_-]?key|credential/i', $codeBlock)) {
                    $riskFlags[] = 'Possible **sensitive data** references (secrets/tokens/credentials).';
                }
                if (preg_match('/eval\s*\(|exec\s*\(|innerHTML|dangerouslySetInnerHTML/i', $codeBlock)) {
                    $riskFlags[] = 'Potential **injection vulnerability** (eval/exec/innerHTML usage).';
                }
                if (preg_match('/console\.\s*log|print\s*\(|debugger/i', $codeBlock)) {
                    $issues[] = 'Contains **debug statements** that should be removed before production.';
                }
                if (preg_match('/TODO|FIXME|HACK|XXX/i', $codeBlock)) {
                    $issues[] = 'Has **TODO/FIXME** markers — unfinished work that may need attention.';
                }
                if (preg_match('/interface|type\s+\w+\s*=/i', $codeBlock)) {
                    $issues[] = 'Defines **types/interfaces** — changes here affect all implementors.';
                }
                if (preg_match('/export\s+(default|class|function|const)/i', $codeBlock)) {
                    $issues[] = 'Has **public exports** — changes to the API surface may break consumers.';
                }
                if (preg_match('/config|env|process\.env|\.env/i', $codeBlock)) {
                    $issues[] = 'References **configuration/environment** — verify env vars are set in all deployment targets.';
                }
            }

            $response = '';
            if ($snippetFile) {
                $response .= "**Code analysis for `{$snippetFile}`:**\n\n";
            } else {
                $response .= "**Code snippet analysis:**\n\n";
            }

            if ($matchedFile) {
                $response .= "This file has a risk score of **{$matchedFile['risk_score']} pts** (_{$matchedFile['change_type']}_).\n\n";
                $highlights = [$matchedFile['file']];
            }

            if (! empty($riskFlags)) {
                $response .= "**Security Concerns:**\n".implode("\n", array_map(fn ($r) => "- {$r}", $riskFlags))."\n\n";
            }

            if (! empty($issues)) {
                $response .= "**Observations:**\n".implode("\n", array_map(fn ($i) => "- {$i}", $issues))."\n\n";
            }

            if (empty($issues) && empty($riskFlags)) {
                $response .= "No obvious issues detected in this snippet. The code appears to follow standard patterns.\n\n";
            }

            $response .= "Overall PR risk: **{$riskScore}/100** ({$riskLevel}).";

            return [
                'response' => $response,
                'highlight_nodes' => $highlights ?? [],
                'suggested_followups' => ['What depends on this file?', 'Show the highest risk files', 'Explain the full blast radius'],
            ];
        }

        // Search for specific file mentions
        $matchedFiles = $sortedFiles->filter(function ($f) use ($q) {
            $fname = strtolower($f['file'] ?? '');
            $shortName = strtolower(basename($fname));
            $words = preg_split('/\s+/', $q);
            foreach ($words as $w) {
                if (strlen($w) > 2 && (str_contains($fname, $w) || str_contains($shortName, $w))) {
                    return true;
                }
            }

            return false;
        });

        if ($matchedFiles->isNotEmpty()) {
            $first = $matchedFiles->first();
            $desc = $fileDescs[$first['file']] ?? null;
            $depList = $depGraph[$first['file']] ?? [];
            $response = "**{$first['file']}** — Risk score: {$first['risk_score']} pts ({$first['change_type']}).";
            if ($desc && is_array($desc)) {
                $response .= "\n\n".($desc['summary'] ?? '');
                if (! empty($desc['risk'])) {
                    $response .= "\n\nRisk: {$desc['risk']}";
                }
            }
            if (count($depList) > 0) {
                $response .= "\n\nDependencies: ".implode(', ', array_slice($depList, 0, 5));
            }

            return [
                'response' => $response,
                'highlight_nodes' => $matchedFiles->pluck('file')->toArray(),
                'suggested_followups' => ['What depends on this file?', 'Show related services', 'Show the highest risk files'],
            ];
        }

        // Default fallback
        return [
            'response' => "This PR (#{$prContext['pr_number']}) changes **{$filesChanged} files** with a risk score of **{$riskScore}/100** ({$riskLevel}). "
                .'Try asking about specific files, services, dependencies, or risk levels.',
            'highlight_nodes' => [],
            'suggested_followups' => $followups,
        ];
    }
}
