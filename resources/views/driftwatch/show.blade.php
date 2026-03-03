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
                    <a href="{{ $pullRequest->pr_url }}" target="_blank" class="btn btn-outline-primary">
                        <span class="material-symbols-outlined align-middle me-1">open_in_new</span> GitHub
                    </a>
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
                        <div class="position-relative d-inline-block mb-3">
                            <div class="wh-150 rounded-circle border border-4 border-{{ $color }} d-flex align-items-center justify-content-center mx-auto">
                                <div>
                                    <span class="d-block fs-1 fw-bold text-{{ $color }}">{{ $score }}</span>
                                    <span class="d-block fs-14 text-secondary">/100</span>
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-{{ $color }} bg-opacity-10 text-{{ $color }} px-4 py-2 fs-14 text-uppercase fw-bold d-block mx-auto" style="max-width: 150px;">
                            {{ $pullRequest->riskAssessment->risk_level }}
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

    {{-- Affected Services --}}
    @if($pullRequest->blastRadius && count($pullRequest->blastRadius->affected_services ?? []) > 0)
        <div class="card bg-white border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1">lan</span>
                    Affected Services
                </h5>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($pullRequest->blastRadius->affected_services as $service)
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-14">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">dns</span>
                            {{ $service }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        {{-- Affected Files --}}
        @if($pullRequest->blastRadius && count($pullRequest->blastRadius->affected_files ?? []) > 0)
            <div class="col-xl-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">description</span>
                            Affected Files
                        </h5>
                        <ul class="list-unstyled mb-0">
                            @foreach($pullRequest->blastRadius->affected_files as $file)
                                <li class="py-2 border-bottom d-flex align-items-center">
                                    <span class="material-symbols-outlined text-secondary me-2 fs-16">insert_drive_file</span>
                                    <code class="fs-14">{{ $file }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- Affected Endpoints --}}
        @if($pullRequest->blastRadius && count($pullRequest->blastRadius->affected_endpoints ?? []) > 0)
            <div class="col-xl-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">api</span>
                            Affected Endpoints
                        </h5>
                        <ul class="list-unstyled mb-0">
                            @foreach($pullRequest->blastRadius->affected_endpoints as $endpoint)
                                <li class="py-2 border-bottom d-flex align-items-center">
                                    <span class="badge bg-info bg-opacity-10 text-info me-2 px-2">API</span>
                                    <code class="fs-14">{{ $endpoint }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Contributing Factors & Recommendation --}}
    @if($pullRequest->riskAssessment)
        <div class="row">
            <div class="col-xl-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <span class="material-symbols-outlined align-middle me-1">fact_check</span>
                            Contributing Factors
                        </h5>
                        <ul class="list-unstyled mb-0">
                            @foreach($pullRequest->riskAssessment->contributing_factors ?? [] as $factor)
                                <li class="py-2 border-bottom d-flex align-items-start">
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
                            <span class="material-symbols-outlined align-middle me-1">recommend</span>
                            AI Recommendation
                        </h5>
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
                <h5 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1">history</span>
                    Related Historical Incidents
                </h5>
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

    {{-- Agent Pipeline Timeline --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">timeline</span>
                Agent Pipeline Timeline
            </h5>
            <div class="d-flex flex-column gap-3">
                {{-- Step 1: Archaeologist --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="wh-40 rounded-circle d-flex align-items-center justify-content-center {{ $pullRequest->blastRadius ? 'bg-primary' : 'bg-secondary bg-opacity-25' }}">
                        <span class="material-symbols-outlined text-white" style="font-size: 20px;">explore</span>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-medium">Archaeologist</span>
                        <span class="text-secondary ms-2 fs-14">Blast Radius Mapper</span>
                    </div>
                    @if($pullRequest->blastRadius)
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">Complete</span>
                        <span class="text-secondary fs-14">{{ $pullRequest->blastRadius->created_at->format('g:i A') }}</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">Pending</span>
                    @endif
                </div>

                {{-- Step 2: Historian --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="wh-40 rounded-circle d-flex align-items-center justify-content-center {{ $pullRequest->riskAssessment ? 'bg-warning' : 'bg-secondary bg-opacity-25' }}">
                        <span class="material-symbols-outlined text-white" style="font-size: 20px;">history</span>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-medium">Historian</span>
                        <span class="text-secondary ms-2 fs-14">Risk Score Calculator</span>
                    </div>
                    @if($pullRequest->riskAssessment)
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">Complete</span>
                        <span class="text-secondary fs-14">Score: {{ $pullRequest->riskAssessment->risk_score }}/100</span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">Pending</span>
                    @endif
                </div>

                {{-- Step 3: Negotiator --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="wh-40 rounded-circle d-flex align-items-center justify-content-center {{ $pullRequest->deploymentDecision ? 'bg-danger' : 'bg-secondary bg-opacity-25' }}">
                        <span class="material-symbols-outlined text-white" style="font-size: 20px;">gavel</span>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-medium">Negotiator</span>
                        <span class="text-secondary ms-2 fs-14">Deployment Gatekeeper</span>
                    </div>
                    @if($pullRequest->deploymentDecision)
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">Complete</span>
                        <span class="badge bg-{{ $pullRequest->deploymentDecision->decision_color }} bg-opacity-10 text-{{ $pullRequest->deploymentDecision->decision_color }} px-2 py-1 text-capitalize">
                            {{ str_replace('_', ' ', $pullRequest->deploymentDecision->decision) }}
                        </span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">Pending</span>
                    @endif
                </div>

                {{-- Step 4: Chronicler --}}
                <div class="d-flex align-items-center gap-3">
                    <div class="wh-40 rounded-circle d-flex align-items-center justify-content-center {{ $pullRequest->deploymentOutcome ? 'bg-success' : 'bg-secondary bg-opacity-25' }}">
                        <span class="material-symbols-outlined text-white" style="font-size: 20px;">auto_stories</span>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-medium">Chronicler</span>
                        <span class="text-secondary ms-2 fs-14">Feedback Loop</span>
                    </div>
                    @if($pullRequest->deploymentOutcome)
                        <span class="badge bg-success bg-opacity-10 text-success px-3 py-1">Complete</span>
                        <span class="text-secondary fs-14">
                            {{ $pullRequest->deploymentOutcome->prediction_accurate ? 'Prediction accurate' : 'Prediction inaccurate' }}
                        </span>
                    @else
                        <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-1">Awaiting deploy</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
