{{-- resources/views/driftwatch/show.blade.php --}}
{{-- PR detail page - blast radius, risk assessment, deployment decision, timeline --}}
@extends('layouts.app')

@section('title', "PR #{$pullRequest->pr_number}")
@section('heading', "PR #{$pullRequest->pr_number}")

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('driftwatch.pull-requests') }}" class="text-decoration-none">
            <span class="text-secondary fw-medium hover">Pull Requests</span>
        </a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">PR #{{ $pullRequest->pr_number }}</span>
    </li>
@endsection

@section('content')
    {{-- PR Header --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold mb-2">{{ $pullRequest->pr_title }}</h4>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="badge bg-{{ $pullRequest->status_color }} bg-opacity-10 text-{{ $pullRequest->status_color }} px-3 py-2 text-capitalize fs-14">
                            {{ str_replace('_', ' ', $pullRequest->status) }}
                        </span>
                        <span class="text-secondary">
                            <span class="material-symbols-outlined align-middle fs-16">person</span>
                            {{ $pullRequest->pr_author }}
                        </span>
                        <span class="text-secondary">
                            <span class="material-symbols-outlined align-middle fs-16">account_tree</span>
                            {{ $pullRequest->head_branch }} → {{ $pullRequest->base_branch }}
                        </span>
                        <span class="text-secondary">
                            <span class="material-symbols-outlined align-middle fs-16">folder</span>
                            {{ $pullRequest->repo_full_name }}
                        </span>
                    </div>
                    <div class="mt-2 d-flex gap-3">
                        <span class="text-secondary fs-14">
                            <span class="fw-medium text-success">+{{ $pullRequest->additions }}</span> /
                            <span class="fw-medium text-danger">-{{ $pullRequest->deletions }}</span>
                            in {{ $pullRequest->files_changed }} files
                        </span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    @if($pullRequest->deploymentDecision && $pullRequest->deploymentDecision->decision === 'pending_review')
                        <form action="{{ route('driftwatch.approve', $pullRequest) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success" onclick="return confirm('Approve this deployment?')">
                                <span class="material-symbols-outlined align-middle me-1">check_circle</span> Approve
                            </button>
                        </form>
                        <form action="{{ route('driftwatch.block', $pullRequest) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Block this deployment?')">
                                <span class="material-symbols-outlined align-middle me-1">block</span> Block
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('driftwatch.reanalyze', $pullRequest) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning" onclick="return confirm('Re-run all agents on this PR?')">
                            <span class="material-symbols-outlined align-middle me-1">refresh</span> Re-analyze
                        </button>
                    </form>
                    <a href="{{ $pullRequest->pr_url }}" target="_blank" class="btn btn-outline-primary">
                        <span class="material-symbols-outlined align-middle me-1">open_in_new</span> GitHub
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Agent Pipeline Progress Bar --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="fw-bold mb-0">Agent Pipeline</h6>
                @php
                    $stepsComplete = 0;
                    if ($pullRequest->blastRadius) $stepsComplete++;
                    if ($pullRequest->riskAssessment) $stepsComplete++;
                    if ($pullRequest->deploymentDecision) $stepsComplete++;
                    if ($pullRequest->deploymentOutcome) $stepsComplete++;
                @endphp
                <span class="text-secondary fs-13">{{ $stepsComplete }}/4 agents complete</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                {{-- Archaeologist --}}
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="material-symbols-outlined {{ $pullRequest->blastRadius ? 'text-primary' : 'text-secondary' }}" style="font-size: 18px;">explore</span>
                        <span class="fs-13 {{ $pullRequest->blastRadius ? 'fw-medium' : 'text-secondary' }}">Archaeologist</span>
                        @if($pullRequest->blastRadius)
                            <span class="material-symbols-outlined text-success" style="font-size: 16px;">check_circle</span>
                        @endif
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-primary" style="width: {{ $pullRequest->blastRadius ? '100' : '0' }}%"></div>
                    </div>
                </div>
                <span class="material-symbols-outlined text-secondary" style="font-size: 16px;">chevron_right</span>
                {{-- Historian --}}
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="material-symbols-outlined {{ $pullRequest->riskAssessment ? 'text-warning' : 'text-secondary' }}" style="font-size: 18px;">history</span>
                        <span class="fs-13 {{ $pullRequest->riskAssessment ? 'fw-medium' : 'text-secondary' }}">Historian</span>
                        @if($pullRequest->riskAssessment)
                            <span class="material-symbols-outlined text-success" style="font-size: 16px;">check_circle</span>
                        @endif
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-warning" style="width: {{ $pullRequest->riskAssessment ? '100' : '0' }}%"></div>
                    </div>
                </div>
                <span class="material-symbols-outlined text-secondary" style="font-size: 16px;">chevron_right</span>
                {{-- Negotiator --}}
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="material-symbols-outlined {{ $pullRequest->deploymentDecision ? 'text-danger' : 'text-secondary' }}" style="font-size: 18px;">gavel</span>
                        <span class="fs-13 {{ $pullRequest->deploymentDecision ? 'fw-medium' : 'text-secondary' }}">Negotiator</span>
                        @if($pullRequest->deploymentDecision)
                            <span class="material-symbols-outlined text-success" style="font-size: 16px;">check_circle</span>
                        @endif
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: {{ $pullRequest->deploymentDecision ? '100' : '0' }}%"></div>
                    </div>
                </div>
                <span class="material-symbols-outlined text-secondary" style="font-size: 16px;">chevron_right</span>
                {{-- Chronicler --}}
                <div class="flex-fill">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="material-symbols-outlined {{ $pullRequest->deploymentOutcome ? 'text-success' : 'text-secondary' }}" style="font-size: 18px;">auto_stories</span>
                        <span class="fs-13 {{ $pullRequest->deploymentOutcome ? 'fw-medium' : 'text-secondary' }}">Chronicler</span>
                        @if($pullRequest->deploymentOutcome)
                            <span class="material-symbols-outlined text-success" style="font-size: 16px;">check_circle</span>
                        @endif
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: {{ $pullRequest->deploymentOutcome ? '100' : '0' }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Risk Score Card --}}
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4 text-center">
                    <h5 class="fw-bold mb-3">Risk Assessment</h5>
                    @if($pullRequest->riskAssessment)
                        @php
                            $score = $pullRequest->riskAssessment->risk_score;
                            $color = $pullRequest->riskAssessment->risk_color;
                        @endphp
                        <div id="riskGaugeChart" style="min-height: 200px;"></div>
                        <span class="badge bg-{{ $color }} bg-opacity-10 text-{{ $color }} px-4 py-2 fs-14 text-uppercase fw-bold d-block mx-auto" style="max-width: 180px;">
                            {{ $pullRequest->riskAssessment->risk_level }}
                            @if($score >= 75)
                                — BLOCKED
                            @elseif($score >= 50)
                                — REVIEW
                            @else
                                — SAFE
                            @endif
                        </span>
                    @else
                        <div class="py-5 text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">hourglass_empty</span>
                            Awaiting analysis
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Blast Radius Summary --}}
        <div class="col-xl-4 col-lg-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Blast Radius</h5>
                    @if($pullRequest->blastRadius)
                        <div class="d-flex gap-3 mb-3">
                            <div class="text-center flex-fill p-3 bg-light rounded-3">
                                <span class="d-block fs-3 fw-bold text-primary">{{ $pullRequest->blastRadius->total_affected_files }}</span>
                                <span class="fs-14 text-secondary">Files</span>
                            </div>
                            <div class="text-center flex-fill p-3 bg-light rounded-3">
                                <span class="d-block fs-3 fw-bold text-warning">{{ $pullRequest->blastRadius->total_affected_services }}</span>
                                <span class="fs-14 text-secondary">Services</span>
                            </div>
                            <div class="text-center flex-fill p-3 bg-light rounded-3">
                                <span class="d-block fs-3 fw-bold text-danger">{{ count($pullRequest->blastRadius->affected_endpoints ?? []) }}</span>
                                <span class="fs-14 text-secondary">Endpoints</span>
                            </div>
                        </div>
                        <p class="text-secondary fs-14 mb-0">{{ $pullRequest->blastRadius->summary }}</p>
                    @else
                        <div class="py-4 text-center text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">explore</span>
                            Awaiting Archaeologist agent
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Deployment Decision --}}
        <div class="col-xl-4 col-lg-12 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Deployment Decision</h5>
                    @if($pullRequest->deploymentDecision)
                        @php $decision = $pullRequest->deploymentDecision; @endphp
                        <div class="text-center mb-3">
                            <span class="material-symbols-outlined text-{{ $decision->decision_color }} d-block mb-2" style="font-size: 48px;">
                                {{ $decision->decision === 'approved' ? 'check_circle' : ($decision->decision === 'blocked' ? 'cancel' : 'pending') }}
                            </span>
                            <span class="badge bg-{{ $decision->decision_color }} bg-opacity-10 text-{{ $decision->decision_color }} px-4 py-2 fs-14 text-uppercase fw-bold">
                                {{ str_replace('_', ' ', $decision->decision) }}
                            </span>
                        </div>
                        @if($decision->notification_message)
                            <div class="p-2 bg-light rounded-3 mb-2">
                                <p class="text-secondary fs-13 mb-0">{{ $decision->notification_message }}</p>
                            </div>
                        @endif
                        @if($decision->decided_by)
                            <p class="text-secondary fs-14 mb-1">
                                <span class="fw-medium">Decided by:</span> {{ $decision->decided_by }}
                            </p>
                        @endif
                        @if($decision->decided_at)
                            <p class="text-secondary fs-14 mb-1">
                                <span class="fw-medium">At:</span> {{ $decision->decided_at->format('M j, Y g:i A') }}
                            </p>
                        @endif
                        @if($decision->notified_oncall)
                            <p class="text-warning fs-14 mb-0">
                                <span class="material-symbols-outlined align-middle fs-16">notifications_active</span>
                                On-call team notified
                            </p>
                        @endif
                    @else
                        <div class="py-4 text-center text-secondary">
                            <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">gavel</span>
                            Awaiting Negotiator agent
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Blast Radius Visual Map --}}
    @if($pullRequest->blastRadius)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">
                    <span class="material-symbols-outlined align-middle me-1">hub</span>
                    Blast Radius Map
                </h5>
                <p class="text-secondary fs-13 mb-3">Visual representation of how this PR's changes ripple through the system. Larger boxes = higher impact.</p>
                <div class="row">
                    <div class="col-lg-8 mb-3 mb-lg-0">
                        <div id="blastRadiusTreemap" style="min-height: 320px;"></div>
                    </div>
                    <div class="col-lg-4">
                        <div id="impactRadialChart" style="min-height: 320px;"></div>
                    </div>
                </div>
                {{-- Legend --}}
                <div class="d-flex gap-4 mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 12px; height: 12px; background: #0d6efd; border-radius: 2px;"></div>
                        <span class="fs-13 text-secondary">Services (highest impact)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 12px; height: 12px; background: #6f42c1; border-radius: 2px;"></div>
                        <span class="fs-13 text-secondary">Files (medium impact)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 12px; height: 12px; background: #0dcaf0; border-radius: 2px;"></div>
                        <span class="fs-13 text-secondary">Endpoints (API surface)</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Dependency Graph --}}
    @if($pullRequest->blastRadius && count($pullRequest->blastRadius->dependency_graph ?? []) > 0)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">
                    <span class="material-symbols-outlined align-middle me-1">account_tree</span>
                    Dependency Graph
                </h5>
                <p class="text-secondary fs-13 mb-3">Shows which files depend on the changed files. Red = changed file, orange = downstream dependent.</p>
                <div class="row">
                    @foreach($pullRequest->blastRadius->dependency_graph as $sourceFile => $dependents)
                        <div class="col-xl-6 mb-3">
                            <div class="p-3 bg-light rounded-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="material-symbols-outlined text-danger me-2" style="font-size: 18px;">change_circle</span>
                                    <code class="fs-13 fw-bold text-danger">{{ $sourceFile }}</code>
                                </div>
                                @if(is_array($dependents) && count($dependents) > 0)
                                    <div class="ps-4 border-start border-2 border-warning ms-2">
                                        @foreach($dependents as $dep)
                                            <div class="d-flex align-items-center py-1">
                                                <span class="material-symbols-outlined text-warning me-2" style="font-size: 14px;">subdirectory_arrow_right</span>
                                                <code class="fs-13 text-secondary">{{ $dep }}</code>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-secondary fs-13 ps-4">No downstream dependents detected</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Affected Services + Files + Endpoints --}}
    @if($pullRequest->blastRadius)
        <div class="row">
            @if(count($pullRequest->blastRadius->affected_services ?? []) > 0)
                <div class="col-xl-4 mb-4">
                    <div class="card bg-white border-0 rounded-3 h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">
                                <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 18px;">dns</span>
                                Affected Services
                            </h6>
                            @foreach($pullRequest->blastRadius->affected_services as $service)
                                <div class="d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <div class="wh-30 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0">
                                        <span class="material-symbols-outlined text-primary" style="font-size: 14px;">dns</span>
                                    </div>
                                    <span class="fs-14 fw-medium">{{ $service }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(count($pullRequest->blastRadius->affected_files ?? []) > 0)
                <div class="col-xl-4 mb-4">
                    <div class="card bg-white border-0 rounded-3 h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">
                                <span class="material-symbols-outlined align-middle me-1 text-warning" style="font-size: 18px;">description</span>
                                Affected Files
                            </h6>
                            @foreach($pullRequest->blastRadius->affected_files as $file)
                                <div class="d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <span class="material-symbols-outlined text-secondary me-2 flex-shrink-0" style="font-size: 16px;">insert_drive_file</span>
                                    <code class="fs-13 text-truncate" title="{{ $file }}">{{ $file }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if(count($pullRequest->blastRadius->affected_endpoints ?? []) > 0)
                <div class="col-xl-4 mb-4">
                    <div class="card bg-white border-0 rounded-3 h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">
                                <span class="material-symbols-outlined align-middle me-1 text-info" style="font-size: 18px;">api</span>
                                Affected Endpoints
                            </h6>
                            @foreach($pullRequest->blastRadius->affected_endpoints as $endpoint)
                                <div class="d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                    <span class="badge bg-info bg-opacity-10 text-info me-2 px-2 flex-shrink-0">API</span>
                                    <code class="fs-13 text-truncate" title="{{ $endpoint }}">{{ $endpoint }}</code>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Contributing Factors & AI Recommendation --}}
    @if($pullRequest->riskAssessment)
        <div class="row">
            <div class="col-xl-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">fact_check</span>
                            Why This Risk Score?
                        </h5>
                        <p class="text-secondary fs-13 mb-3">These factors contributed to the risk score calculated by the Historian agent.</p>
                        <ul class="list-unstyled mb-0">
                            @foreach($pullRequest->riskAssessment->contributing_factors ?? [] as $factor)
                                <li class="py-2 {{ !$loop->last ? 'border-bottom' : '' }} d-flex align-items-start">
                                    <span class="material-symbols-outlined text-warning me-2 flex-shrink-0" style="font-size: 18px;">warning</span>
                                    <span class="fs-14">{{ $factor }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">smart_toy</span>
                            AI Recommendation
                        </h5>
                        <p class="text-secondary fs-13 mb-3">The Historian agent's recommendation for this deployment.</p>
                        <div class="p-3 bg-light rounded-3">
                            <p class="mb-0 fs-14 lh-lg">{{ $pullRequest->riskAssessment->recommendation }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Historical Incidents --}}
    @if($pullRequest->riskAssessment && count($pullRequest->riskAssessment->historical_incidents ?? []) > 0)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1">
                    <span class="material-symbols-outlined align-middle me-1">history</span>
                    Related Historical Incidents
                </h5>
                <p class="text-secondary fs-13 mb-3">Past incidents that the Historian agent found relevant to this PR's blast radius.</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="fw-medium text-secondary">ID</th>
                                <th class="fw-medium text-secondary">Incident</th>
                                <th class="fw-medium text-secondary">Severity</th>
                                <th class="fw-medium text-secondary">When</th>
                                <th class="fw-medium text-secondary">Relevance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pullRequest->riskAssessment->historical_incidents as $incident)
                                <tr>
                                    <td><code>{{ $incident['id'] ?? '—' }}</code></td>
                                    <td class="fw-medium">{{ $incident['title'] ?? 'Unknown' }}</td>
                                    <td>
                                        @php $sev = $incident['severity'] ?? 3; @endphp
                                        <span class="badge bg-{{ $sev <= 1 ? 'danger' : ($sev <= 2 ? 'warning' : 'info') }} bg-opacity-10 text-{{ $sev <= 1 ? 'danger' : ($sev <= 2 ? 'warning' : 'info') }}">
                                            P{{ $sev }}
                                        </span>
                                    </td>
                                    <td class="text-secondary">{{ $incident['days_ago'] ?? '?' }} days ago</td>
                                    <td class="text-secondary fs-14">{{ $incident['relevance'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Deployment Outcome (if deployed) --}}
    @if($pullRequest->deploymentOutcome)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1">auto_stories</span>
                    Post-Deployment Outcome
                </h5>
                <div class="row">
                    <div class="col-md-3 text-center">
                        <span class="d-block fs-14 text-secondary mb-1">Predicted</span>
                        <span class="fs-3 fw-bold">{{ $pullRequest->deploymentOutcome->predicted_risk_score }}/100</span>
                    </div>
                    <div class="col-md-3 text-center">
                        <span class="d-block fs-14 text-secondary mb-1">Incident?</span>
                        @if($pullRequest->deploymentOutcome->incident_occurred)
                            <span class="badge bg-danger px-3 py-2 fs-14">Yes</span>
                        @else
                            <span class="badge bg-success px-3 py-2 fs-14">No</span>
                        @endif
                    </div>
                    <div class="col-md-3 text-center">
                        <span class="d-block fs-14 text-secondary mb-1">Prediction</span>
                        @if($pullRequest->deploymentOutcome->prediction_accurate)
                            <span class="badge bg-success px-3 py-2 fs-14">Accurate</span>
                        @else
                            <span class="badge bg-warning px-3 py-2 fs-14">Inaccurate</span>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <span class="d-block fs-14 text-secondary mb-1">Notes</span>
                        <p class="fs-14 mb-0">{{ $pullRequest->deploymentOutcome->post_mortem_notes }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($pullRequest->riskAssessment)
    // Risk Score Gauge
    var riskScore = {{ $pullRequest->riskAssessment->risk_score }};
    var riskColor = riskScore >= 75 ? '#dc3545' : (riskScore >= 50 ? '#fd7e14' : '#198754');

    new ApexCharts(document.querySelector("#riskGaugeChart"), {
        series: [riskScore],
        chart: { type: 'radialBar', height: 200, fontFamily: 'inherit', sparkline: { enabled: false } },
        plotOptions: {
            radialBar: {
                startAngle: -135,
                endAngle: 135,
                hollow: { size: '60%' },
                track: { background: '#f0f0f0', strokeWidth: '100%' },
                dataLabels: {
                    name: { show: true, fontSize: '12px', color: '#6c757d', offsetY: 20 },
                    value: { show: true, fontSize: '36px', fontWeight: 700, color: riskColor, offsetY: -15, formatter: function(val) { return val; } }
                }
            }
        },
        fill: { colors: [riskColor] },
        labels: ['Risk Score'],
        stroke: { lineCap: 'round' }
    }).render();
    @endif

    @if($pullRequest->blastRadius)
    // Blast Radius Treemap
    var services = @json($pullRequest->blastRadius->affected_services ?? []);
    var files = @json($pullRequest->blastRadius->affected_files ?? []);
    var endpoints = @json($pullRequest->blastRadius->affected_endpoints ?? []);

    var treemapSeries = [];
    if (services.length > 0) {
        treemapSeries.push({ name: 'Services', data: services.map(function(s) { return { x: s, y: 30 }; }) });
    }
    if (files.length > 0) {
        treemapSeries.push({ name: 'Files', data: files.map(function(f) { return { x: f.split('/').pop(), y: 15 }; }) });
    }
    if (endpoints.length > 0) {
        treemapSeries.push({ name: 'Endpoints', data: endpoints.map(function(e) { return { x: e, y: 10 }; }) });
    }

    if (treemapSeries.length > 0) {
        // Flatten for single-series treemap with better visuals
        var allData = [];
        services.forEach(function(s) { allData.push({ x: s, y: 30 }); });
        files.forEach(function(f) { allData.push({ x: f.split('/').pop(), y: 15 }); });
        endpoints.forEach(function(e) { allData.push({ x: e, y: 10 }); });

        new ApexCharts(document.querySelector("#blastRadiusTreemap"), {
            series: [{ data: allData }],
            chart: { type: 'treemap', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#0d6efd', '#6610f2', '#d63384', '#fd7e14', '#198754', '#0dcaf0', '#6f42c1', '#20c997'],
            plotOptions: { treemap: { distributed: true, enableShades: false } },
            tooltip: {
                y: { formatter: function(val) {
                    if (val >= 30) return 'Service (high impact)';
                    if (val >= 15) return 'File (medium impact)';
                    return 'Endpoint (API surface)';
                }}
            }
        }).render();
    }

    // Impact Radial
    @if($pullRequest->riskAssessment)
    var filesImpact = Math.min(100, Math.round(({{ $pullRequest->blastRadius->total_affected_files ?? 0 }} / 10) * 100));
    var servicesImpact = Math.min(100, Math.round(({{ $pullRequest->blastRadius->total_affected_services ?? 0 }} / 8) * 100));
    var endpointsImpact = Math.min(100, Math.round(({{ count($pullRequest->blastRadius->affected_endpoints ?? []) }} / 6) * 100));

    new ApexCharts(document.querySelector("#impactRadialChart"), {
        series: [{{ $pullRequest->riskAssessment->risk_score }}, filesImpact, servicesImpact, endpointsImpact],
        chart: { type: 'radialBar', height: 320, fontFamily: 'inherit' },
        plotOptions: {
            radialBar: {
                offsetY: -10,
                startAngle: 0,
                endAngle: 270,
                hollow: { margin: 5, size: '30%', background: 'transparent' },
                dataLabels: { name: { show: false }, value: { show: false } },
                barLabels: {
                    enabled: true,
                    useSeriesColors: true,
                    fontSize: '12px',
                    formatter: function(seriesName, opts) {
                        return seriesName + ': ' + opts.w.globals.series[opts.seriesIndex] + '%';
                    }
                }
            }
        },
        colors: [riskColor, '#0d6efd', '#6f42c1', '#0dcaf0'],
        labels: ['Risk Score', 'Files', 'Services', 'Endpoints']
    }).render();
    @endif
    @endif
});
</script>
@endpush
