{{-- resources/views/driftwatch/index.blade.php --}}
{{-- Main DriftWatch dashboard - analyze PRs, view risk scores, track decisions --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Overview</span>
    </li>
@endsection

@section('content')
    {{-- Analyze a PR - the primary action --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <h4 class="fw-bold mb-1">
                        <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 28px;">science</span>
                        Analyze a Pull Request
                    </h4>
                    <p class="text-secondary mb-0 fs-14">Paste any GitHub PR URL to run the full AI agent pipeline — blast radius, risk score, and deploy decision in seconds.</p>
                </div>
                <div class="col-lg-7">
                    <form action="{{ route('driftwatch.analyze') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <div class="flex-grow-1">
                            <input type="url" name="pr_url" class="form-control form-control-lg @error('pr_url') is-invalid @enderror"
                                   placeholder="https://github.com/owner/repo/pull/123"
                                   value="{{ old('pr_url') }}" required>
                            @error('pr_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg px-4 d-flex align-items-center gap-2" id="analyzeBtn">
                            <span class="material-symbols-outlined">play_arrow</span>
                            <span>Analyze</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- How It Works - visual pipeline with PNG agent icons --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 text-center">How DriftWatch Works</h5>
            <div class="row align-items-start">
                <div class="col mb-3 mb-md-0 text-center">
                    <div class="p-3">
                        <img src="{{ url('/assets/images/agents/archaeologist.png') }}" alt="Archaeologist" class="mb-3 rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                        <h6 class="fw-bold mb-1">1. Archaeologist</h6>
                        <p class="text-secondary fs-13 mb-0">Maps the blast radius — which files, services, and endpoints your PR touches</p>
                    </div>
                </div>
                <div class="col-auto d-none d-md-flex align-items-center" style="padding-top: 20px;">
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 32px; height: 32px; opacity: 0.6;">
                </div>
                <div class="col mb-3 mb-md-0 text-center">
                    <div class="p-3">
                        <img src="{{ url('/assets/images/agents/historian.png') }}" alt="Historian" class="mb-3 rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                        <h6 class="fw-bold mb-1">2. Historian</h6>
                        <p class="text-secondary fs-13 mb-0">Correlates with past incidents to produce a risk score from 0-100</p>
                    </div>
                </div>
                <div class="col-auto d-none d-md-flex align-items-center" style="padding-top: 20px;">
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 32px; height: 32px; opacity: 0.6;">
                </div>
                <div class="col mb-3 mb-md-0 text-center">
                    <div class="p-3">
                        <img src="{{ url('/assets/images/agents/negotiation.png') }}" alt="Negotiator" class="mb-3 rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                        <h6 class="fw-bold mb-1">3. Negotiator</h6>
                        <p class="text-secondary fs-13 mb-0">Makes the deploy/block/review decision and posts a comment on your PR</p>
                    </div>
                </div>
                <div class="col-auto d-none d-md-flex align-items-center" style="padding-top: 20px;">
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 32px; height: 32px; opacity: 0.6;">
                </div>
                <div class="col text-center">
                    <div class="p-3">
                        <img src="{{ url('/assets/images/agents/chronicle.png') }}" alt="Chronicler" class="mb-3 rounded-circle" style="width: 72px; height: 72px; object-fit: cover;">
                        <h6 class="fw-bold mb-1">4. Chronicler</h6>
                        <p class="text-secondary fs-13 mb-0">Tracks outcomes after deploy to improve future predictions</p>
                    </div>
                </div>
            </div>
            {{-- Pipeline flow labels --}}
            <div class="d-none d-md-flex justify-content-center align-items-center mt-3 pt-3 border-top">
                <div class="d-flex align-items-center gap-3 flex-wrap justify-content-center">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-13">PR diff</span>
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 18px; height: 18px; opacity: 0.5;">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-13">Blast radius</span>
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 18px; height: 18px; opacity: 0.5;">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-13">Risk score</span>
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 18px; height: 18px; opacity: 0.5;">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-13">Decision</span>
                    <img src="{{ url('/assets/images/agents/forward_hires.png') }}" alt="arrow" style="width: 18px; height: 18px; opacity: 0.5;">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-13">Feedback loop</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block mb-1 fs-14 text-secondary">PRs Analyzed</span>
                            <h3 class="mb-0 fs-2 fw-bold">{{ $stats['total_analyzed'] }}</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-primary">merge_type</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block mb-1 fs-14 text-secondary">Avg Risk Score</span>
                            <h3 class="mb-0 fs-2 fw-bold">
                                <span class="text-{{ $stats['avg_risk_score'] >= 76 ? 'danger' : ($stats['avg_risk_score'] >= 51 ? 'warning' : 'primary') }}">
                                    {{ $stats['avg_risk_score'] }}
                                </span>
                                <span class="fs-14 fw-normal text-secondary">/100</span>
                            </h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-warning">speed</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block mb-1 fs-14 text-secondary">Deploys Blocked</span>
                            <h3 class="mb-0 fs-2 fw-bold text-danger">{{ $stats['incidents_prevented'] }}</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-danger">shield</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block mb-1 fs-14 text-secondary">Prediction Accuracy</span>
                            <h3 class="mb-0 fs-2 fw-bold text-primary">{{ $stats['prediction_accuracy'] ?: '—' }}{{ $stats['prediction_accuracy'] ? '%' : '' }}</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-primary">verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="row mb-4">
        {{-- Risk Score Distribution --}}
        <div class="col-xl-7 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">Risk Score by PR</h5>
                    <p class="text-secondary fs-13 mb-3">Each bar shows the risk score for a recently analyzed PR.</p>
                    <div id="riskDistributionChart" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>

        {{-- Decision Breakdown --}}
        <div class="col-xl-5 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">Decision Breakdown</h5>
                    <p class="text-secondary fs-13 mb-3">How DriftWatch has decided on all analyzed PRs so far.</p>
                    <div id="decisionBreakdownChart" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Pull Requests --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-0 fw-bold">Recent Pull Requests</h4>
                    <p class="text-secondary fs-13 mb-0">Click any PR to see its full blast radius analysis and agent pipeline results.</p>
                </div>
                <a href="{{ route('driftwatch.pull-requests') }}" class="btn btn-outline-primary btn-sm">
                    View All
                </a>
            </div>

            @if($pullRequests->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="fw-medium text-secondary">PR</th>
                                <th class="fw-medium text-secondary">Title</th>
                                <th class="fw-medium text-secondary">Author</th>
                                <th class="fw-medium text-secondary">Risk</th>
                                <th class="fw-medium text-secondary">Decision</th>
                                <th class="fw-medium text-secondary">Changes</th>
                                <th class="fw-medium text-secondary"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pullRequests as $pr)
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">#{{ $pr->pr_number }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="text-decoration-none fw-medium">
                                            {{ Str::limit($pr->pr_title, 50) }}
                                        </a>
                                        <br>
                                        <small class="text-secondary">{{ $pr->repo_full_name }}</small>
                                    </td>
                                    <td>
                                        <span class="text-secondary">{{ $pr->pr_author }}</span>
                                    </td>
                                    <td>
                                        @if($pr->riskAssessment)
                                            <span class="badge bg-{{ $pr->risk_color }} bg-opacity-10 text-{{ $pr->risk_color }} fw-bold px-3 py-2 fs-14">
                                                {{ $pr->riskAssessment->risk_score }}/100
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pr->deploymentDecision)
                                            <span class="badge bg-{{ $pr->deploymentDecision->decision_color }} bg-opacity-10 text-{{ $pr->deploymentDecision->decision_color }} px-3 py-2 text-capitalize">
                                                @if($pr->deploymentDecision->decision === 'approved')
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 14px;">check_circle</span>
                                                @elseif($pr->deploymentDecision->decision === 'blocked')
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 14px;">cancel</span>
                                                @else
                                                    <span class="material-symbols-outlined align-middle" style="font-size: 14px;">pending</span>
                                                @endif
                                                {{ str_replace('_', ' ', $pr->deploymentDecision->decision) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-success fs-13">+{{ $pr->additions }}</span>
                                        <span class="text-danger fs-13">-{{ $pr->deletions }}</span>
                                        <br>
                                        <small class="text-secondary">{{ $pr->files_changed }} files</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined fs-16">visibility</span> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $pullRequests->links() }}
            @else
                <div class="text-center py-5">
                    <span class="material-symbols-outlined d-block mb-3 text-secondary" style="font-size: 64px;">science</span>
                    <h5 class="fw-bold mb-2">No PRs analyzed yet</h5>
                    <p class="text-secondary mb-3">Paste a GitHub PR URL above to get started, or set up a webhook for automatic analysis.</p>
                    <a href="{{ route('driftwatch.settings') }}" class="btn btn-outline-primary">
                        <span class="material-symbols-outlined align-middle me-1">settings</span> View Setup Guide
                    </a>
                </div>
            @endif
        </div>
    </div>
    {{-- Agent Pipeline Loading Overlay --}}
    <div id="agentLoadingOverlay" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(10,15,30,0.92); backdrop-filter:blur(8px);">
        <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white px-4">
            <div class="mb-4">
                <span class="material-symbols-outlined" style="font-size:48px; color:#605DFF;" id="loadingMainIcon">science</span>
            </div>
            <h3 class="fw-bold mb-2" id="loadingTitle">Initializing Agent Pipeline</h3>
            <p class="text-white-50 fs-14 mb-4" id="loadingSubtitle">Connecting to Azure Functions...</p>

            {{-- Gradient progress bar --}}
            <div style="width:100%; max-width:520px; margin-bottom:32px;">
                <div style="height:6px; border-radius:3px; background:rgba(255,255,255,0.08); overflow:hidden; position:relative;">
                    <div id="agentProgressBar" style="height:100%; width:0%; border-radius:3px; background:linear-gradient(90deg, #605DFF, #3B82F6, #06B6D4, #10B981); transition: width 1.2s cubic-bezier(0.4,0,0.2,1);"></div>
                </div>
            </div>

            {{-- Agent stages --}}
            <div style="width:100%; max-width:520px;">
                <div class="d-flex align-items-center gap-3 mb-3 agent-stage" id="stage-archaeologist">
                    <div class="flex-shrink-0" style="width:44px; height:44px;">
                        <img src="{{ url('/assets/images/agents/archaeologist.png') }}" alt="" class="rounded-circle" style="width:44px; height:44px; object-fit:cover; opacity:0.3; transition:all 0.5s;" id="img-archaeologist">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-14" style="opacity:0.4; transition:opacity 0.5s;" id="label-archaeologist">Archaeologist</span>
                            <span class="fs-12 text-white-50" id="status-archaeologist">Waiting...</span>
                        </div>
                        <div style="height:3px; border-radius:2px; background:rgba(255,255,255,0.06); margin-top:6px; overflow:hidden;">
                            <div id="bar-archaeologist" style="height:100%; width:0%; border-radius:2px; background:#605DFF; transition:width 2s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mb-3 agent-stage" id="stage-historian">
                    <div class="flex-shrink-0" style="width:44px; height:44px;">
                        <img src="{{ url('/assets/images/agents/historian.png') }}" alt="" class="rounded-circle" style="width:44px; height:44px; object-fit:cover; opacity:0.3; transition:all 0.5s;" id="img-historian">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-14" style="opacity:0.4; transition:opacity 0.5s;" id="label-historian">Historian</span>
                            <span class="fs-12 text-white-50" id="status-historian">Waiting...</span>
                        </div>
                        <div style="height:3px; border-radius:2px; background:rgba(255,255,255,0.06); margin-top:6px; overflow:hidden;">
                            <div id="bar-historian" style="height:100%; width:0%; border-radius:2px; background:#F59E0B; transition:width 2s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mb-3 agent-stage" id="stage-negotiator">
                    <div class="flex-shrink-0" style="width:44px; height:44px;">
                        <img src="{{ url('/assets/images/agents/negotiation.png') }}" alt="" class="rounded-circle" style="width:44px; height:44px; object-fit:cover; opacity:0.3; transition:all 0.5s;" id="img-negotiator">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-14" style="opacity:0.4; transition:opacity 0.5s;" id="label-negotiator">Negotiator</span>
                            <span class="fs-12 text-white-50" id="status-negotiator">Waiting...</span>
                        </div>
                        <div style="height:3px; border-radius:2px; background:rgba(255,255,255,0.06); margin-top:6px; overflow:hidden;">
                            <div id="bar-negotiator" style="height:100%; width:0%; border-radius:2px; background:#EF4444; transition:width 2s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 agent-stage" id="stage-chronicler">
                    <div class="flex-shrink-0" style="width:44px; height:44px;">
                        <img src="{{ url('/assets/images/agents/chronicle.png') }}" alt="" class="rounded-circle" style="width:44px; height:44px; object-fit:cover; opacity:0.3; transition:all 0.5s;" id="img-chronicler">
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-14" style="opacity:0.4; transition:opacity 0.5s;" id="label-chronicler">Chronicler</span>
                            <span class="fs-12 text-white-50" id="status-chronicler">Waiting...</span>
                        </div>
                        <div style="height:3px; border-radius:2px; background:rgba(255,255,255,0.06); margin-top:6px; overflow:hidden;">
                            <div id="bar-chronicler" style="height:100%; width:0%; border-radius:2px; background:#10B981; transition:width 2s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-white-50 fs-13 mt-4" id="loadingElapsed">0s elapsed</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // === Agent Pipeline Loading Animation ===
    var form = document.querySelector('form[action*="analyze"]');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('analyzeBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Analyzing...';

            // Show the overlay
            var overlay = document.getElementById('agentLoadingOverlay');
            if (!overlay) return;
            overlay.style.display = 'block';
            overlay.style.opacity = '0';
            requestAnimationFrame(function() {
                overlay.style.transition = 'opacity 0.4s ease';
                overlay.style.opacity = '1';
            });

            // Elapsed timer
            var startTime = Date.now();
            var elapsedEl = document.getElementById('loadingElapsed');
            var elapsedTimer = setInterval(function() {
                var secs = Math.floor((Date.now() - startTime) / 1000);
                if (elapsedEl) elapsedEl.textContent = secs + 's elapsed';
            }, 1000);

            // Agent stages config
            var stages = [
                { key: 'archaeologist', title: 'Mapping Blast Radius', subtitle: 'Archaeologist is scanning file dependencies, services, and API endpoints...', icon: 'explore', delay: 300, duration: 2500 },
                { key: 'historian', title: 'Calculating Risk Score', subtitle: 'Historian is correlating with past incidents and computing risk factors...', icon: 'history', delay: 3000, duration: 2500 },
                { key: 'negotiator', title: 'Making Deploy Decision', subtitle: 'Negotiator is weighing risk vs velocity and determining the gate decision...', icon: 'gavel', delay: 5800, duration: 2000 },
                { key: 'chronicler', title: 'Recording Feedback Loop', subtitle: 'Chronicler is capturing this analysis for future learning...', icon: 'auto_stories', delay: 8000, duration: 1500 }
            ];

            var progressBar = document.getElementById('agentProgressBar');
            var titleEl = document.getElementById('loadingTitle');
            var subtitleEl = document.getElementById('loadingSubtitle');
            var mainIcon = document.getElementById('loadingMainIcon');

            stages.forEach(function(stage, idx) {
                // Start the stage
                setTimeout(function() {
                    // Activate this agent's row
                    var img = document.getElementById('img-' + stage.key);
                    var label = document.getElementById('label-' + stage.key);
                    var bar = document.getElementById('bar-' + stage.key);
                    var status = document.getElementById('status-' + stage.key);

                    if (img) { img.style.opacity = '1'; img.style.boxShadow = '0 0 16px rgba(96,93,255,0.4)'; }
                    if (label) label.style.opacity = '1';
                    if (status) { status.textContent = 'Processing...'; status.style.color = '#60a5fa'; }

                    // Fill this agent's bar
                    requestAnimationFrame(function() {
                        if (bar) bar.style.width = '100%';
                    });

                    // Update main title/subtitle
                    if (titleEl) titleEl.textContent = stage.title;
                    if (subtitleEl) subtitleEl.textContent = stage.subtitle;
                    if (mainIcon) mainIcon.textContent = stage.icon;

                    // Update overall progress
                    var progress = Math.round(((idx + 0.5) / stages.length) * 100);
                    if (progressBar) progressBar.style.width = progress + '%';
                }, stage.delay);

                // Complete the stage
                setTimeout(function() {
                    var status = document.getElementById('status-' + stage.key);
                    var img = document.getElementById('img-' + stage.key);
                    if (status) { status.textContent = 'Complete'; status.style.color = '#10B981'; }
                    if (img) img.style.boxShadow = '0 0 12px rgba(16,185,129,0.3)';

                    var progress = Math.round(((idx + 1) / stages.length) * 100);
                    if (progressBar) progressBar.style.width = progress + '%';

                    // Final stage complete
                    if (idx === stages.length - 1) {
                        setTimeout(function() {
                            if (titleEl) titleEl.textContent = 'Analysis Complete';
                            if (subtitleEl) subtitleEl.textContent = 'Redirecting to results...';
                            if (mainIcon) mainIcon.textContent = 'check_circle';
                            mainIcon.style.color = '#10B981';
                        }, 400);
                    }
                }, stage.delay + stage.duration);
            });
        });
    }

    // Risk Score Distribution bar chart
    @php
        $riskData = \App\Models\RiskAssessment::orderBy('created_at', 'desc')->take(10)->get();
        $riskLabels = $riskData->map(function($r) {
            $pr = $r->pullRequest;
            return $pr ? 'PR #' . $pr->pr_number : 'PR';
        })->reverse()->values();
        $riskScores = $riskData->pluck('risk_score')->reverse()->values();
        $riskColors = $riskData->map(function($r) {
            if ($r->risk_score >= 75) return '#EF4444';
            if ($r->risk_score >= 50) return '#F59E0B';
            if ($r->risk_score >= 25) return '#3B82F6';
            return '#605DFF';
        })->reverse()->values();
    @endphp

    @if($riskScores->count() > 0)
    new ApexCharts(document.querySelector("#riskDistributionChart"), {
        series: [{ name: 'Risk Score', data: @json($riskScores) }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
        colors: @json($riskColors),
        plotOptions: {
            bar: { borderRadius: 6, columnWidth: '55%', distributed: true }
        },
        dataLabels: {
            enabled: true,
            formatter: function(val) { return val + '/100'; },
            style: { fontSize: '11px', fontWeight: 600 }
        },
        legend: { show: false },
        xaxis: { categories: @json($riskLabels), labels: { style: { fontSize: '11px' } } },
        yaxis: { max: 100, labels: { formatter: function(val) { return Math.round(val); } } },
        tooltip: { y: { formatter: function(val) { return val + ' / 100'; } } },
        annotations: {
            yaxis: [
                { y: 75, borderColor: '#EF4444', strokeDashArray: 4, label: { text: 'Block threshold', style: { color: '#EF4444', background: 'transparent', fontSize: '10px' } } },
                { y: 50, borderColor: '#F59E0B', strokeDashArray: 4, label: { text: 'Review threshold', style: { color: '#F59E0B', background: 'transparent', fontSize: '10px' } } }
            ]
        }
    }).render();
    @else
    document.querySelector("#riskDistributionChart").innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-secondary"><div class="text-center"><span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">bar_chart</span>Analyze a PR to see risk scores here</div></div>';
    @endif

    // Decision Breakdown donut
    @php
        $decisions = \App\Models\DeploymentDecision::selectRaw("decision, count(*) as total")
            ->groupBy('decision')
            ->pluck('total', 'decision');
        $hasDecisions = $decisions->sum() > 0;
    @endphp

    @if($hasDecisions)
    new ApexCharts(document.querySelector("#decisionBreakdownChart"), {
        series: [{{ $decisions->get('approved', 0) }}, {{ $decisions->get('pending_review', 0) }}, {{ $decisions->get('blocked', 0) }}],
        chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
        labels: ['Approved', 'Pending Review', 'Blocked'],
        colors: ['#605DFF', '#F59E0B', '#EF4444'],
        plotOptions: {
            pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total PRs', fontSize: '13px', fontWeight: 600 } } } }
        },
        dataLabels: { enabled: true, formatter: function(val, opts) { return opts.w.config.series[opts.seriesIndex]; } },
        legend: { position: 'bottom', fontSize: '13px' }
    }).render();
    @else
    document.querySelector("#decisionBreakdownChart").innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-secondary"><div class="text-center"><span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">donut_large</span>Decisions will appear here after analysis</div></div>';
    @endif
});
</script>
@endpush
