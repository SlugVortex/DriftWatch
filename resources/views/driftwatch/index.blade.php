{{-- resources/views/driftwatch/index.blade.php --}}
{{-- Main DriftWatch dashboard - summary stats and recent PR table --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Overview</span>
    </li>
@endsection

@section('content')
    {{-- Summary Stats --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block mb-1 fs-14 text-secondary">Total PRs Analyzed</span>
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
                                <span class="text-{{ $stats['avg_risk_score'] >= 76 ? 'danger' : ($stats['avg_risk_score'] >= 51 ? 'warning' : ($stats['avg_risk_score'] >= 26 ? 'info' : 'success')) }}">
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
                            <span class="d-block mb-1 fs-14 text-secondary">Deployments Blocked</span>
                            <h3 class="mb-0 fs-2 fw-bold text-danger">{{ $stats['incidents_prevented'] }}</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-danger">block</span>
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
                            <h3 class="mb-0 fs-2 fw-bold text-success">{{ $stats['prediction_accuracy'] }}%</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="wh-50 bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <span class="material-symbols-outlined text-success">verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Pull Requests --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0 fw-bold">Recent Pull Requests</h4>
                <a href="{{ route('driftwatch.pull-requests') }}" class="btn btn-outline-primary btn-sm">
                    View All
                </a>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="fw-medium text-secondary">PR</th>
                            <th class="fw-medium text-secondary">Title</th>
                            <th class="fw-medium text-secondary">Author</th>
                            <th class="fw-medium text-secondary">Risk Score</th>
                            <th class="fw-medium text-secondary">Status</th>
                            <th class="fw-medium text-secondary">Decision</th>
                            <th class="fw-medium text-secondary">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pullRequests as $pr)
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
                                    <span class="badge bg-{{ $pr->status_color }} bg-opacity-10 text-{{ $pr->status_color }} px-3 py-2 text-capitalize">
                                        {{ str_replace('_', ' ', $pr->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($pr->deploymentDecision)
                                        <span class="badge bg-{{ $pr->deploymentDecision->decision_color }} bg-opacity-10 text-{{ $pr->deploymentDecision->decision_color }} px-3 py-2 text-capitalize">
                                            {{ str_replace('_', ' ', $pr->deploymentDecision->decision) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-outline-primary">
                                        <span class="material-symbols-outlined fs-16">visibility</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">inbox</span>
                                    No pull requests analyzed yet. Send a webhook or run the seeder.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $pullRequests->links() }}
        </div>
    </div>
@endsection
