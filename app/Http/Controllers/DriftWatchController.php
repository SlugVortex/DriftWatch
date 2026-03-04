<?php
// app/Http/Controllers/DriftWatchController.php
// Main dashboard controller for DriftWatch - handles all dashboard pages,
// PR detail views, approve/block actions, and analytics.

namespace App\Http\Controllers;

use App\Http\Controllers\GitHubWebhookController;
use App\Models\DeploymentDecision;
use App\Models\DeploymentOutcome;
use App\Models\Incident;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DriftWatchController extends Controller
{
    /**
     * Main dashboard - summary stats and recent PR list.
     */
    public function index()
    {
        Log::info('[DriftWatch] Dashboard loaded.');

        $pullRequests = PullRequest::with(['riskAssessment', 'deploymentDecision'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total_analyzed' => PullRequest::count(),
            'avg_risk_score' => round(RiskAssessment::avg('risk_score') ?? 0),
            'incidents_prevented' => DeploymentDecision::where('decision', 'blocked')->count(),
            'prediction_accuracy' => $this->calculateAccuracy(),
        ];

        return view('driftwatch.index', compact('pullRequests', 'stats'));
    }

    /**
     * Pull requests list page with filtering.
     */
    public function pullRequests(Request $request)
    {
        $query = PullRequest::with(['riskAssessment', 'deploymentDecision'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('pr_title', 'like', "%{$search}%")
                  ->orWhere('pr_author', 'like', "%{$search}%")
                  ->orWhere('repo_full_name', 'like', "%{$search}%");
            });
        }

        $pullRequests = $query->paginate(20)->withQueryString();

        return view('driftwatch.pull-requests', compact('pullRequests'));
    }

    /**
     * PR detail page - blast radius, risk assessment, deployment decision, timeline.
     */
    public function show(PullRequest $pullRequest)
    {
        $pullRequest->load([
            'blastRadius',
            'riskAssessment',
            'deploymentDecision',
            'deploymentOutcome',
        ]);

        Log::info("[DriftWatch] Viewing PR #{$pullRequest->pr_number} detail.", [
            'pr_id' => $pullRequest->id,
            'status' => $pullRequest->status,
        ]);

        return view('driftwatch.show', compact('pullRequest'));
    }

    /**
     * Approve a PR deployment.
     */
    public function approve(PullRequest $pullRequest)
    {
        $pullRequest->load('deploymentDecision');

        if (!$pullRequest->deploymentDecision) {
            return back()->with('error', 'No deployment decision found for this PR.');
        }

        $pullRequest->deploymentDecision->update([
            'decision' => 'approved',
            'decided_by' => 'Manual Override',
            'decided_at' => now(),
        ]);

        $pullRequest->update(['status' => 'approved']);

        Log::info("[DriftWatch] PR #{$pullRequest->pr_number} APPROVED manually.", [
            'pr_id' => $pullRequest->id,
            'decided_by' => 'Manual Override',
        ]);

        return back()->with('success', "PR #{$pullRequest->pr_number} deployment approved.");
    }

    /**
     * Block a PR deployment.
     */
    public function block(PullRequest $pullRequest)
    {
        $pullRequest->load('deploymentDecision');

        if (!$pullRequest->deploymentDecision) {
            return back()->with('error', 'No deployment decision found for this PR.');
        }

        $pullRequest->deploymentDecision->update([
            'decision' => 'blocked',
            'decided_by' => 'Manual Override',
            'decided_at' => now(),
        ]);

        $pullRequest->update(['status' => 'blocked']);

        Log::info("[DriftWatch] PR #{$pullRequest->pr_number} BLOCKED manually.", [
            'pr_id' => $pullRequest->id,
            'decided_by' => 'Manual Override',
        ]);

        return back()->with('warning', "PR #{$pullRequest->pr_number} deployment blocked.");
    }

    /**
     * Analyze a PR by URL - manual trigger from dashboard.
     * Accepts a GitHub PR URL like https://github.com/owner/repo/pull/123
     */
    public function analyzePr(Request $request)
    {
        $request->validate([
            'pr_url' => ['required', 'url', 'regex:#^https://github\.com/([^/]+)/([^/]+)/pull/(\d+)$#'],
        ], [
            'pr_url.regex' => 'Please enter a valid GitHub PR URL (e.g., https://github.com/owner/repo/pull/123)',
        ]);

        $url = $request->input('pr_url');
        preg_match('#^https://github\.com/([^/]+)/([^/]+)/pull/(\d+)$#', $url, $matches);
        $owner = $matches[1];
        $repo = $matches[2];
        $prNumber = (int) $matches[3];
        $repoFullName = "{$owner}/{$repo}";

        Log::info("[DriftWatch] Manual analysis requested.", [
            'repo' => $repoFullName,
            'pr_number' => $prNumber,
        ]);

        // Fetch PR data from GitHub API
        try {
            $ghToken = config('services.github.token');
            $ghResponse = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(15)->get("https://api.github.com/repos/{$repoFullName}/pulls/{$prNumber}");

            if (!$ghResponse->successful()) {
                Log::warning('[DriftWatch] GitHub API returned error.', [
                    'status' => $ghResponse->status(),
                    'body' => $ghResponse->body(),
                ]);
                return back()->with('error', "Could not fetch PR from GitHub (HTTP {$ghResponse->status()}). Check the URL and ensure the repo is accessible.");
            }

            $prData = $ghResponse->json();
        } catch (\Exception $e) {
            Log::error('[DriftWatch] GitHub API call failed.', ['error' => $e->getMessage()]);
            return back()->with('error', 'Could not connect to GitHub API: ' . $e->getMessage());
        }

        // Create or update the PR record
        $pullRequest = PullRequest::updateOrCreate(
            ['github_pr_id' => (string) $prData['id']],
            [
                'repo_full_name' => $repoFullName,
                'pr_number' => $prNumber,
                'pr_title' => $prData['title'] ?? 'Untitled',
                'pr_author' => $prData['user']['login'] ?? 'unknown',
                'pr_url' => $prData['html_url'] ?? $url,
                'base_branch' => $prData['base']['ref'] ?? 'main',
                'head_branch' => $prData['head']['ref'] ?? 'unknown',
                'files_changed' => $prData['changed_files'] ?? 0,
                'additions' => $prData['additions'] ?? 0,
                'deletions' => $prData['deletions'] ?? 0,
                'status' => 'analyzing',
            ]
        );

        // Run the agent pipeline
        $webhookController = app(GitHubWebhookController::class);
        $webhookController->runAgentPipelinePublic($pullRequest);

        return redirect()
            ->route('driftwatch.show', $pullRequest)
            ->with('success', "PR #{$prNumber} from {$repoFullName} analyzed successfully by all agents!");
    }

    /**
     * Re-analyze an existing PR through the agent pipeline.
     */
    public function reanalyze(PullRequest $pullRequest)
    {
        Log::info("[DriftWatch] Re-analysis requested for PR #{$pullRequest->pr_number}.");

        $pullRequest->update(['status' => 'analyzing']);

        $webhookController = app(GitHubWebhookController::class);
        $webhookController->runAgentPipelinePublic($pullRequest);

        return redirect()
            ->route('driftwatch.show', $pullRequest)
            ->with('success', "PR #{$pullRequest->pr_number} re-analyzed successfully!");
    }

    /**
     * Incidents list page.
     */
    public function incidents()
    {
        $incidents = Incident::orderByDesc('occurred_at')->paginate(20);

        return view('driftwatch.incidents', compact('incidents'));
    }

    /**
     * Analytics page - risk distribution, accuracy trends, risky services.
     */
    public function analytics()
    {
        $riskDistribution = RiskAssessment::selectRaw('risk_level, COUNT(*) as count')
            ->groupBy('risk_level')
            ->pluck('count', 'risk_level')
            ->toArray();

        $recentAssessments = RiskAssessment::with('pullRequest')
            ->latest()
            ->limit(20)
            ->get();

        $topRiskyServices = $this->getTopRiskyServices();

        $accuracy = $this->calculateAccuracy();

        $totalBlocked = DeploymentDecision::where('decision', 'blocked')->count();
        $totalApproved = DeploymentDecision::where('decision', 'approved')->count();

        return view('driftwatch.analytics', compact(
            'riskDistribution',
            'recentAssessments',
            'topRiskyServices',
            'accuracy',
            'totalBlocked',
            'totalApproved'
        ));
    }

    /**
     * Agent status pages - show what each agent does and recent results.
     */
    public function agentStatus(string $agent)
    {
        $validAgents = ['archaeologist', 'historian', 'negotiator', 'chronicler'];
        if (!in_array($agent, $validAgents)) {
            abort(404);
        }

        $agentInfo = $this->getAgentInfo($agent);
        $recentResults = $this->getAgentRecentResults($agent);

        return view('driftwatch.agents.show', compact('agent', 'agentInfo', 'recentResults'));
    }

    /**
     * Agent Map - visual pipeline orchestration page.
     */
    public function agentMap()
    {
        Log::info('[DriftWatch] Agent Map page loaded.');

        $recentRuns = PullRequest::with(['blastRadius', 'riskAssessment', 'deploymentDecision', 'deploymentOutcome'])
            ->latest()
            ->limit(10)
            ->get();

        // Determine agent statuses based on most recent PR
        $latestPr = $recentRuns->first();
        $agentStatuses = [
            'archaeologist' => 'idle',
            'historian' => 'idle',
            'negotiator' => 'idle',
            'chronicler' => 'idle',
        ];

        if ($latestPr) {
            $agentStatuses['archaeologist'] = $latestPr->blastRadius ? 'complete' : ($latestPr->status === 'analyzing' ? 'active' : 'idle');
            $agentStatuses['historian'] = $latestPr->riskAssessment ? 'complete' : ($latestPr->blastRadius && $latestPr->status === 'analyzing' ? 'active' : 'idle');
            $agentStatuses['negotiator'] = $latestPr->deploymentDecision ? 'complete' : ($latestPr->riskAssessment && $latestPr->status === 'analyzing' ? 'active' : 'idle');
            $agentStatuses['chronicler'] = $latestPr->deploymentOutcome ? 'complete' : 'idle';
        }

        // Agent stats
        $agentStats = [
            'archaeologist' => [
                'name' => 'Archaeologist',
                'subtitle' => 'Blast Radius Mapper',
                'icon' => 'explore',
                'color' => 'primary',
                'total_runs' => PullRequest::whereHas('blastRadius')->count(),
                'success_rate' => $this->agentSuccessRate('blastRadius'),
                'avg_time' => 3,
            ],
            'historian' => [
                'name' => 'Historian',
                'subtitle' => 'Risk Calculator',
                'icon' => 'history',
                'color' => 'warning',
                'total_runs' => PullRequest::whereHas('riskAssessment')->count(),
                'success_rate' => $this->agentSuccessRate('riskAssessment'),
                'avg_time' => 4,
            ],
            'negotiator' => [
                'name' => 'Negotiator',
                'subtitle' => 'Deploy Gatekeeper',
                'icon' => 'gavel',
                'color' => 'danger',
                'total_runs' => PullRequest::whereHas('deploymentDecision')->count(),
                'success_rate' => $this->agentSuccessRate('deploymentDecision'),
                'avg_time' => 2,
            ],
            'chronicler' => [
                'name' => 'Chronicler',
                'subtitle' => 'Feedback Recorder',
                'icon' => 'auto_stories',
                'color' => 'success',
                'total_runs' => PullRequest::whereHas('deploymentOutcome')->count(),
                'success_rate' => 100,
                'avg_time' => 1,
            ],
        ];

        // Observability metrics
        $totalPrs = PullRequest::count();
        $observability = [
            'total_traces' => $totalPrs,
            'error_rate' => $totalPrs > 0 ? round((PullRequest::where('status', 'error')->count() / $totalPrs) * 100, 1) : 0,
            'avg_latency' => 10,
        ];

        return view('driftwatch.agent-map', compact('recentRuns', 'agentStatuses', 'agentStats', 'observability'));
    }

    /**
     * Governance & Responsible AI page.
     */
    public function governance()
    {
        Log::info('[DriftWatch] Governance page loaded.');

        $recentDecisions = DeploymentDecision::with('pullRequest')
            ->latest()
            ->limit(10)
            ->get();

        return view('driftwatch.governance', compact('recentDecisions'));
    }

    /**
     * Repositories list page.
     */
    public function repositories()
    {
        Log::info('[DriftWatch] Repositories page loaded.');

        $repositories = \App\Models\Repository::withCount('pullRequests')->latest()->get();

        return view('driftwatch.repositories.index', compact('repositories'));
    }

    /**
     * Connect a GitHub repository.
     */
    public function connectRepository(Request $request)
    {
        $request->validate([
            'repo_input' => ['required', 'string'],
        ]);

        $input = trim($request->input('repo_input'));

        // Parse owner/repo from URL or direct input
        if (preg_match('#github\.com/([^/]+)/([^/]+)#', $input, $matches)) {
            $owner = $matches[1];
            $repo = rtrim($matches[2], '.git');
        } elseif (preg_match('#^([^/]+)/([^/]+)$#', $input, $matches)) {
            $owner = $matches[1];
            $repo = $matches[2];
        } else {
            return back()->with('error', 'Invalid format. Use owner/repo or a GitHub URL.');
        }

        $fullName = "{$owner}/{$repo}";

        // Check if already connected
        $existing = \App\Models\Repository::where('full_name', $fullName)->first();
        if ($existing) {
            return back()->with('warning', "Repository {$fullName} is already connected.");
        }

        // Try to verify via GitHub API
        try {
            $ghToken = config('services.github.token');
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(10)->get("https://api.github.com/repos/{$fullName}");

            if ($response->successful()) {
                $data = $response->json();
                $repository = \App\Models\Repository::create([
                    'name' => $data['name'],
                    'full_name' => $data['full_name'],
                    'owner' => $data['owner']['login'],
                    'default_branch' => $data['default_branch'] ?? 'main',
                    'github_url' => $data['html_url'],
                    'is_active' => true,
                ]);
            } else {
                // Create with manual data if API fails
                $repository = \App\Models\Repository::create([
                    'name' => $repo,
                    'full_name' => $fullName,
                    'owner' => $owner,
                    'default_branch' => 'main',
                    'github_url' => "https://github.com/{$fullName}",
                    'is_active' => true,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[DriftWatch] GitHub API failed for repo connect, using manual data.', ['error' => $e->getMessage()]);
            $repository = \App\Models\Repository::create([
                'name' => $repo,
                'full_name' => $fullName,
                'owner' => $owner,
                'default_branch' => 'main',
                'github_url' => "https://github.com/{$fullName}",
                'is_active' => true,
            ]);
        }

        Log::info("[DriftWatch] Repository connected: {$fullName}", ['id' => $repository->id]);

        return redirect()->route('driftwatch.repositories.show', $repository)
            ->with('success', "Repository {$fullName} connected successfully!");
    }

    /**
     * Show a single repository and its PRs.
     */
    public function showRepository(\App\Models\Repository $repository)
    {
        $pullRequests = PullRequest::where('repo_full_name', $repository->full_name)
            ->with(['riskAssessment', 'deploymentDecision'])
            ->latest()
            ->paginate(20);

        return view('driftwatch.repositories.show', compact('repository', 'pullRequests'));
    }

    /**
     * Sync PRs from a connected repository.
     */
    public function syncRepository(\App\Models\Repository $repository)
    {
        Log::info("[DriftWatch] Syncing repository: {$repository->full_name}");

        try {
            $ghToken = config('services.github.token');
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$ghToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ])->timeout(15)->get("https://api.github.com/repos/{$repository->full_name}/pulls", [
                'state' => 'open',
                'per_page' => 20,
            ]);

            if (!$response->successful()) {
                return back()->with('error', "Could not fetch PRs from GitHub (HTTP {$response->status()}).");
            }

            $prs = $response->json();
            $synced = 0;

            foreach ($prs as $prData) {
                PullRequest::updateOrCreate(
                    ['github_pr_id' => (string) $prData['id']],
                    [
                        'repo_full_name' => $repository->full_name,
                        'pr_number' => $prData['number'],
                        'pr_title' => $prData['title'] ?? 'Untitled',
                        'pr_author' => $prData['user']['login'] ?? 'unknown',
                        'pr_url' => $prData['html_url'],
                        'base_branch' => $prData['base']['ref'] ?? 'main',
                        'head_branch' => $prData['head']['ref'] ?? 'unknown',
                        'files_changed' => 0,
                        'additions' => 0,
                        'deletions' => 0,
                        'status' => 'pending',
                    ]
                );
                $synced++;
            }

            $repository->update(['last_synced_at' => now()]);

            Log::info("[DriftWatch] Synced {$synced} PRs from {$repository->full_name}");

            return back()->with('success', "Synced {$synced} open PRs from {$repository->full_name}.");
        } catch (\Exception $e) {
            Log::error("[DriftWatch] Sync failed for {$repository->full_name}", ['error' => $e->getMessage()]);
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect a repository.
     */
    public function disconnectRepository(\App\Models\Repository $repository)
    {
        $name = $repository->full_name;
        $repository->update(['is_active' => false]);

        Log::info("[DriftWatch] Repository disconnected: {$name}");

        return redirect()->route('driftwatch.repositories')
            ->with('success', "Repository {$name} disconnected.");
    }

    /**
     * Settings page.
     */
    public function settings()
    {
        return view('driftwatch.settings');
    }

    // --- Private helpers ---

    /**
     * Calculate prediction accuracy from deployment outcomes.
     */
    private function calculateAccuracy(): float
    {
        $total = DeploymentOutcome::count();
        if ($total === 0) {
            return 0;
        }

        $accurate = DeploymentOutcome::where('prediction_accurate', true)->count();
        return round(($accurate / $total) * 100, 1);
    }

    /**
     * Aggregate which services appear most in high-risk assessments.
     */
    private function getTopRiskyServices(): array
    {
        $incidents = Incident::all();
        $serviceCounts = [];

        foreach ($incidents as $incident) {
            $services = is_array($incident->affected_services) ? $incident->affected_services : [];
            foreach ($services as $service) {
                $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;
            }
        }

        arsort($serviceCounts);
        return array_slice($serviceCounts, 0, 10, true);
    }

    /**
     * Calculate success rate for a given agent relationship.
     */
    private function agentSuccessRate(string $relation): int
    {
        $total = PullRequest::count();
        if ($total === 0) {
            return 0;
        }

        $completed = PullRequest::whereHas($relation)->count();
        return (int) round(($completed / $total) * 100);
    }

    /**
     * Returns metadata about each agent for display.
     */
    private function getAgentInfo(string $agent): array
    {
        return match ($agent) {
            'archaeologist' => [
                'name' => 'The Archaeologist',
                'subtitle' => 'Blast Radius Mapper',
                'description' => 'Analyzes PR diffs to map which services, endpoints, and files are affected. Traces dependency graphs to find downstream impact.',
                'icon' => 'explore',
                'color' => 'primary',
                'inputs' => 'GitHub PR diff, file changes, repository structure',
                'outputs' => 'Affected files, services, endpoints, dependency graph, summary',
            ],
            'historian' => [
                'name' => 'The Historian',
                'subtitle' => 'Risk Score Calculator',
                'description' => 'Correlates the blast radius with 90 days of historical incident data to produce a risk score (0-100) and identify patterns.',
                'icon' => 'history',
                'color' => 'warning',
                'inputs' => 'Blast radius results, historical incidents database',
                'outputs' => 'Risk score (0-100), risk level, historical correlations, contributing factors, recommendation',
            ],
            'negotiator' => [
                'name' => 'The Negotiator',
                'subtitle' => 'Deployment Gatekeeper',
                'description' => 'Makes the deploy/block/review decision based on risk score, concurrent deployments, freeze windows, and team context. Posts PR comments.',
                'icon' => 'gavel',
                'color' => 'danger',
                'inputs' => 'Risk assessment, deployment context, team schedules',
                'outputs' => 'Decision (approve/block/review), notifications, GitHub PR comment',
            ],
            'chronicler' => [
                'name' => 'The Chronicler',
                'subtitle' => 'Feedback Loop Recorder',
                'description' => 'Runs after deployment to compare predictions vs reality. Records whether incidents occurred and feeds accuracy data back into the system.',
                'icon' => 'auto_stories',
                'color' => 'success',
                'inputs' => 'Predicted risk score, actual deployment outcome, monitoring data',
                'outputs' => 'Prediction accuracy, post-mortem notes, system learning data',
            ],
        };
    }

    /**
     * Gets recent results for a specific agent.
     */
    private function getAgentRecentResults(string $agent): \Illuminate\Support\Collection
    {
        return match ($agent) {
            'archaeologist' => PullRequest::with('blastRadius')
                ->whereHas('blastRadius')
                ->latest()->limit(10)->get(),
            'historian' => PullRequest::with('riskAssessment')
                ->whereHas('riskAssessment')
                ->latest()->limit(10)->get(),
            'negotiator' => PullRequest::with('deploymentDecision')
                ->whereHas('deploymentDecision')
                ->latest()->limit(10)->get(),
            'chronicler' => PullRequest::with('deploymentOutcome')
                ->whereHas('deploymentOutcome')
                ->latest()->limit(10)->get(),
        };
    }
}
