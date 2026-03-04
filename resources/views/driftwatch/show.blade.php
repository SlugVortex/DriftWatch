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

    {{-- Interactive Blast Radius Visualization --}}
    @if($pullRequest->blastRadius)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">
                            <span class="material-symbols-outlined align-middle me-1">hub</span>
                            Blast Radius — Interactive File Map
                        </h5>
                        <p class="text-secondary fs-13 mb-0">Drag nodes, zoom, and click to explore how changes ripple through the system.</p>
                    </div>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-primary active" id="btnDynamicView" onclick="toggleBlastView('dynamic')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">bubble_chart</span> Dynamic
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnStructuralView" onclick="toggleBlastView('structural')">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">account_tree</span> Structural
                        </button>
                    </div>
                </div>

                {{-- vis.js Dynamic Network Graph --}}
                <div id="blastRadiusDynamic" style="min-height: 480px; border: 1px solid #e9ecef; border-radius: 12px; background: linear-gradient(135deg, #fafbff 0%, #f5f7ff 100%);"></div>

                {{-- Mermaid Structural Diagram --}}
                <div id="blastRadiusStructural" style="min-height: 480px; display: none; padding: 20px;">
                    <div class="mermaid" id="blastMermaidDiagram">
                    </div>
                </div>

                {{-- Legend --}}
                <div class="d-flex gap-4 mt-3 pt-3 border-top flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 14px; height: 14px; background: linear-gradient(135deg, #dc3545, #b02a37); border-radius: 50%;"></div>
                        <span class="fs-13 text-secondary">Changed Files (source of change)</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 14px; height: 14px; background: linear-gradient(135deg, #fd7e14, #e8590c); border-radius: 50%;"></div>
                        <span class="fs-13 text-secondary">Downstream Dependents</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 18px; height: 14px; background: linear-gradient(135deg, #0d6efd, #0b5ed7); border-radius: 3px;"></div>
                        <span class="fs-13 text-secondary">Services</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width: 14px; height: 14px; background: linear-gradient(135deg, #0dcaf0, #0aa2c0); border-radius: 50%; border: 2px solid #0dcaf0;"></div>
                        <span class="fs-13 text-secondary">Endpoints</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Impact Metrics Row --}}
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">donut_large</span>
                            Impact Treemap
                        </h6>
                        <div id="blastRadiusTreemap" style="min-height: 280px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">speed</span>
                            Impact Radial
                        </h6>
                        <div id="impactRadialChart" style="min-height: 280px;"></div>
                    </div>
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
{{-- vis.js for dynamic network graph --}}
<script src="https://unpkg.com/vis-network@9.1.6/standalone/umd/vis-network.min.js"></script>
{{-- Mermaid.js for structural diagrams --}}
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
// Toggle between dynamic and structural blast radius views
function toggleBlastView(view) {
    var dynamicEl = document.getElementById('blastRadiusDynamic');
    var structuralEl = document.getElementById('blastRadiusStructural');
    var btnDynamic = document.getElementById('btnDynamicView');
    var btnStructural = document.getElementById('btnStructuralView');
    if (!dynamicEl || !structuralEl) return;

    if (view === 'dynamic') {
        dynamicEl.style.display = 'block';
        structuralEl.style.display = 'none';
        btnDynamic.className = 'btn btn-sm btn-primary active';
        btnStructural.className = 'btn btn-sm btn-outline-primary';
    } else {
        dynamicEl.style.display = 'none';
        structuralEl.style.display = 'block';
        btnDynamic.className = 'btn btn-sm btn-outline-primary';
        btnStructural.className = 'btn btn-sm btn-primary active';
        // Re-render mermaid if needed
        if (!structuralEl.dataset.rendered) {
            mermaid.run({ nodes: [document.getElementById('blastMermaidDiagram')] });
            structuralEl.dataset.rendered = 'true';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize mermaid
    mermaid.initialize({ startOnLoad: false, theme: 'base', themeVariables: {
        primaryColor: '#e8f0fe', primaryBorderColor: '#0d6efd', primaryTextColor: '#1a1a2e',
        lineColor: '#6c757d', secondaryColor: '#fff3cd', tertiaryColor: '#f8d7da'
    }});

    @if($pullRequest->riskAssessment)
    // Risk Score Gauge
    var riskScore = {{ $pullRequest->riskAssessment->risk_score }};
    var riskColor = riskScore >= 75 ? '#dc3545' : (riskScore >= 50 ? '#fd7e14' : '#198754');

    new ApexCharts(document.querySelector("#riskGaugeChart"), {
        series: [riskScore],
        chart: { type: 'radialBar', height: 200, fontFamily: 'inherit', sparkline: { enabled: false } },
        plotOptions: {
            radialBar: {
                startAngle: -135, endAngle: 135,
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
    var services = @json($pullRequest->blastRadius->affected_services ?? []);
    var files = @json($pullRequest->blastRadius->affected_files ?? []);
    var endpoints = @json($pullRequest->blastRadius->affected_endpoints ?? []);
    var depGraph = @json($pullRequest->blastRadius->dependency_graph ?? []);

    // === vis.js Dynamic Network Graph ===
    (function() {
        var nodes = [];
        var edges = [];
        var nodeId = 1;
        var nodeMap = {};

        // Central PR node
        var prNodeId = nodeId++;
        nodes.push({
            id: prNodeId,
            label: 'PR #{{ $pullRequest->pr_number }}',
            shape: 'diamond',
            size: 35,
            color: { background: '#605DFF', border: '#4b49cc', highlight: { background: '#7c7aff', border: '#605DFF' } },
            font: { color: '#ffffff', size: 13, bold: true },
            shadow: { enabled: true, color: 'rgba(96,93,255,0.3)', size: 15 }
        });

        // Service nodes (large boxes)
        services.forEach(function(s) {
            var id = nodeId++;
            nodeMap['svc_' + s] = id;
            nodes.push({
                id: id,
                label: s,
                shape: 'box',
                size: 28,
                color: { background: '#0d6efd', border: '#0b5ed7', highlight: { background: '#3d8bfd', border: '#0d6efd' } },
                font: { color: '#ffffff', size: 12, bold: true },
                borderWidth: 2,
                shadow: { enabled: true, color: 'rgba(13,110,253,0.25)', size: 10 }
            });
            edges.push({
                from: prNodeId, to: id,
                color: { color: '#0d6efd', opacity: 0.5 },
                width: 2, dashes: false,
                arrows: { to: { enabled: true, scaleFactor: 0.7 } },
                smooth: { type: 'curvedCW', roundness: 0.2 }
            });
        });

        // File nodes (circles, colored by risk)
        files.forEach(function(f, i) {
            var id = nodeId++;
            var shortName = f.split('/').pop();
            nodeMap['file_' + f] = id;
            var isChanged = Object.keys(depGraph).indexOf(f) >= 0;
            var bgColor = isChanged ? '#dc3545' : '#fd7e14';
            var borderColor = isChanged ? '#b02a37' : '#e8590c';
            nodes.push({
                id: id,
                label: shortName,
                title: f,
                shape: 'dot',
                size: isChanged ? 22 : 16,
                color: { background: bgColor, border: borderColor, highlight: { background: '#ff6b7a', border: bgColor } },
                font: { color: '#333', size: 10 },
                shadow: { enabled: true, color: 'rgba(220,53,69,0.2)', size: 8 }
            });
            edges.push({
                from: prNodeId, to: id,
                color: { color: isChanged ? '#dc3545' : '#fd7e14', opacity: 0.4 },
                width: isChanged ? 2 : 1,
                arrows: { to: { enabled: true, scaleFactor: 0.5 } },
                smooth: { type: 'curvedCCW', roundness: 0.15 }
            });
        });

        // Endpoint nodes (small circles)
        endpoints.forEach(function(e) {
            var id = nodeId++;
            nodeMap['ep_' + e] = id;
            nodes.push({
                id: id,
                label: e,
                shape: 'dot',
                size: 12,
                color: { background: '#0dcaf0', border: '#0aa2c0', highlight: { background: '#3dd5f3', border: '#0dcaf0' } },
                font: { color: '#333', size: 9 },
                borderWidth: 2,
                borderWidthSelected: 3
            });
            edges.push({
                from: prNodeId, to: id,
                color: { color: '#0dcaf0', opacity: 0.3 },
                width: 1, dashes: [4, 4],
                arrows: { to: { enabled: true, scaleFactor: 0.4 } }
            });
        });

        // Dependency edges
        Object.keys(depGraph).forEach(function(sourceFile) {
            var sourceId = nodeMap['file_' + sourceFile];
            var deps = depGraph[sourceFile];
            if (Array.isArray(deps)) {
                deps.forEach(function(dep) {
                    // Add dependent node if not already present
                    if (!nodeMap['file_' + dep]) {
                        var id = nodeId++;
                        nodeMap['file_' + dep] = id;
                        var shortName = dep.split('/').pop();
                        nodes.push({
                            id: id,
                            label: shortName,
                            title: dep + ' (downstream dependent)',
                            shape: 'dot',
                            size: 14,
                            color: { background: '#ffc107', border: '#e0a800', highlight: { background: '#ffcd39', border: '#ffc107' } },
                            font: { color: '#333', size: 9 }
                        });
                    }
                    if (sourceId && nodeMap['file_' + dep]) {
                        edges.push({
                            from: sourceId, to: nodeMap['file_' + dep],
                            color: { color: '#fd7e14', opacity: 0.6 },
                            width: 1.5,
                            arrows: { to: { enabled: true, scaleFactor: 0.6, type: 'vee' } },
                            dashes: [6, 3],
                            smooth: { type: 'dynamic' }
                        });
                    }
                });
            }
        });

        var container = document.getElementById('blastRadiusDynamic');
        if (container && nodes.length > 0) {
            var network = new vis.Network(container, {
                nodes: new vis.DataSet(nodes),
                edges: new vis.DataSet(edges)
            }, {
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -60,
                        centralGravity: 0.008,
                        springLength: 140,
                        springConstant: 0.06,
                        damping: 0.4
                    },
                    stabilization: { iterations: 120, fit: true }
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 150,
                    zoomView: true,
                    dragView: true,
                    navigationButtons: false,
                    keyboard: false
                },
                layout: { improvedLayout: true }
            });

            // Click to highlight connected nodes
            network.on('click', function(params) {
                if (params.nodes.length > 0) {
                    var connectedNodes = network.getConnectedNodes(params.nodes[0]);
                    connectedNodes.push(params.nodes[0]);
                    // Flash effect via selection
                    network.selectNodes(connectedNodes);
                }
            });
        }
    })();

    // === Mermaid Structural Diagram ===
    (function() {
        var mermaidCode = 'flowchart TD\n';
        mermaidCode += '    PR["PR #{{ $pullRequest->pr_number }}\\n{{ addslashes($pullRequest->pr_title) }}"]\n';
        mermaidCode += '    style PR fill:#605DFF,color:#fff,stroke:#4b49cc,stroke-width:2px\n';

        // Services subgraph
        if (services.length > 0) {
            mermaidCode += '    subgraph Services["Affected Services"]\n';
            services.forEach(function(s, i) {
                var sid = 'SVC' + i;
                mermaidCode += '        ' + sid + '["' + s + '"]\n';
                mermaidCode += '        style ' + sid + ' fill:#0d6efd,color:#fff,stroke:#0b5ed7\n';
            });
            mermaidCode += '    end\n';
            services.forEach(function(s, i) {
                mermaidCode += '    PR --> SVC' + i + '\n';
            });
        }

        // Files subgraph
        if (files.length > 0) {
            mermaidCode += '    subgraph Files["Changed Files"]\n';
            files.forEach(function(f, i) {
                var fid = 'FILE' + i;
                var shortName = f.split('/').pop();
                var isSource = Object.keys(depGraph).indexOf(f) >= 0;
                mermaidCode += '        ' + fid + '["' + shortName + '"]\n';
                mermaidCode += '        style ' + fid + ' fill:' + (isSource ? '#dc3545' : '#fd7e14') + ',color:#fff,stroke:' + (isSource ? '#b02a37' : '#e8590c') + '\n';
            });
            mermaidCode += '    end\n';
            files.forEach(function(f, i) {
                mermaidCode += '    PR --> FILE' + i + '\n';
            });
        }

        // Dependency edges
        var depIdx = 0;
        Object.keys(depGraph).forEach(function(sourceFile) {
            var sourceIdx = files.indexOf(sourceFile);
            var deps = depGraph[sourceFile];
            if (Array.isArray(deps) && sourceIdx >= 0) {
                deps.forEach(function(dep) {
                    var depId = 'DEP' + depIdx++;
                    var shortDep = dep.split('/').pop();
                    mermaidCode += '    ' + depId + '(("' + shortDep + '"))\n';
                    mermaidCode += '    style ' + depId + ' fill:#ffc107,color:#333,stroke:#e0a800\n';
                    mermaidCode += '    FILE' + sourceIdx + ' -.->|depends| ' + depId + '\n';
                });
            }
        });

        // Endpoints subgraph
        if (endpoints.length > 0) {
            mermaidCode += '    subgraph Endpoints["API Endpoints"]\n';
            endpoints.forEach(function(e, i) {
                var eid = 'EP' + i;
                mermaidCode += '        ' + eid + '["' + e + '"]\n';
                mermaidCode += '        style ' + eid + ' fill:#0dcaf0,color:#333,stroke:#0aa2c0\n';
            });
            mermaidCode += '    end\n';
            endpoints.forEach(function(e, i) {
                mermaidCode += '    PR -.-> EP' + i + '\n';
            });
        }

        var mermaidEl = document.getElementById('blastMermaidDiagram');
        if (mermaidEl) {
            mermaidEl.textContent = mermaidCode;
        }
    })();

    // Treemap
    var allData = [];
    services.forEach(function(s) { allData.push({ x: s, y: 30 }); });
    files.forEach(function(f) { allData.push({ x: f.split('/').pop(), y: 15 }); });
    endpoints.forEach(function(e) { allData.push({ x: e, y: 10 }); });

    if (allData.length > 0) {
        new ApexCharts(document.querySelector("#blastRadiusTreemap"), {
            series: [{ data: allData }],
            chart: { type: 'treemap', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
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
        chart: { type: 'radialBar', height: 280, fontFamily: 'inherit' },
        plotOptions: {
            radialBar: {
                offsetY: -10, startAngle: 0, endAngle: 270,
                hollow: { margin: 5, size: '30%', background: 'transparent' },
                dataLabels: { name: { show: false }, value: { show: false } },
                barLabels: {
                    enabled: true, useSeriesColors: true, fontSize: '12px',
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
