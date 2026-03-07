<?php
// routes/api.php
// DriftWatch API routes - used by the Python AI agents, GitHub Action, and external integrations.

use App\Http\Controllers\GitHubWebhookController;
use App\Models\DeploymentDecision;
use App\Models\Incident;
use App\Models\PullRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Incidents API - used by the Historian agent to fetch historical data
Route::get('/incidents', function (Request $request) {
    $services = array_filter(explode(',', $request->get('services', '')));

    $query = Incident::query();

    if (!empty($services)) {
        $query->where(function ($q) use ($services) {
            foreach ($services as $service) {
                $q->orWhereJsonContains('affected_services', trim($service));
            }
        });
    }

    $incidents = $query->where('occurred_at', '>=', now()->subDays(90))
        ->orderByDesc('occurred_at')
        ->limit(30)
        ->get();

    return response()->json($incidents);
});

// === GitHub Action API Endpoints ===

// POST /api/analyze — Trigger analysis for a PR (used by driftwatch-analyze GitHub Action)
Route::post('/analyze', function (Request $request) {
    $request->validate([
        'pr_number' => ['required', 'integer'],
        'repo_full_name' => ['required', 'string', 'regex:#^[^/]+/[^/]+$#'],
    ]);

    $prNumber = $request->input('pr_number');
    $repoFullName = $request->input('repo_full_name');

    Log::info('[API:analyze] Analysis requested.', ['repo' => $repoFullName, 'pr' => $prNumber]);

    // Fetch PR data from GitHub API
    $ghToken = config('services.github.token');
    $prData = null;

    try {
        $ghResponse = Http::withHeaders([
            'Authorization' => "Bearer {$ghToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->timeout(15)->get("https://api.github.com/repos/{$repoFullName}/pulls/{$prNumber}");

        if ($ghResponse->successful()) {
            $prData = $ghResponse->json();
        }
    } catch (\Exception $e) {
        Log::warning('[API:analyze] GitHub API fetch failed.', ['error' => $e->getMessage()]);
    }

    // Create or update the PR record
    $pullRequest = PullRequest::updateOrCreate(
        [
            'repo_full_name' => $repoFullName,
            'pr_number' => $prNumber,
        ],
        [
            'github_pr_id' => (string) ($prData['id'] ?? "{$repoFullName}#{$prNumber}"),
            'pr_title' => $prData['title'] ?? "PR #{$prNumber}",
            'pr_author' => $prData['user']['login'] ?? 'unknown',
            'pr_url' => $prData['html_url'] ?? "https://github.com/{$repoFullName}/pull/{$prNumber}",
            'base_branch' => $prData['base']['ref'] ?? 'main',
            'head_branch' => $prData['head']['ref'] ?? 'unknown',
            'files_changed' => $prData['changed_files'] ?? 0,
            'additions' => $prData['additions'] ?? 0,
            'deletions' => $prData['deletions'] ?? 0,
            'status' => 'analyzing',
        ]
    );

    // Run the agent pipeline synchronously
    $webhookController = app(GitHubWebhookController::class);
    $webhookController->runAgentPipelinePublic($pullRequest);

    $pullRequest->refresh();
    $pullRequest->load(['riskAssessment', 'deploymentDecision', 'blastRadius']);

    return response()->json([
        'job_id' => $pullRequest->id,
        'status' => 'completed',
        'pr_id' => $pullRequest->id,
        'risk_score' => $pullRequest->riskAssessment?->risk_score ?? 0,
        'risk_level' => $pullRequest->riskAssessment?->risk_level ?? 'unknown',
        'decision' => $pullRequest->deploymentDecision?->decision ?? 'pending_review',
        'affected_services' => $pullRequest->blastRadius?->affected_services ?? [],
        'summary' => $pullRequest->blastRadius?->summary ?? 'Analysis complete.',
    ]);
});

// GET /api/jobs/{id}/status — Poll job status (used by GitHub Action polling loop)
Route::get('/jobs/{id}/status', function (int $id) {
    $pullRequest = PullRequest::with(['riskAssessment', 'deploymentDecision', 'blastRadius'])->find($id);

    if (!$pullRequest) {
        return response()->json(['status' => 'not_found', 'error' => 'Job not found'], 404);
    }

    $isComplete = $pullRequest->status !== 'analyzing';

    return response()->json([
        'status' => $isComplete ? 'completed' : 'processing',
        'pr_id' => $pullRequest->id,
        'risk_score' => $pullRequest->riskAssessment?->risk_score ?? 0,
        'risk_level' => $pullRequest->riskAssessment?->risk_level ?? 'unknown',
        'decision' => $pullRequest->deploymentDecision?->decision ?? 'pending_review',
        'affected_services' => $pullRequest->blastRadius?->affected_services ?? [],
        'summary' => $pullRequest->blastRadius?->summary ?? '',
    ]);
});

// === Decision Callback Endpoints (for Teams notifications) ===

Route::get('/decisions/{id}/approve', function (Request $request, int $id) {
    $decision = DeploymentDecision::with('pullRequest')->findOrFail($id);
    $token = $request->query('token');
    $expectedToken = hash_hmac('sha256', "decision-{$id}", config('app.key'));

    if (!$token || !hash_equals($expectedToken, $token)) {
        return response('Invalid or missing token.', 403);
    }

    $decision->update([
        'decision' => 'approved',
        'decided_by' => 'Teams Callback',
        'decided_at' => now(),
    ]);

    $decision->pullRequest->update(['status' => 'approved']);

    Log::info("[API:decision] PR #{$decision->pullRequest->pr_number} APPROVED via Teams callback.");

    return response()->view('driftwatch.decision-confirmed', [
        'action' => 'APPROVED',
        'prNumber' => $decision->pullRequest->pr_number,
    ]);
});

Route::get('/decisions/{id}/block', function (Request $request, int $id) {
    $decision = DeploymentDecision::with('pullRequest')->findOrFail($id);
    $token = $request->query('token');
    $expectedToken = hash_hmac('sha256', "decision-{$id}", config('app.key'));

    if (!$token || !hash_equals($expectedToken, $token)) {
        return response('Invalid or missing token.', 403);
    }

    $decision->update([
        'decision' => 'blocked',
        'decided_by' => 'Teams Callback',
        'decided_at' => now(),
    ]);

    $decision->pullRequest->update(['status' => 'blocked']);

    Log::info("[API:decision] PR #{$decision->pullRequest->pr_number} BLOCKED via Teams callback.");

    return response()->view('driftwatch.decision-confirmed', [
        'action' => 'BLOCKED',
        'prNumber' => $decision->pullRequest->pr_number,
    ]);
});
