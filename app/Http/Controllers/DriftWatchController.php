<?php
// app/Http/Controllers/DriftWatchController.php
// Main dashboard controller for DriftWatch - handles all dashboard pages,
// PR detail views, approve/block actions, and analytics.

namespace App\Http\Controllers;

use App\Models\DeploymentDecision;
use App\Models\DeploymentOutcome;
use App\Models\Incident;
use App\Models\PullRequest;
use App\Models\RiskAssessment;
use Illuminate\Http\Request;
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
