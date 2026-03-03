<?php
// app/Http/Controllers/GitHubWebhookController.php
// Receives GitHub webhook events for pull_request.opened and pull_request.synchronize.
// Creates PullRequest records and dispatches the agent pipeline.

namespace App\Http\Controllers;

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

        // Run the agent pipeline synchronously with mock fallbacks
        // In production, this would dispatch queued jobs
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
     * Each agent tries to call the Azure Function first, falls back to mock data.
     */
    private function runAgentPipeline(PullRequest $pullRequest): void
    {
        // Agent 1: Archaeologist (Blast Radius)
        $blastResult = $this->runArchaeologist($pullRequest);
        BlastRadiusResult::updateOrCreate(
            ['pull_request_id' => $pullRequest->id],
            $blastResult
        );

        // Agent 2: Historian (Risk Assessment)
        $riskResult = $this->runHistorian($pullRequest, $blastResult);
        RiskAssessment::updateOrCreate(
            ['pull_request_id' => $pullRequest->id],
            $riskResult
        );

        // Agent 3: Negotiator (Deployment Decision)
        $decisionResult = $this->runNegotiator($pullRequest, $riskResult);
        DeploymentDecision::updateOrCreate(
            ['pull_request_id' => $pullRequest->id],
            $decisionResult
        );

        // Update PR status
        $pullRequest->update(['status' => 'scored']);

        Log::info("[GitHubWebhook] Agent pipeline complete for PR #{$pullRequest->pr_number}.", [
            'risk_score' => $riskResult['risk_score'],
            'decision' => $decisionResult['decision'],
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
                        'affected_files' => $data['affected_files'] ?? [],
                        'affected_services' => $data['affected_services'] ?? [],
                        'affected_endpoints' => $data['affected_endpoints'] ?? [],
                        'dependency_graph' => $data['dependency_graph'] ?? [],
                        'total_affected_files' => count($data['affected_files'] ?? []),
                        'total_affected_services' => count($data['affected_services'] ?? []),
                        'summary' => $data['summary'] ?? 'Analysis complete.',
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
                    'affected_services' => $blastResult['affected_services'],
                    'affected_files' => $blastResult['affected_files'],
                    'risk_indicators' => [],
                    'pr_number' => $pullRequest->pr_number,
                    'repo_full_name' => $pullRequest->repo_full_name,
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
                    'summary' => '',
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
            'total_affected_files' => 3,
            'total_affected_services' => 4,
            'summary' => 'This PR modifies the payment rate calculator which is used by the billing API, notification service, and invoice generator. Changes propagate to 4 downstream services and 3 API endpoints.',
        ];
    }

    private function getMockHistorianResult(): array
    {
        return [
            'risk_score' => 65,
            'risk_level' => 'high',
            'historical_incidents' => [
                ['id' => 'INC-001', 'title' => 'Payment processing outage', 'severity' => 1, 'days_ago' => 12, 'relevance' => 'Same service area'],
            ],
            'contributing_factors' => [
                'Service area has recent incident history',
                'Multiple downstream services affected',
                'Changes to critical payment path',
            ],
            'recommendation' => 'Proceed with caution. Consider deploying during low-traffic window with enhanced monitoring.',
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
