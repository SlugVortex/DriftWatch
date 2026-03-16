<?php

// app/Http/Controllers/DriftWatchController.php
// Main dashboard controller for DriftWatch - handles all dashboard pages,
// PR detail views, approve/block actions, and analytics.

namespace App\Http\Controllers;

use App\Models\DeploymentDecision;
use App\Models\DeploymentOutcome;
use App\Models\Incident;
use App\Models\PipelineConfig;
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
     * Generates AI file descriptions on first view if not yet present.
     */
    public function show(PullRequest $pullRequest)
    {
        $pullRequest->load([
            'blastRadius',
            'riskAssessment',
            'deploymentDecision',
            'deploymentOutcome',
            'agentRuns',
        ]);

        Log::info("[DriftWatch] Viewing PR #{$pullRequest->pr_number} detail.", [
            'pr_id' => $pullRequest->id,
            'status' => $pullRequest->status,
        ]);

        // Generate AI file descriptions if not yet present
        if ($pullRequest->blastRadius && empty($pullRequest->blastRadius->file_descriptions)) {
            $descriptions = $this->generateFileDescriptions($pullRequest);
            if (! empty($descriptions)) {
                $pullRequest->blastRadius->update(['file_descriptions' => $descriptions]);
                $pullRequest->load('blastRadius');
            }
        }

        return view('driftwatch.show', compact('pullRequest'));
    }

    /**
     * Approve a PR deployment.
     */
    public function approve(PullRequest $pullRequest)
    {
        $pullRequest->load('deploymentDecision');

        if (! $pullRequest->deploymentDecision) {
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

        if (! $pullRequest->deploymentDecision) {
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

        Log::info('[DriftWatch] Manual analysis requested.', [
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

            if (! $ghResponse->successful()) {
                Log::warning('[DriftWatch] GitHub API returned error.', [
                    'status' => $ghResponse->status(),
                    'body' => $ghResponse->body(),
                ]);

                return back()->with('error', "Could not fetch PR from GitHub (HTTP {$ghResponse->status()}). Check the URL and ensure the repo is accessible.");
            }

            $prData = $ghResponse->json();
        } catch (\Exception $e) {
            Log::error('[DriftWatch] GitHub API call failed.', ['error' => $e->getMessage()]);

            return back()->with('error', 'Could not connect to GitHub API: '.$e->getMessage());
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
        if (! in_array($agent, $validAgents)) {
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
     * Explainability page — how scoring and pipeline decisions work.
     */
    public function explainability(): \Illuminate\View\View
    {
        Log::info('[DriftWatch] Explainability page loaded.');

        $defaultConfig = PipelineConfig::getDefault();

        return view('driftwatch.explainability', compact('defaultConfig'));
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

            if (! $response->successful()) {
                return back()->with('error', "Could not fetch PRs from GitHub (HTTP {$response->status()}).");
            }

            $prs = $response->json();
            $synced = 0;

            foreach ($prs as $prData) {
                // Fetch individual PR details for file counts (list endpoint doesn't include them)
                $filesChanged = 0;
                $additions = 0;
                $deletions = 0;
                try {
                    $detailResponse = Http::withHeaders([
                        'Authorization' => "Bearer {$ghToken}",
                        'Accept' => 'application/vnd.github.v3+json',
                    ])->timeout(10)->get("https://api.github.com/repos/{$repository->full_name}/pulls/{$prData['number']}");

                    if ($detailResponse->successful()) {
                        $detail = $detailResponse->json();
                        $filesChanged = $detail['changed_files'] ?? 0;
                        $additions = $detail['additions'] ?? 0;
                        $deletions = $detail['deletions'] ?? 0;
                    }
                } catch (\Exception $e) {
                    Log::debug("[DriftWatch] Could not fetch PR detail for #{$prData['number']}: {$e->getMessage()}");
                }

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
                        'files_changed' => $filesChanged,
                        'additions' => $additions,
                        'deletions' => $deletions,
                        'status' => 'pending',
                    ]
                );
                $synced++;
            }

            $repository->update(['last_synced_at' => now()]);

            Log::info("[DriftWatch] Synced {$synced} PRs from {$repository->full_name}");

            // Auto-analyze if enabled
            $autoMsg = '';
            if ($repository->auto_analyze && $synced > 0) {
                $unanalyzed = PullRequest::where('repo_full_name', $repository->full_name)
                    ->where('status', 'pending')
                    ->whereDoesntHave('riskAssessment')
                    ->get();

                if ($unanalyzed->isNotEmpty()) {
                    $webhookController = app(\App\Http\Controllers\GitHubWebhookController::class);
                    $analyzed = 0;
                    foreach ($unanalyzed as $pr) {
                        try {
                            $pr->update(['status' => 'analyzing']);
                            $webhookController->runAgentPipelinePublic($pr);
                            $analyzed++;
                        } catch (\Exception $e) {
                            Log::error("[DriftWatch] Auto-analyze failed for PR #{$pr->pr_number}", ['error' => $e->getMessage()]);
                            $pr->update(['status' => 'pending']);
                        }
                    }
                    $autoMsg = " Auto-analyzed {$analyzed} PR(s).";
                    Log::info("[DriftWatch] Auto-analyzed {$analyzed} PRs from {$repository->full_name}");
                }
            }

            return back()->with('success', "Synced {$synced} open PRs from {$repository->full_name}.{$autoMsg}");
        } catch (\Exception $e) {
            Log::error("[DriftWatch] Sync failed for {$repository->full_name}", ['error' => $e->getMessage()]);

            return back()->with('error', 'Sync failed: '.$e->getMessage());
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
     * Toggle auto-analyze on a repository.
     */
    public function toggleAutoAnalyze(\App\Models\Repository $repository)
    {
        $repository->update(['auto_analyze' => ! $repository->auto_analyze]);

        $state = $repository->auto_analyze ? 'enabled' : 'disabled';
        Log::info("[DriftWatch] Auto-analyze {$state} for {$repository->full_name}");

        return back()->with('success', "Auto-analyze {$state} for {$repository->full_name}.");
    }

    /**
     * Analyze all unanalyzed PRs in a repository.
     */
    public function analyzeAllPrs(\App\Models\Repository $repository)
    {
        $unanalyzed = PullRequest::where('repo_full_name', $repository->full_name)
            ->where('status', 'pending')
            ->whereDoesntHave('riskAssessment')
            ->get();

        if ($unanalyzed->isEmpty()) {
            return back()->with('warning', 'No unanalyzed PRs found. All PRs have already been processed.');
        }

        $webhookController = app(\App\Http\Controllers\GitHubWebhookController::class);
        $analyzed = 0;

        foreach ($unanalyzed as $pr) {
            try {
                $pr->update(['status' => 'analyzing']);
                $webhookController->runAgentPipelinePublic($pr);
                $analyzed++;
            } catch (\Exception $e) {
                Log::error("[DriftWatch] Auto-analyze failed for PR #{$pr->pr_number}", ['error' => $e->getMessage()]);
                $pr->update(['status' => 'pending']);
            }
        }

        Log::info("[DriftWatch] Auto-analyzed {$analyzed} PRs from {$repository->full_name}");

        return back()->with('success', "Analyzed {$analyzed} PR(s) from {$repository->full_name}.");
    }

    /**
     * Settings page — includes pipeline configuration.
     */
    public function settings()
    {
        $pipelineConfigs = PipelineConfig::all();

        // Seed defaults if none exist
        if ($pipelineConfigs->isEmpty()) {
            PipelineConfig::seedBuiltInTemplates();
            $pipelineConfigs = PipelineConfig::all();
        }

        $defaultConfig = $pipelineConfigs->firstWhere('is_default', true) ?? $pipelineConfigs->first();

        return view('driftwatch.settings', compact('pipelineConfigs', 'defaultConfig'));
    }

    /**
     * Save pipeline configuration updates.
     */
    public function savePipelineConfig(Request $request)
    {
        $request->validate([
            'config_id' => ['required', 'exists:pipeline_configs,id'],
        ]);

        $config = PipelineConfig::findOrFail($request->input('config_id'));

        $config->update([
            'agent_archaeologist' => $request->boolean('agent_archaeologist', true),
            'agent_historian' => $request->boolean('agent_historian', true),
            'agent_negotiator' => $request->boolean('agent_negotiator', true),
            'agent_chronicler' => $request->boolean('agent_chronicler', true),
            'require_approval_after_scoring' => $request->boolean('require_approval_after_scoring'),
            'auto_approve_below_score' => (int) $request->input('auto_approve_below_score', 20),
            'auto_block_above_score' => (int) $request->input('auto_block_above_score', 85),
            'max_retries_per_agent' => (int) $request->input('max_retries_per_agent', 1),
            'retry_on_timeout' => $request->boolean('retry_on_timeout', true),
        ]);

        // Update environment thresholds
        $envThresholds = [];
        foreach (['production', 'staging', 'development'] as $env) {
            if ($request->has("env_{$env}_threshold")) {
                $envThresholds[$env] = [
                    'risk_threshold' => (int) $request->input("env_{$env}_threshold", 50),
                    'require_approval' => $request->boolean("env_{$env}_approval"),
                ];
            }
        }
        if (! empty($envThresholds)) {
            $config->update(['environment_thresholds' => $envThresholds]);
        }

        // Set as default if requested
        if ($request->boolean('set_default')) {
            PipelineConfig::where('id', '!=', $config->id)->update(['is_default' => false]);
            $config->update(['is_default' => true]);
        }

        Log::info("[DriftWatch] Pipeline config '{$config->name}' updated.");

        return back()->with('success', "Pipeline template '{$config->label}' saved successfully.");
    }

    /**
     * Save GitHub token to the .env file.
     */
    public function saveGithubToken(Request $request): \Illuminate\Http\RedirectResponse
    {
        $token = trim($request->input('github_token', ''));

        if (empty($token)) {
            return back()->with('error', 'Token cannot be empty.');
        }

        // Validate token format — GitHub tokens start with ghp_, gho_, ghu_, ghs_, ghr_, or github_pat_
        if (! preg_match('/^(ghp_|gho_|ghu_|ghs_|ghr_|github_pat_)[a-zA-Z0-9_]+$/', $token)) {
            return back()->with('error', 'Invalid token format. GitHub tokens start with ghp_ or github_pat_ followed by alphanumeric characters.');
        }

        // Update .env file
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (preg_match('/^GITHUB_TOKEN=.*/m', $envContent)) {
            $envContent = preg_replace('/^GITHUB_TOKEN=.*/m', "GITHUB_TOKEN=\"{$token}\"", $envContent);
        } else {
            $envContent .= "\nGITHUB_TOKEN=\"{$token}\"\n";
        }

        file_put_contents($envPath, $envContent);

        // Clear config cache so the new token takes effect
        \Artisan::call('config:clear');

        Log::info('[DriftWatch] GitHub token updated via settings page.');

        return back()->with('success', 'GitHub token saved successfully. Agents can now read PR code.');
    }

    /**
     * Reset pipeline configs to built-in defaults.
     */
    public function resetPipelineConfig()
    {
        PipelineConfig::truncate();
        PipelineConfig::seedBuiltInTemplates();

        Log::info('[DriftWatch] Pipeline configs reset to defaults.');

        return back()->with('success', 'Pipeline configurations reset to defaults.');
    }

    /**
     * Resume a paused pipeline (approval gate).
     */
    public function resumePipeline(PullRequest $pullRequest)
    {
        if (! $pullRequest->pipeline_paused) {
            return back()->with('warning', 'This pipeline is not paused.');
        }

        Log::info("[DriftWatch] Pipeline resumed for PR #{$pullRequest->pr_number} by manual approval.");

        $webhookController = app(GitHubWebhookController::class);
        $webhookController->resumePipeline($pullRequest);

        return redirect()
            ->route('driftwatch.show', $pullRequest)
            ->with('success', "Pipeline resumed for PR #{$pullRequest->pr_number}. Negotiator agent will now make its decision.");
    }

    /**
     * Update the target environment for a PR.
     */
    public function updateEnvironment(PullRequest $pullRequest, Request $request)
    {
        $request->validate([
            'target_environment' => ['required', 'in:production,staging,development'],
        ]);

        $pullRequest->update([
            'target_environment' => $request->input('target_environment'),
        ]);

        Log::info("[DriftWatch] PR #{$pullRequest->pr_number} target environment changed to {$request->input('target_environment')}.");

        return back()->with('success', "Target environment updated to {$request->input('target_environment')}.");
    }

    /**
     * Update the pipeline template for a PR.
     */
    public function updateTemplate(PullRequest $pullRequest, Request $request)
    {
        $request->validate([
            'pipeline_template' => ['required', 'string'],
        ]);

        $pullRequest->update([
            'pipeline_template' => $request->input('pipeline_template'),
        ]);

        Log::info("[DriftWatch] PR #{$pullRequest->pr_number} pipeline template changed to {$request->input('pipeline_template')}.");

        return back()->with('success', "Pipeline template updated to {$request->input('pipeline_template')}.");
    }

    public function toggleGate(PullRequest $pullRequest, Request $request): \Illuminate\Http\RedirectResponse
    {
        $env = $pullRequest->target_environment ?? 'production';
        $template = $pullRequest->pipeline_template ?? 'full';

        $config = PipelineConfig::where('name', $template)->first() ?? PipelineConfig::first();

        if ($config) {
            $thresholds = $config->environment_thresholds ?? [];
            $gateEnabled = $request->has('gate_enabled');
            $thresholds[$env]['require_approval'] = $gateEnabled;
            $config->environment_thresholds = $thresholds;
            $config->save();

            Log::info("[DriftWatch] Approval gate for {$env} toggled to " . ($gateEnabled ? 'ON' : 'OFF') . '.');
        }

        return back()->with('success', "Approval gate for {$env} " . ($request->has('gate_enabled') ? 'enabled' : 'disabled') . '.');
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

    /**
     * Generate AI-powered file descriptions by fetching code from GitHub
     * and analyzing with Azure OpenAI. Returns associative array of file => description.
     *
     * @return array<string, array{summary: string, role: string, risk: string, affects: string}>
     */
    private function generateFileDescriptions(PullRequest $pullRequest): array
    {
        $blastRadius = $pullRequest->blastRadius;
        if (! $blastRadius) {
            return [];
        }

        $affectedFiles = $blastRadius->affected_files ?? [];
        $depGraph = $blastRadius->dependency_graph ?? [];
        $ghToken = config('services.github.token');
        $repo = $pullRequest->repo_full_name;
        $branch = $pullRequest->head_branch;

        // Collect file snippets from GitHub (first 80 lines of each)
        $fileSnippets = [];
        foreach (array_slice($affectedFiles, 0, 25) as $filePath) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$ghToken}",
                    'Accept' => 'application/vnd.github.v3.raw',
                ])->timeout(8)->get("https://api.github.com/repos/{$repo}/contents/{$filePath}", [
                    'ref' => $branch,
                ]);

                if ($response->successful()) {
                    $content = $response->body();
                    $lines = explode("\n", $content);
                    $fileSnippets[$filePath] = implode("\n", array_slice($lines, 0, 80));
                }
            } catch (\Exception $e) {
                Log::debug("[DriftWatch] Could not fetch file {$filePath}: {$e->getMessage()}");
            }
        }

        // Also add dep graph source files not in affected_files
        foreach (array_keys($depGraph) as $srcFile) {
            if (! isset($fileSnippets[$srcFile]) && count($fileSnippets) < 25) {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => "Bearer {$ghToken}",
                        'Accept' => 'application/vnd.github.v3.raw',
                    ])->timeout(8)->get("https://api.github.com/repos/{$repo}/contents/{$srcFile}", [
                        'ref' => $branch,
                    ]);

                    if ($response->successful()) {
                        $content = $response->body();
                        $lines = explode("\n", $content);
                        $fileSnippets[$srcFile] = implode("\n", array_slice($lines, 0, 80));
                    }
                } catch (\Exception $e) {
                    Log::debug("[DriftWatch] Could not fetch source file {$srcFile}: {$e->getMessage()}");
                }
            }
        }

        if (empty($fileSnippets)) {
            Log::info("[DriftWatch] No file snippets fetched from GitHub for PR #{$pullRequest->pr_number}. Using heuristic descriptions.");

            return $this->generateHeuristicDescriptions($affectedFiles, $depGraph);
        }

        // Build the prompt for Azure OpenAI
        $depGraphJson = json_encode($depGraph);
        $filesBlock = '';
        foreach ($fileSnippets as $path => $snippet) {
            $deps = isset($depGraph[$path]) ? json_encode($depGraph[$path]) : '[]';
            $filesBlock .= "---\nFILE: {$path}\nDEPENDENTS: {$deps}\n```\n{$snippet}\n```\n\n";
        }

        $prompt = <<<PROMPT
You are a DevOps risk analyst AI. Analyze these source code files from a GitHub pull request and generate a JSON object describing each file.

For each file, provide:
- "summary": 1-2 sentence plain-English description of what this file does (non-technical, a PM should understand)
- "role": The file's role in the system (e.g., "API Controller", "Data Model", "View Template", "Configuration", "Service Layer", "Middleware", "Database Migration", "Test Suite", "Utility", "Worker/Job")
- "risk": Why changing this file is risky, or "Low risk" if safe (1 sentence)
- "affects": How changes to this file could impact other files that depend on it (1 sentence). Reference specific dependents if known.

Dependency graph (source file → files that depend on it):
{$depGraphJson}

Files to analyze:
{$filesBlock}

Respond with ONLY a valid JSON object where keys are file paths and values are objects with {summary, role, risk, affects}. No markdown, no explanation, just the JSON.
PROMPT;

        // Call Azure OpenAI
        try {
            $endpoint = config('services.azure_openai.endpoint');
            $apiKey = config('services.azure_openai.api_key');
            $deployment = config('services.azure_openai.deployment');

            if (! $endpoint || ! $apiKey) {
                Log::warning('[DriftWatch] Azure OpenAI not configured. Using heuristic descriptions.');

                return $this->generateHeuristicDescriptions($affectedFiles, $depGraph);
            }

            $url = rtrim($endpoint, '/')."/openai/deployments/{$deployment}/chat/completions?api-version=2024-12-01-preview";

            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a DevOps risk analyst. Respond only with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 4000,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';

                // Strip markdown code fence if present
                $content = preg_replace('/^```json\s*\n?/', '', $content);
                $content = preg_replace('/\n?```\s*$/', '', $content);

                $descriptions = json_decode($content, true);

                if (is_array($descriptions) && ! empty($descriptions)) {
                    Log::info('[DriftWatch] AI generated descriptions for '.count($descriptions)." files in PR #{$pullRequest->pr_number}.");

                    return $descriptions;
                }

                Log::warning('[DriftWatch] Azure OpenAI returned invalid JSON for file descriptions.', [
                    'content' => substr($content, 0, 500),
                ]);
            } else {
                Log::warning('[DriftWatch] Azure OpenAI API error for file descriptions.', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[DriftWatch] Azure OpenAI call failed for file descriptions.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->generateHeuristicDescriptions($affectedFiles, $depGraph);
    }

    /**
     * Fallback: generate heuristic descriptions when AI is unavailable.
     *
     * @return array<string, array{summary: string, role: string, risk: string, affects: string}>
     */
    private function generateHeuristicDescriptions(array $files, array $depGraph): array
    {
        $descriptions = [];

        foreach ($files as $filePath) {
            $name = basename($filePath);
            $dir = dirname($filePath);
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $nameLower = strtolower($name);
            $deps = $depGraph[$filePath] ?? [];
            $depCount = is_array($deps) ? count($deps) : 0;

            // Determine role
            $role = match (true) {
                str_contains($nameLower, 'controller') => 'API Controller',
                str_contains($nameLower, 'route') || str_contains($nameLower, 'router') => 'Routing Configuration',
                str_contains($nameLower, 'model') || str_contains($nameLower, 'schema') => 'Data Model',
                str_contains($nameLower, 'migration') => 'Database Migration',
                str_contains($nameLower, 'middleware') => 'Middleware',
                str_contains($nameLower, 'service') || str_contains($nameLower, 'provider') => 'Service Layer',
                str_contains($nameLower, 'test') || str_contains($nameLower, 'spec') => 'Test Suite',
                str_contains($nameLower, 'util') || str_contains($nameLower, 'helper') => 'Utility',
                str_contains($nameLower, 'worker') || str_contains($nameLower, 'job') || str_contains($nameLower, 'queue') => 'Background Worker',
                str_contains($dir, 'view') || str_contains($dir, 'template') || $ext === 'blade.php' => 'View Template',
                in_array($ext, ['json', 'yaml', 'yml', 'toml', 'ini', 'env']) || str_contains($nameLower, 'config') => 'Configuration',
                in_array($ext, ['css', 'scss', 'less']) => 'Styles',
                default => ucfirst($ext).' Module',
            };

            // Build summary
            $summary = "Handles {$role} logic in the ".basename($dir).' directory.';

            // Build risk assessment
            $risk = match (true) {
                str_contains($nameLower, 'migration') => 'Database changes are irreversible in production and affect all environments.',
                str_contains($nameLower, 'auth') || str_contains($nameLower, 'security') => 'Security-sensitive file — changes could create authentication or authorization vulnerabilities.',
                str_contains($nameLower, 'config') || in_array($ext, ['json', 'yaml', 'yml', 'env']) => 'Configuration changes propagate to all environments and can cause widespread failures.',
                $depCount >= 5 => "High-impact file with {$depCount} downstream dependencies. A breaking change here cascades widely.",
                $depCount >= 2 => "Moderate risk — {$depCount} other files depend on this module.",
                str_contains($nameLower, 'controller') || str_contains($nameLower, 'route') => 'API-facing file — changes may break client contracts or external integrations.',
                default => 'Low risk — isolated changes with limited downstream impact.',
            };

            // Build affects description
            $affects = $depCount > 0
                ? "Changes here directly impact {$depCount} downstream file(s): ".implode(', ', array_map('basename', array_slice($deps, 0, 4))).(count($deps) > 4 ? ' and more' : '').'.'
                : 'No known downstream dependencies — changes are relatively isolated.';

            $descriptions[$filePath] = [
                'summary' => $summary,
                'role' => $role,
                'risk' => $risk,
                'affects' => $affects,
            ];
        }

        // Also add descriptions for source files in dep graph not in affected_files
        foreach (array_keys($depGraph) as $srcFile) {
            if (! isset($descriptions[$srcFile])) {
                $deps = $depGraph[$srcFile] ?? [];
                $depCount = is_array($deps) ? count($deps) : 0;
                $descriptions[$srcFile] = [
                    'summary' => 'Source file that was directly changed in this PR.',
                    'role' => ucfirst(pathinfo($srcFile, PATHINFO_EXTENSION)).' Module',
                    'risk' => $depCount >= 3 ? "High fan-out: {$depCount} files depend on this." : 'Moderate — directly changed.',
                    'affects' => $depCount > 0
                        ? "Impacts {$depCount} downstream files: ".implode(', ', array_map('basename', array_slice($deps, 0, 4))).'.'
                        : 'No known downstream dependencies.',
                ];
            }
        }

        return $descriptions;
    }
}
