{{-- resources/views/driftwatch/explainability.blade.php --}}
{{-- Explainability page — how DriftWatch scoring and pipeline decisions work --}}
@extends('layouts.app')

@section('title', 'Explainability')
@section('heading', 'How Scoring Works')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Explainability</span>
    </li>
@endsection

@push('styles')
<style>
    .explain-card { border: 1px solid rgba(0,0,0,0.04) !important; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 4px 16px rgba(0,0,0,0.03); transition: box-shadow 0.2s; }
    .explain-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06), 0 8px 24px rgba(0,0,0,0.06); }
    .explain-section-title { position: relative; display: inline-flex; align-items: center; gap: 8px; padding-bottom: 8px; }
    .explain-section-title::after { content: ''; position: absolute; bottom: 0; left: 0; width: 32px; height: 3px; border-radius: 2px; background: #605DFF; }
    .score-bar { height: 24px; border-radius: 6px; font-size: 11px; font-weight: 600; display: flex; align-items: center; padding: 0 10px; color: #fff; }
    .flow-step { padding: 16px; border-radius: 12px; border: 2px solid #e2e8f0; position: relative; }
    .flow-step::after { content: ''; position: absolute; right: -20px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-top: 8px solid transparent; border-bottom: 8px solid transparent; border-left: 10px solid #605DFF; }
    .flow-step:last-child::after { display: none; }
    .threshold-row { padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 8px; }
    .gate-reason { background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; padding: 12px 16px; }
    [data-theme=dark] .gate-reason { background: #422006; border-color: #92400e; }
    [data-theme=dark] .flow-step { border-color: #334155; }
    [data-theme=dark] .threshold-row { border-color: #334155; }
</style>
@endpush

@section('content')
    {{-- Hero Banner --}}
    <div class="card bg-white border-0 rounded-3 mb-4 explain-card" style="background: linear-gradient(135deg, #605DFF 0%, #8B5CF6 100%) !important;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="wh-60 bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                    <span class="material-symbols-outlined text-white" style="font-size: 28px;">school</span>
                </div>
                <div>
                    <h4 class="fw-bold text-white mb-1">How DriftWatch Scores Your PRs</h4>
                    <p class="text-white text-opacity-75 mb-0 fs-14">Transparent breakdown of the scoring mechanism, pipeline gates, and decision logic.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 4-Agent Pipeline Flow --}}
    <div class="card bg-white border-0 rounded-3 mb-4 explain-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 explain-section-title">
                <span class="material-symbols-outlined align-middle text-primary" style="font-size: 22px;">route</span>
                The 4-Agent Pipeline
            </h5>
            <p class="text-secondary fs-13 mb-4">When a PR is submitted, DriftWatch runs 4 sequential AI agents. Each agent builds on the previous one's findings.</p>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="flow-step text-center">
                        <div class="wh-44 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-primary" style="font-size:22px;">explore</span>
                        </div>
                        <h6 class="fw-bold fs-14 mb-1">1. Archaeologist</h6>
                        <p class="text-secondary fs-12 mb-0">Fetches PR diff from GitHub. Classifies every changed file by type (migration, auth, config, etc.) and assigns a <strong>blast radius score</strong> (0-100+).</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="flow-step text-center">
                        <div class="wh-44 bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-warning" style="font-size:22px;">history</span>
                        </div>
                        <h6 class="fw-bold fs-14 mb-1">2. Historian</h6>
                        <p class="text-secondary fs-12 mb-0">Correlates blast radius with past incidents. Checks if files, services, or change types have caused problems before. Produces the <strong>risk score</strong> (0-100).</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="flow-step text-center">
                        <div class="wh-44 bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-danger" style="font-size:22px;">gavel</span>
                        </div>
                        <h6 class="fw-bold fs-14 mb-1">3. Negotiator</h6>
                        <p class="text-secondary fs-12 mb-0">Makes the final <strong>deploy/block decision</strong>. Checks weather score, applies pipeline gates, posts a PR comment to GitHub, and sends Teams alerts.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="flow-step text-center" style="border-style: dashed;">
                        <div class="wh-44 bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                            <span class="material-symbols-outlined text-success" style="font-size:22px;">auto_stories</span>
                        </div>
                        <h6 class="fw-bold fs-14 mb-1">4. Chronicler</h6>
                        <p class="text-secondary fs-12 mb-0">Records the prediction for future accuracy tracking. Compares predictions to actual deploy outcomes (feedback loop).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Score Composition --}}
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 explain-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 explain-section-title">
                        <span class="material-symbols-outlined align-middle text-danger" style="font-size: 22px;">target</span>
                        Blast Radius Score (Archaeologist)
                    </h5>
                    <p class="text-secondary fs-13 mb-3">Each changed file contributes points based on its type and risk level.</p>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Auth / Middleware changes</span>
                            <span class="text-danger">+30 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-danger" style="width:100%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Database migrations</span>
                            <span class="text-danger">+25 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-danger" style="width:83%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Service / Provider files</span>
                            <span class="text-warning">+20 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-warning" style="width:67%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Config files</span>
                            <span class="text-warning">+20 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-warning" style="width:67%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Controllers / Routing</span>
                            <span class="text-info">+15 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-info" style="width:50%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Models</span>
                            <span class="text-info">+15 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-info" style="width:50%"></div></div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Views / CSS / Assets</span>
                            <span class="text-success">+2 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-success" style="width:7%"></div></div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between fs-12 fw-medium mb-1">
                            <span>Tests / Specs</span>
                            <span class="text-success">+2 pts</span>
                        </div>
                        <div class="progress" style="height:8px;"><div class="progress-bar bg-success" style="width:7%"></div></div>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-secondary">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;">info</span>
                            Additional: <strong>+25 pts</strong> for failing CI checks, up to <strong>+10 pts</strong> for bot findings (Snyk, Dependabot, etc.), and <strong>+5 pts</strong> per detected function signature change.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 explain-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 explain-section-title">
                        <span class="material-symbols-outlined align-middle text-warning" style="font-size: 22px;">assessment</span>
                        Risk Score (Historian)
                    </h5>
                    <p class="text-secondary fs-13 mb-3">The Historian uses 3-layer matching against your incident history to calculate risk (0-100).</p>

                    <div class="mb-4">
                        <h6 class="fs-13 fw-bold mb-2">Layer 1: File Overlap (max 25 pts)</h6>
                        <p class="text-secondary fs-12 mb-0">If any changed file was involved in a past incident, +25 points. The more files overlap, the higher the score.</p>
                    </div>
                    <div class="mb-4">
                        <h6 class="fs-13 fw-bold mb-2">Layer 2: Service Correlation (max 10 pts)</h6>
                        <p class="text-secondary fs-12 mb-0">If affected services match services involved in past incidents, +10 points. Cross-service impact is tracked.</p>
                    </div>
                    <div class="mb-4">
                        <h6 class="fs-13 fw-bold mb-2">Layer 3: Change-Type Matching (max 15 pts)</h6>
                        <p class="text-secondary fs-12 mb-0">If the type of change (migration, auth, config) matches change types that previously caused incidents, +15 points.</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="fs-13 fw-bold mb-2">History Cap: max 40 pts from incident history</h6>
                        <p class="text-secondary fs-12 mb-0">The remaining 60 pts come from the blast radius score (scaled). Final formula:</p>
                    </div>

                    <div class="p-3 rounded-3 mb-3" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #e2e8f0;">
                        <code class="fs-13" style="color: #a5b4fc;">
                            risk_score = min(100, history_score + (blast_radius_score * 0.6))
                        </code>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <div class="text-center p-2 rounded-3 flex-fill" style="background:#dcfce7;"><span class="fw-bold fs-14 text-success">0-20</span><br><span class="fs-11 text-secondary">Low Risk</span></div>
                        <div class="text-center p-2 rounded-3 flex-fill" style="background:#fef9c3;"><span class="fw-bold fs-14 text-warning">21-40</span><br><span class="fs-11 text-secondary">Medium</span></div>
                        <div class="text-center p-2 rounded-3 flex-fill" style="background:#ffedd5;"><span class="fw-bold fs-14" style="color:#ea580c;">41-60</span><br><span class="fs-11 text-secondary">Elevated</span></div>
                        <div class="text-center p-2 rounded-3 flex-fill" style="background:#fee2e2;"><span class="fw-bold fs-14 text-danger">61-80</span><br><span class="fs-11 text-secondary">High</span></div>
                        <div class="text-center p-2 rounded-3 flex-fill" style="background:#fecaca;"><span class="fw-bold fs-14 text-danger">81-100</span><br><span class="fs-11 text-secondary">Critical</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Pipeline Gates & Pause Logic --}}
    <div class="card bg-white border-0 rounded-3 mb-4 explain-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 explain-section-title">
                <span class="material-symbols-outlined align-middle text-warning" style="font-size: 22px;">front_hand</span>
                Why Does the Pipeline Pause? (Approval Gates)
            </h5>
            <p class="text-secondary fs-13 mb-4">The pipeline can pause between the Historian (scoring) and Negotiator (decision) stages. Here's exactly when and why:</p>

            <div class="gate-reason mb-4">
                <h6 class="fw-bold mb-2">
                    <span class="material-symbols-outlined align-middle text-warning me-1" style="font-size:18px;">warning</span>
                    Why your PR with score 45 paused
                </h6>
                <p class="mb-0 fs-13">Your pipeline template has <strong>environment-specific thresholds</strong>. For <code>production</code>, the threshold is set to <strong>{{ $defaultConfig->environment_thresholds['production']['risk_threshold'] ?? 50 }}</strong> with <code>require_approval = true</code>. Since your risk score (45) exceeds that threshold, the pipeline pauses and waits for human approval before the Negotiator makes its final decision.</p>
            </div>

            <h6 class="fw-bold mb-3">Three reasons a pipeline can pause:</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-danger" style="font-size:18px;">security</span>
                            <span class="fw-bold fs-13">1. Template Gate</span>
                        </div>
                        <p class="text-secondary fs-12 mb-2">If the pipeline template has <code>require_approval_after_scoring = true</code> (like "Gated Deployment"), it ALWAYS pauses unless the score is below <code>auto_approve_below_score</code>.</p>
                        <div class="d-flex justify-content-between fs-12 text-secondary">
                            <span>Current auto-approve below:</span>
                            <span class="fw-bold">{{ $defaultConfig->auto_approve_below_score }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-warning" style="font-size:18px;">dns</span>
                            <span class="fw-bold fs-13">2. Environment Gate</span>
                        </div>
                        <p class="text-secondary fs-12 mb-2">Each environment has its own risk threshold. If the score exceeds it and <code>require_approval = true</code>, the pipeline pauses.</p>
                        @php $thresholds = $defaultConfig->environment_thresholds ?? []; @endphp
                        @foreach(['production', 'staging', 'development'] as $env)
                            @php $ec = $thresholds[$env] ?? ['risk_threshold' => 50, 'require_approval' => false]; @endphp
                            <div class="d-flex justify-content-between fs-12 mb-1">
                                <span class="text-capitalize">{{ $env }}:</span>
                                <span>
                                    threshold <strong>{{ $ec['risk_threshold'] }}</strong>
                                    @if(!empty($ec['require_approval']))
                                        <span class="badge bg-warning bg-opacity-10 text-warning fs-10">gate on</span>
                                    @else
                                        <span class="badge bg-success bg-opacity-10 text-success fs-10">auto</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-info" style="font-size:18px;">rule</span>
                            <span class="fw-bold fs-13">3. Conditional Rules</span>
                        </div>
                        <p class="text-secondary fs-12 mb-2">Certain file patterns can force the pipeline to pause regardless of score (e.g., "always gate migrations").</p>
                        @if(!empty($defaultConfig->conditional_rules))
                            @foreach($defaultConfig->conditional_rules as $rule)
                                @if(($rule['action'] ?? '') === 'force_gate')
                                    <div class="d-flex align-items-center gap-1 fs-12 mb-1">
                                        <span class="material-symbols-outlined text-warning" style="font-size:12px;">lock</span>
                                        <span>{{ $rule['label'] ?? $rule['pattern'] ?? 'Rule' }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <span class="text-secondary fs-12">No force-gate rules configured.</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded-3">
                <h6 class="fw-bold mb-2">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">lightbulb</span>
                    Want it to be more autonomous?
                </h6>
                <p class="fs-13 mb-2">Go to <a href="{{ route('driftwatch.settings') }}" class="fw-bold">Settings</a> and adjust these values:</p>
                <ul class="fs-13 mb-0">
                    <li><strong>Raise <code>auto_approve_below_score</code></strong> — e.g., set it to 50 so any PR with score &le; 50 auto-approves without pausing.</li>
                    <li><strong>Raise the production risk threshold</strong> — e.g., set it to 60 so only truly risky PRs (>60) trigger the gate.</li>
                    <li><strong>Switch to "Full Analysis" template</strong> instead of "Gated Deployment" — Full Analysis has <code>require_approval_after_scoring = false</code> so it only pauses based on environment thresholds.</li>
                    <li><strong>Turn off <code>require_approval</code></strong> for production environment — the pipeline will run fully autonomously.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Deployment Weather --}}
    <div class="card bg-white border-0 rounded-3 mb-4 explain-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 explain-section-title">
                <span class="material-symbols-outlined align-middle text-info" style="font-size: 22px;">thunderstorm</span>
                Deployment Weather Score (max 95 pts)
            </h5>
            <p class="text-secondary fs-13 mb-3">Separate from code risk — this scores the environmental conditions at deploy time.</p>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">Concurrent Deploys <span class="badge bg-danger bg-opacity-10 text-danger fs-10">+20 pts</span></div>
                        <p class="text-secondary fs-12 mb-0">Other PRs approved in last 30 min with overlapping services, or active GitHub Actions workflows.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">Active Incidents <span class="badge bg-danger bg-opacity-10 text-danger fs-10">+30 pts</span></div>
                        <p class="text-secondary fs-12 mb-0">Unresolved incidents exist, especially if they affect the same services as this PR.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">Infrastructure Health <span class="badge bg-warning bg-opacity-10 text-warning fs-10">+20 pts</span></div>
                        <p class="text-secondary fs-12 mb-0">App Insights error rates or recently-resolved incidents indicating instability.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">High Traffic Window <span class="badge bg-warning bg-opacity-10 text-warning fs-10">+15 pts</span></div>
                        <p class="text-secondary fs-12 mb-0">Current time falls within a configured high-traffic window (e.g., Monday mornings).</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">Recent Related Deploy <span class="badge bg-info bg-opacity-10 text-info fs-10">+10 pts</span></div>
                        <p class="text-secondary fs-12 mb-0">Another PR touching same services was deployed in the last 2 hours (stacked deploy risk).</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="threshold-row h-100">
                        <div class="fw-bold fs-13 mb-1">Weather Escalation</div>
                        <p class="text-secondary fs-12 mb-0">If weather score &ge; 40 and the PR was approved, it auto-escalates to <code>pending_review</code> for safety.</p>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <div class="text-center p-2 rounded-3 flex-fill" style="background:#dcfce7;"><span class="fw-bold text-success">0-10</span><br><span class="fs-11 text-secondary">Clear Skies</span></div>
                <div class="text-center p-2 rounded-3 flex-fill" style="background:#fef9c3;"><span class="fw-bold text-warning">11-30</span><br><span class="fs-11 text-secondary">Partly Cloudy</span></div>
                <div class="text-center p-2 rounded-3 flex-fill" style="background:#ffedd5;"><span class="fw-bold" style="color:#ea580c;">31-50</span><br><span class="fs-11 text-secondary">Storm Warning</span></div>
                <div class="text-center p-2 rounded-3 flex-fill" style="background:#fee2e2;"><span class="fw-bold text-danger">51+</span><br><span class="fs-11 text-secondary">Severe Storm</span></div>
            </div>
        </div>
    </div>

    {{-- Decision Flow Summary --}}
    <div class="card bg-white border-0 rounded-3 mb-4 explain-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 explain-section-title">
                <span class="material-symbols-outlined align-middle text-success" style="font-size: 22px;">decision</span>
                Decision Flow Summary
            </h5>
            <div class="mermaid">
flowchart TD
    PR["PR Submitted"] --> A1["Archaeologist<br/>Blast Radius"]
    A1 --> A2["Historian<br/>Risk Score"]
    A2 --> GATE{"Score > Threshold?<br/>Gate enabled?"}
    GATE -->|"No — auto-proceed"| A3["Negotiator<br/>Decision"]
    GATE -->|"Yes — requires approval"| PAUSE["Pipeline Paused<br/>Awaiting Human"]
    PAUSE -->|"Resume clicked"| A3
    PAUSE -->|"Block clicked"| BLOCKED["PR Blocked"]
    A3 --> WEATHER{"Weather Score<br/>>= 40?"}
    WEATHER -->|No| FINAL["Final Decision<br/>Approved / Blocked"]
    WEATHER -->|Yes| ESCALATE["Escalated to<br/>Pending Review"]
    A3 --> A4["Chronicler<br/>Feedback Loop"]

    style PR fill:#24292e,color:#fff
    style A1 fill:#0d6efd,color:#fff
    style A2 fill:#fd7e14,color:#fff
    style GATE fill:#fbbf24,color:#000
    style PAUSE fill:#f87171,color:#fff
    style A3 fill:#dc3545,color:#fff
    style WEATHER fill:#06B6D4,color:#fff
    style FINAL fill:#10B981,color:#fff
    style BLOCKED fill:#991b1b,color:#fff
    style ESCALATE fill:#9333EA,color:#fff
    style A4 fill:#198754,color:#fff
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({ startOnLoad: true, theme: 'base', themeVariables: {
        primaryColor: '#e8f0fe', primaryBorderColor: '#0d6efd', primaryTextColor: '#1a1a2e',
        lineColor: '#6c757d'
    }});
</script>
@endpush
