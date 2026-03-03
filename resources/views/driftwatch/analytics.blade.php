{{-- resources/views/driftwatch/analytics.blade.php --}}
{{-- Analytics page - risk distribution, accuracy trends, risky services --}}
@extends('layouts.app')

@section('title', 'Analytics')
@section('heading', 'Analytics')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Analytics</span>
    </li>
@endsection

@section('content')
    {{-- Summary Stats Row --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4 text-center">
                    <span class="material-symbols-outlined text-success mb-2" style="font-size: 36px;">check_circle</span>
                    <h3 class="fw-bold text-success">{{ $totalApproved }}</h3>
                    <span class="text-secondary fs-14">Approved</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4 text-center">
                    <span class="material-symbols-outlined text-danger mb-2" style="font-size: 36px;">block</span>
                    <h3 class="fw-bold text-danger">{{ $totalBlocked }}</h3>
                    <span class="text-secondary fs-14">Blocked</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4 text-center">
                    <span class="material-symbols-outlined text-primary mb-2" style="font-size: 36px;">verified</span>
                    <h3 class="fw-bold text-primary">{{ $accuracy }}%</h3>
                    <span class="text-secondary fs-14">Prediction Accuracy</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card bg-white border-0 rounded-3 mb-4">
                <div class="card-body p-4 text-center">
                    <span class="material-symbols-outlined text-warning mb-2" style="font-size: 36px;">speed</span>
                    <h3 class="fw-bold text-warning">{{ count($recentAssessments) }}</h3>
                    <span class="text-secondary fs-14">Total Assessments</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Risk Level Distribution --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Risk Level Distribution</h5>
                    @php
                        $levels = ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'];
                        $total = array_sum($riskDistribution) ?: 1;
                    @endphp
                    @foreach($levels as $level => $color)
                        @php $count = $riskDistribution[$level] ?? 0; $pct = round(($count / $total) * 100); @endphp
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-medium text-capitalize">{{ $level }}</span>
                                <span class="text-secondary">{{ $count }} ({{ $pct }}%)</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-{{ $color }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Top Risky Services --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Most Incident-Prone Services</h5>
                    @forelse($topRiskyServices as $service => $count)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div class="d-flex align-items-center">
                                <span class="material-symbols-outlined text-primary me-2" style="font-size: 18px;">dns</span>
                                <span class="fw-medium">{{ $service }}</span>
                            </div>
                            <span class="badge bg-{{ $count >= 3 ? 'danger' : ($count >= 2 ? 'warning' : 'info') }} bg-opacity-10 text-{{ $count >= 3 ? 'danger' : ($count >= 2 ? 'warning' : 'info') }} px-3 py-2">
                                {{ $count }} {{ Str::plural('incident', $count) }}
                            </span>
                        </div>
                    @empty
                        <p class="text-secondary text-center py-4">No incident data available.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Risk Scores --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4">Recent Risk Assessments</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="fw-medium text-secondary">PR</th>
                            <th class="fw-medium text-secondary">Title</th>
                            <th class="fw-medium text-secondary">Risk Score</th>
                            <th class="fw-medium text-secondary">Level</th>
                            <th class="fw-medium text-secondary">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAssessments as $assessment)
                            <tr>
                                <td>
                                    @if($assessment->pullRequest)
                                        <a href="{{ route('driftwatch.show', $assessment->pullRequest) }}" class="text-primary fw-bold text-decoration-none">
                                            #{{ $assessment->pullRequest->pr_number }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $assessment->pullRequest->pr_title ?? '—' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                            <div class="progress-bar bg-{{ $assessment->risk_color }}" style="width: {{ $assessment->risk_score }}%"></div>
                                        </div>
                                        <span class="fw-bold text-{{ $assessment->risk_color }}">{{ $assessment->risk_score }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $assessment->risk_color }} bg-opacity-10 text-{{ $assessment->risk_color }} px-3 py-2 text-capitalize">
                                        {{ $assessment->risk_level }}
                                    </span>
                                </td>
                                <td class="text-secondary fs-14">{{ $assessment->created_at->format('M j, Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
