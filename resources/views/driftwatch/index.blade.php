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

    {{-- How It Works - visual pipeline --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4 text-center">How DriftWatch Works</h5>
            <div class="row text-center">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3">
                        <div class="wh-60 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <span class="material-symbols-outlined text-primary" style="font-size: 28px;">explore</span>
                        </div>
                        <h6 class="fw-bold mb-1">1. Archaeologist</h6>
                        <p class="text-secondary fs-13 mb-0">Maps the blast radius — which files, services, and endpoints your PR touches</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3">
                        <div class="wh-60 bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <span class="material-symbols-outlined text-warning" style="font-size: 28px;">history</span>
                        </div>
                        <h6 class="fw-bold mb-1">2. Historian</h6>
                        <p class="text-secondary fs-13 mb-0">Correlates with past incidents to produce a risk score from 0-100</p>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3">
                        <div class="wh-60 bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <span class="material-symbols-outlined text-danger" style="font-size: 28px;">gavel</span>
                        </div>
                        <h6 class="fw-bold mb-1">3. Negotiator</h6>
                        <p class="text-secondary fs-13 mb-0">Makes the deploy/block/review decision and posts a comment on your PR</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3">
                        <div class="wh-60 bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                            <span class="material-symbols-outlined text-success" style="font-size: 28px;">auto_stories</span>
                        </div>
                        <h6 class="fw-bold mb-1">4. Chronicler</h6>
                        <p class="text-secondary fs-13 mb-0">Tracks outcomes after deploy to improve future predictions</p>
                    </div>
                </div>
            </div>
            {{-- Flow arrows (visible on md+) --}}
            <div class="d-none d-md-flex justify-content-center align-items-center mt-2 gap-0" style="margin-top: -10px;">
                <div class="text-center" style="width: 25%;"><span class="text-secondary">PR diff</span></div>
                <span class="material-symbols-outlined text-secondary">arrow_forward</span>
                <div class="text-center" style="width: 25%;"><span class="text-secondary">Blast radius</span></div>
                <span class="material-symbols-outlined text-secondary">arrow_forward</span>
                <div class="text-center" style="width: 25%;"><span class="text-secondary">Risk score</span></div>
                <span class="material-symbols-outlined text-secondary">arrow_forward</span>
                <div class="text-center" style="width: 25%;"><span class="text-secondary">Decision</span></div>
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
                            <h3 class="mb-0 fs-2 fw-bold text-success">{{ $stats['prediction_accuracy'] ?: '—' }}{{ $stats['prediction_accuracy'] ? '%' : '' }}</h3>
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

    {{-- Charts Row --}}
    <div class="row mb-4">
        {{-- Risk Score Distribution --}}
        <div class="col-xl-7 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">Risk Score by PR</h5>
                    <p class="text-secondary fs-13 mb-3">Each bar shows the risk score for a recently analyzed PR. Red = high risk, green = safe.</p>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading state for analyze button
    var form = document.querySelector('form[action*="analyze"]');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('analyzeBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Analyzing...';
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
            if ($r->risk_score >= 75) return '#dc3545';
            if ($r->risk_score >= 50) return '#fd7e14';
            if ($r->risk_score >= 25) return '#0dcaf0';
            return '#198754';
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
                { y: 75, borderColor: '#dc3545', strokeDashArray: 4, label: { text: 'Block threshold', style: { color: '#dc3545', background: 'transparent', fontSize: '10px' } } },
                { y: 50, borderColor: '#fd7e14', strokeDashArray: 4, label: { text: 'Review threshold', style: { color: '#fd7e14', background: 'transparent', fontSize: '10px' } } }
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
        colors: ['#198754', '#fd7e14', '#dc3545'],
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
