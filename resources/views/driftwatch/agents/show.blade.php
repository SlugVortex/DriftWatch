{{-- resources/views/driftwatch/agents/show.blade.php --}}
{{-- Individual agent status page - shows agent info and recent results --}}
@extends('layouts.app')

@section('title', $agentInfo['name'])
@section('heading', $agentInfo['name'])

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <span class="text-secondary fw-medium">Agents</span>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">{{ $agentInfo['name'] }}</span>
    </li>
@endsection

@section('content')
    {{-- Agent Info Card --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-4">
                <div class="wh-80 bg-{{ $agentInfo['color'] }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                    <span class="material-symbols-outlined text-{{ $agentInfo['color'] }}" style="font-size: 36px;">{{ $agentInfo['icon'] }}</span>
                </div>
                <div>
                    <h4 class="fw-bold mb-1">{{ $agentInfo['name'] }}</h4>
                    <p class="text-{{ $agentInfo['color'] }} fw-medium mb-2">{{ $agentInfo['subtitle'] }}</p>
                    <p class="text-secondary mb-3">{{ $agentInfo['description'] }}</p>
                    <div class="row">
                        <div class="col-md-6">
                            <span class="fw-medium">Inputs:</span>
                            <p class="text-secondary fs-14 mb-0">{{ $agentInfo['inputs'] }}</p>
                        </div>
                        <div class="col-md-6">
                            <span class="fw-medium">Outputs:</span>
                            <p class="text-secondary fs-14 mb-0">{{ $agentInfo['outputs'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Results --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-4">Recent Results</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="fw-medium text-secondary">PR</th>
                            <th class="fw-medium text-secondary">Title</th>
                            <th class="fw-medium text-secondary">Result</th>
                            <th class="fw-medium text-secondary">Date</th>
                            <th class="fw-medium text-secondary">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentResults as $pr)
                            <tr>
                                <td><span class="fw-bold text-primary">#{{ $pr->pr_number }}</span></td>
                                <td>{{ Str::limit($pr->pr_title, 45) }}</td>
                                <td>
                                    @if($agent === 'archaeologist' && $pr->blastRadius)
                                        <span class="text-secondary">{{ $pr->blastRadius->total_affected_services }} services, {{ $pr->blastRadius->total_affected_files }} files</span>
                                    @elseif($agent === 'historian' && $pr->riskAssessment)
                                        <span class="badge bg-{{ $pr->riskAssessment->risk_color }} bg-opacity-10 text-{{ $pr->riskAssessment->risk_color }} px-3 py-2">
                                            Score: {{ $pr->riskAssessment->risk_score }}/100
                                        </span>
                                    @elseif($agent === 'negotiator' && $pr->deploymentDecision)
                                        <span class="badge bg-{{ $pr->deploymentDecision->decision_color }} bg-opacity-10 text-{{ $pr->deploymentDecision->decision_color }} px-3 py-2 text-capitalize">
                                            {{ str_replace('_', ' ', $pr->deploymentDecision->decision) }}
                                        </span>
                                    @elseif($agent === 'chronicler' && $pr->deploymentOutcome)
                                        <span class="badge bg-{{ $pr->deploymentOutcome->prediction_accurate ? 'success' : 'warning' }} bg-opacity-10 text-{{ $pr->deploymentOutcome->prediction_accurate ? 'success' : 'warning' }} px-3 py-2">
                                            {{ $pr->deploymentOutcome->prediction_accurate ? 'Accurate' : 'Inaccurate' }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-secondary fs-14">{{ $pr->created_at->format('M j, Y') }}</td>
                                <td>
                                    <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-outline-primary">
                                        <span class="material-symbols-outlined fs-16">visibility</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">
                                    No results from this agent yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
