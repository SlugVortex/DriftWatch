<?php
// app/Http/Controllers/GitHubWebhookController.php
// Receives GitHub webhook events for pull_request.opened and pull_request.synchronize.
// Creates PullRequest records and dispatches the agent pipeline.
// Records AgentRun entries for every agent invocation with timing, cost, and score data.

namespace App\Http\Controllers;

use App\Models\AgentRun;
use App\Models\BlastRadiusResult;
use App\Models\DeploymentDecision;
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
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('[GitHubWebhook] Invalid signature. Rejecting request.');
                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        if ($event !== 'pull_request') {
            return response()->json(['message' => 'Event ignored', 'event' => $event], 200);
        }

        $action = $payload['action'] ?? '';
        if (!in_array($action, ['opened', 'synchronize', 'reopened'])) {
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
     * Run the 4-agent pipeline with mock fallbacks.
     * Records AgentRun entries for each agent invocation.
     * Generates a Briefing Pack before analysis and an MRP after the Negotiator.
     * Passes enriched data between agents (blast_radius_score, change_classifications, CI data).
     */
    private function runAgentPipeline(PullRequest $pullRequest): void
    {
        // Clear previous agent runs for this PR (re-analysis)
        AgentRun::where('pull_request_id', $pullRequest->id)->delete();

        // Generate Briefing Pack — structured input shared across all agents
        $briefingPack = [
            'briefing_id' => "PR-{$pullRequest->pr_number}-" . now()->timestamp,
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

        Log::info("[GitHubWebhook] Briefing Pack generated.", ['briefing_id' => $briefingPack['briefing_id']]);

        // Agent 1: Archaeologist (Blast Radius)
        $archaeologistStart = microtime(true);
        $blastResult = $this->runArchaeologist($pullRequest);
        $archaeologistDuration = (int) ((microtime(true) - $archaeologistStart) * 1000);

        BlastRadiusResult::updateOrCreate(
            ['pull_request_id' => $pullRequest->id],
            [
                'affected_files' => $blastResult['affected_files'] ?? [],
                'affected_services' => $blastResult['affected_services'] ?? [],
                'affected_endpoints' => $blastResult['affected_endpoints'] ?? [],
                'dependency_graph' => $blastResult['dependency_graph'] ?? [],
                'total_affected_files' => $blastResult['total_affected_files'] ?? count($blastResult['affected_files'] ?? []),
                'total_affected_services' => $blastResult['total_affected_services'] ?? count($blastResult['affected_services'] ?? []),
                'summary' => $blastResult['summary'] ?? 'Analysis complete.',
            ]
        );

        // Enrich briefing pack with Archaeologist findings
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

        $this->recordAgentRun($pullRequest, 'archaeologist', $blastResult, $archaeologistDuration, $briefingPack);

        // Agent 2: Historian (Risk Assessment) — pass enriched blast radius data
        $historianStart = microtime(true);
        $riskResult = $this->runHistorian($pullRequest, $blastResult);
        $historianDuration = (int) ((microtime(true) - $historianStart) * 1000);

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

        // Enrich briefing pack with Historian findings
        $briefingPack['historian'] = [
            'status' => $riskResult['status'] ?? 'scored',
            'risk_score' => $riskResult['risk_score'] ?? 0,
            'risk_level' => $riskResult['risk_level'] ?? 'unknown',
            'historical_incidents' => $riskResult['historical_incidents'] ?? [],
            'contributing_factors' => $riskResult['contributing_factors'] ?? [],
            'recommendation' => $riskResult['recommendation'] ?? '',
        ];

        $this->recordAgentRun($pullRequest, 'historian', $riskResult, $historianDuration, $briefingPack);

        // Agent 3: Negotiator (Deployment Decision)
        $negotiatorStart = microtime(true);
        $decisionResult = $this->runNegotiator($pullRequest, $riskResult);
        $negotiatorDuration = (int) ((microtime(true) - $negotiatorStart) * 1000);

        // Determine MRP version (increment if re-analyzing)
        $existingDecision = DeploymentDecision::where('pull_request_id', $pullRequest->id)->first();
        $mrpVersion = $existingDecision ? ($existingDecision->mrp_version ?? 0) + 1 : 1;

        // Generate Merge-Readiness Pack (MRP)
        $riskScore = $riskResult['risk_score'] ?? 50;
        $mrpPayload = [
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
                'decided_at' => $decisionResult['decided_at'] ?? null,
            ]
        );

        // Enrich briefing pack with Negotiator findings
        $briefingPack['negotiator'] = [
            'decision' => $decisionResult['decision'] ?? 'pending_review',
            'mrp_id' => $mrpPayload['mrp_id'],
            'mrp_version' => $mrpVersion,
        ];

        $this->recordAgentRun($pullRequest, 'negotiator', $decisionResult, $negotiatorDuration, $briefingPack);

        // Update PR status
        $pullRequest->update(['status' => 'scored']);

        Log::info("[GitHubWebhook] Agent pipeline complete for PR #{$pullRequest->pr_number}.", [
            'risk_score' => $riskScore,
            'decision' => $decisionResult['decision'] ?? 'unknown',
            'mrp_id' => $mrpPayload['mrp_id'],
            'total_duration_ms' => $archaeologistDuration + $historianDuration + $negotiatorDuration,
        ]);
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
        }

        $tokensUsed = $result['tokens_used'] ?? 0;

        // Use briefing pack as input payload if available, otherwise fall back to basic info
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
            'cost_usd' => $tokensUsed * 0.000001,
            'duration_ms' => $result['duration_ms'] ?? $durationMs,
            'model_used' => config('services.azure_openai.deployment', 'gpt-4.1-mini'),
            'input_hash' => md5($pullRequest->repo_full_name . ':' . $pullRequest->pr_number . ':' . $agentName),
        ]);

        Log::info("[AgentRun] Recorded {$agentName} run for PR #{$pullRequest->pr_number}.", [
            'status' => $status,
            'score_contribution' => $scoreContribution,
            'duration_ms' => $durationMs,
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

    private function runArchaeologist(PullRequest $pullRequest): array
    {
        $url = config('services.agents.archaeologist_url');

        try {
            if ($url) {
                $response = $this->agentHttp()->post($url, [
                    'repo_full_name' => $pullRequest->repo_full_name,
                    'pr_number' => $pullRequest->pr_number,
                    'base_branch' => $pullRequest->base_branch,
                    'head_branch' => $pullRequest->head_branch,
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
                        'summary' => $data['summary'] ?? 'Analysis complete.',
                        'duration_ms' => $data['duration_ms'] ?? 0,
                    ];
                }

                Log::warning("[Agent:Archaeologist] Non-200 response.", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("[Agent:Archaeologist] Agent call failed, using mock.", ['error' => $e->getMessage()]);
        }

        return $this->getMockArchaeologistResult();
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

                Log::warning("[Agent:Historian] Non-200 response.", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("[Agent:Historian] Agent call failed, using mock.", ['error' => $e->getMessage()]);
        }

        return $this->getMockHistorianResult();
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

                Log::warning("[Agent:Negotiator] Non-200 response.", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("[Agent:Negotiator] Agent call failed, using mock.", ['error' => $e->getMessage()]);
        }

        return $this->getMockNegotiatorResult($riskResult);
    }

    // --- Mock fallback data ---

    private function getMockArchaeologistResult(): array
    {
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
                    'reasoning' => 'Modified payment calculation function parameters',
                    'full_file_read' => true,
                ],
                [
                    'file' => 'src/api/routes/billing.py',
                    'change_type' => 'new_api_endpoint',
                    'risk_score' => 15,
                    'reasoning' => 'Added new billing calculation endpoint',
                    'full_file_read' => false,
                ],
                [
                    'file' => 'src/utils/rate_helper.py',
                    'change_type' => 'shared_utility_change',
                    'risk_score' => 25,
                    'reasoning' => 'Rate helper is imported by 4 other modules',
                    'full_file_read' => true,
                ],
            ],
            'total_blast_radius_score' => 60,
            'total_affected_files' => 3,
            'total_affected_services' => 4,
            'risk_indicators' => [
                'Payment calculation logic modified',
                'Shared utility function changed',
                'Multiple downstream services affected',
            ],
            'ci_status' => 'passing',
            'failing_checks' => [],
            'ci_risk_addition' => 0,
            'bot_findings' => [],
            'bot_risk_addition' => 0,
            'summary' => 'This PR modifies the payment rate calculator which is used by the billing API, notification service, and invoice generator. The shared rate_helper utility is imported by 4 modules, making this a wide-blast-radius change. Score: 60/100.',
        ];
    }

    private function getMockHistorianResult(): array
    {
        return [
            'status' => 'scored',
            'risk_score' => 65,
            'risk_level' => 'high',
            'historical_incidents' => [
                [
                    'id' => 'INC-001',
                    'title' => 'Payment processing outage',
                    'severity' => 1,
                    'days_ago' => 12,
                    'relevance' => 'Same service area — payment-service',
                    'match_type' => 'service',
                    'match_score' => 10,
                ],
            ],
            'match_summary' => [
                'file_matches' => 0,
                'service_matches' => 1,
                'change_type_matches' => 0,
                'history_score' => 10,
                'blast_radius_score' => 60,
                'ci_risk_addition' => 0,
                'bot_risk_addition' => 0,
            ],
            'contributing_factors' => [
                'Service area has recent P1 incident history (12 days ago)',
                'Multiple downstream services affected (4 services)',
                'Changes to critical payment path with shared utility modifications',
                'Blast radius score: 60/100',
            ],
            'recommendation' => 'Proceed with caution. The payment-service had a P1 outage 12 days ago. Consider deploying during a low-traffic window with enhanced monitoring on error rates and latency for payment-service, billing-api, and notification-service.',
        ];
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

        return [
            'decision' => $decision,
            'decided_by' => $score >= 50 ? null : 'DriftWatch AI',
            'has_concurrent_deploys' => false,
            'in_freeze_window' => false,
            'notified_oncall' => $score >= 50,
            'notification_message' => $score >= 50
                ? "Risk score {$score}/100. " . ($score >= 75 ? 'Blocked automatically.' : 'Review recommended.')
                : null,
            'decided_at' => $score < 50 ? now() : null,
        ];
    }
}
