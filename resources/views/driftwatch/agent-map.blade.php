{{-- resources/views/driftwatch/agent-map.blade.php --}}
{{-- Agent Pipeline Visualization - Agent 365 style interactive map --}}
@extends('layouts.app')

@section('title', 'Agent Map')
@section('heading', 'Agent Pipeline Map')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Agent Map</span>
    </li>
@endsection

@push('styles')
<style>
    /* Agent Map - Orbital Layout */
    .agent-map-container {
        position: relative;
        width: 100%;
        min-height: 520px;
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
        border-radius: 16px;
        overflow: hidden;
    }
    .agent-map-container::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 340px;
        height: 340px;
        transform: translate(-50%, -50%);
        border: 2px dashed rgba(96, 93, 255, 0.15);
        border-radius: 50%;
        pointer-events: none;
    }
    .agent-map-container::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 480px;
        height: 480px;
        transform: translate(-50%, -50%);
        border: 1px dashed rgba(96, 93, 255, 0.08);
        border-radius: 50%;
        pointer-events: none;
    }

    /* SVG Connections */
    .agent-connections {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
    }
    .connection-line {
        stroke: #605DFF;
        stroke-width: 2;
        fill: none;
        opacity: 0.3;
    }
    .connection-line.active {
        opacity: 0.8;
        stroke-width: 2.5;
        stroke-dasharray: 8 4;
        animation: dashFlow 1.5s linear infinite;
    }
    .connection-line.complete {
        opacity: 0.5;
        stroke: #198754;
    }
    @keyframes dashFlow {
        to { stroke-dashoffset: -24; }
    }

    /* Orchestrator Hub */
    .orchestrator-hub {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 10;
        text-align: center;
    }
    .orchestrator-circle {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: linear-gradient(135deg, #605DFF 0%, #8B5CF6 100%);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        box-shadow: 0 8px 32px rgba(96, 93, 255, 0.35), 0 0 0 4px rgba(96, 93, 255, 0.1);
        cursor: pointer;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .orchestrator-circle:hover {
        transform: scale(1.08);
        box-shadow: 0 12px 40px rgba(96, 93, 255, 0.45), 0 0 0 8px rgba(96, 93, 255, 0.1);
    }
    .orchestrator-circle .material-symbols-outlined {
        font-size: 32px;
        margin-bottom: 2px;
    }
    .orchestrator-label {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    /* Agent Nodes */
    .agent-node {
        position: absolute;
        z-index: 10;
        text-align: center;
        cursor: pointer;
        transition: transform 0.3s;
    }
    .agent-node:hover {
        transform: scale(1.1);
    }
    .agent-node-circle {
        width: 88px;
        height: 88px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        position: relative;
        transition: box-shadow 0.3s;
    }
    .agent-node-circle .material-symbols-outlined {
        font-size: 28px;
        margin-bottom: 2px;
    }
    .agent-node-name {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    /* Agent positions - orbital */
    .agent-node.archaeologist { top: 8%; left: 50%; transform: translateX(-50%); }
    .agent-node.historian { top: 50%; right: 6%; transform: translateY(-50%); }
    .agent-node.negotiator { bottom: 8%; left: 50%; transform: translateX(-50%); }
    .agent-node.chronicler { top: 50%; left: 6%; transform: translateY(-50%); }

    .agent-node.archaeologist:hover { transform: translateX(-50%) scale(1.1); }
    .agent-node.historian:hover { transform: translateY(-50%) scale(1.1); }
    .agent-node.negotiator:hover { transform: translateX(-50%) scale(1.1); }
    .agent-node.chronicler:hover { transform: translateY(-50%) scale(1.1); }

    /* Agent colors */
    .agent-node.archaeologist .agent-node-circle {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        box-shadow: 0 6px 24px rgba(13, 110, 253, 0.35);
    }
    .agent-node.historian .agent-node-circle {
        background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);
        box-shadow: 0 6px 24px rgba(253, 126, 20, 0.35);
    }
    .agent-node.negotiator .agent-node-circle {
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
        box-shadow: 0 6px 24px rgba(220, 53, 69, 0.35);
    }
    .agent-node.chronicler .agent-node-circle {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        box-shadow: 0 6px 24px rgba(25, 135, 84, 0.35);
    }

    /* Status indicator */
    .agent-status-dot {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid white;
    }
    .agent-status-dot.idle { background: #adb5bd; }
    .agent-status-dot.active { background: #0dcaf0; animation: pulse 1.5s ease-in-out infinite; }
    .agent-status-dot.complete { background: #198754; }
    .agent-status-dot.error { background: #dc3545; }

    @keyframes pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(13, 202, 240, 0.5); }
        50% { box-shadow: 0 0 0 6px rgba(13, 202, 240, 0); }
    }

    /* Agent info label below node */
    .agent-info-label {
        margin-top: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #333;
        white-space: nowrap;
    }
    .agent-info-sublabel {
        font-size: 10px;
        font-weight: 400;
        color: #6c757d;
    }

    /* Flow order badges */
    .flow-order {
        position: absolute;
        top: -6px;
        left: -6px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #333;
        color: white;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        z-index: 2;
    }

    /* Azure service badges */
    .azure-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        justify-content: center;
        margin-top: 4px;
        max-width: 140px;
    }
    .azure-badge {
        font-size: 9px;
        padding: 1px 6px;
        border-radius: 8px;
        background: rgba(0,0,0,0.06);
        color: #555;
        white-space: nowrap;
    }

    /* SK Pattern label */
    .sk-pattern-label {
        position: absolute;
        bottom: 12px;
        right: 16px;
        z-index: 5;
        font-size: 10px;
        color: #8B5CF6;
        opacity: 0.7;
        letter-spacing: 0.5px;
    }

    /* Timeline chart container */
    .pipeline-timeline-chart {
        min-height: 200px;
    }
</style>
@endpush

@section('content')
    {{-- Agent Map Visualization --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-1">
                        <span class="material-symbols-outlined align-middle me-1">hub</span>
                        Agent Pipeline Orchestration
                    </h5>
                    <p class="text-secondary fs-13 mb-0">Real-time view of the DriftWatch multi-agent system. Each agent runs as an Azure Function, orchestrated via Semantic Kernel patterns.</p>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">cloud</span>
                        Azure Functions V2
                    </span>
                    <span class="badge bg-purple bg-opacity-10 text-purple px-3 py-2" style="color: #8B5CF6 !important; background: rgba(139,92,246,0.1) !important;">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">psychology</span>
                        Semantic Kernel
                    </span>
                </div>
            </div>

            <div class="agent-map-container" id="agentMap">
                {{-- SVG Connection Lines --}}
                <svg class="agent-connections" id="connectionsSvg">
                    {{-- Lines drawn by JS based on node positions --}}
                </svg>

                {{-- Central Orchestrator Hub --}}
                <div class="orchestrator-hub">
                    <div class="orchestrator-circle">
                        <span class="material-symbols-outlined">hub</span>
                        <span class="orchestrator-label">Orchestrator</span>
                    </div>
                    <div class="mt-2">
                        <span class="azure-badge">Semantic Kernel</span>
                    </div>
                </div>

                {{-- Agent 1: Archaeologist (top) --}}
                <div class="agent-node archaeologist" data-agent="archaeologist">
                    <div class="agent-node-circle">
                        <span class="flow-order">1</span>
                        <span class="agent-status-dot {{ $agentStatuses['archaeologist'] }}"></span>
                        <span class="material-symbols-outlined">explore</span>
                        <span class="agent-node-name">Archaeologist</span>
                    </div>
                    <div class="agent-info-label">Blast Radius Mapper</div>
                    <div class="azure-badges">
                        <span class="azure-badge">Azure Functions</span>
                        <span class="azure-badge">Azure OpenAI</span>
                    </div>
                </div>

                {{-- Agent 2: Historian (right) --}}
                <div class="agent-node historian" data-agent="historian">
                    <div class="agent-node-circle">
                        <span class="flow-order">2</span>
                        <span class="agent-status-dot {{ $agentStatuses['historian'] }}"></span>
                        <span class="material-symbols-outlined">history</span>
                        <span class="agent-node-name">Historian</span>
                    </div>
                    <div class="agent-info-label">Risk Calculator</div>
                    <div class="azure-badges">
                        <span class="azure-badge">Azure Functions</span>
                        <span class="azure-badge">Azure OpenAI</span>
                        <span class="azure-badge">Azure MySQL</span>
                    </div>
                </div>

                {{-- Agent 3: Negotiator (bottom) --}}
                <div class="agent-node negotiator" data-agent="negotiator">
                    <div class="agent-node-circle">
                        <span class="flow-order">3</span>
                        <span class="agent-status-dot {{ $agentStatuses['negotiator'] }}"></span>
                        <span class="material-symbols-outlined">gavel</span>
                        <span class="agent-node-name">Negotiator</span>
                    </div>
                    <div class="agent-info-label">Deploy Gatekeeper</div>
                    <div class="azure-badges">
                        <span class="azure-badge">Azure Functions</span>
                        <span class="azure-badge">Azure OpenAI</span>
                        <span class="azure-badge">Content Safety</span>
                    </div>
                </div>

                {{-- Agent 4: Chronicler (left) --}}
                <div class="agent-node chronicler" data-agent="chronicler">
                    <div class="agent-node-circle">
                        <span class="flow-order">4</span>
                        <span class="agent-status-dot {{ $agentStatuses['chronicler'] }}"></span>
                        <span class="material-symbols-outlined">auto_stories</span>
                        <span class="agent-node-name">Chronicler</span>
                    </div>
                    <div class="agent-info-label">Feedback Recorder</div>
                    <div class="azure-badges">
                        <span class="azure-badge">Azure Functions</span>
                        <span class="azure-badge">Azure OpenAI</span>
                        <span class="azure-badge">App Insights</span>
                    </div>
                </div>

                {{-- SK Pattern Watermark --}}
                <div class="sk-pattern-label">
                    Planner → Skills → Memory
                </div>
            </div>
        </div>
    </div>

    {{-- Agent Stats Cards --}}
    <div class="row mb-4">
        @foreach($agentStats as $key => $agent)
            <div class="col-xl-3 col-sm-6 mb-4">
                <div class="card bg-white border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="wh-44 bg-{{ $agent['color'] }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                                <span class="material-symbols-outlined text-{{ $agent['color'] }}">{{ $agent['icon'] }}</span>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">{{ $agent['name'] }}</h6>
                                <span class="text-secondary fs-12">{{ $agent['subtitle'] }}</span>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <span class="d-block fs-20 fw-bold">{{ $agent['total_runs'] }}</span>
                                <span class="fs-11 text-secondary">Runs</span>
                            </div>
                            <div class="col-4">
                                <span class="d-block fs-20 fw-bold text-success">{{ $agent['success_rate'] }}%</span>
                                <span class="fs-11 text-secondary">Success</span>
                            </div>
                            <div class="col-4">
                                <span class="d-block fs-20 fw-bold text-info">{{ $agent['avg_time'] }}s</span>
                                <span class="fs-11 text-secondary">Avg Time</span>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-top">
                            <a href="{{ route('driftwatch.agents.' . $key) }}" class="text-decoration-none fs-13 text-primary fw-medium">
                                View Details <span class="material-symbols-outlined align-middle" style="font-size: 14px;">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Pipeline Execution Timeline + Azure Services --}}
    <div class="row mb-4">
        <div class="col-xl-8 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-1">
                        <span class="material-symbols-outlined align-middle me-1">timeline</span>
                        Pipeline Execution Timeline
                    </h5>
                    <p class="text-secondary fs-13 mb-3">Recent agent pipeline runs with risk scores over time. Monitored via Application Insights + Azure Monitor.</p>
                    <div id="pipelineTimelineChart" class="pipeline-timeline-chart"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">cloud</span>
                        Azure Services Stack
                    </h5>
                    @php
                        $azureServices = [
                            ['name' => 'Azure OpenAI', 'desc' => 'GPT-4.1-mini agent reasoning', 'icon' => 'psychology', 'color' => 'primary', 'status' => config('services.azure_openai.endpoint') ? 'active' : 'inactive'],
                            ['name' => 'Azure Functions', 'desc' => 'Serverless agent hosting (V2)', 'icon' => 'bolt', 'color' => 'warning', 'status' => config('services.agents.archaeologist_url') ? 'active' : 'inactive'],
                            ['name' => 'Azure MySQL', 'desc' => 'Flexible Server for data', 'icon' => 'storage', 'color' => 'info', 'status' => 'active'],
                            ['name' => 'App Insights', 'desc' => 'Telemetry & monitoring', 'icon' => 'monitoring', 'color' => 'success', 'status' => config('services.app_insights.connection_string') ? 'active' : 'inactive'],
                            ['name' => 'Content Safety', 'desc' => 'AI output filtering', 'icon' => 'shield', 'color' => 'danger', 'status' => 'configured'],
                            ['name' => 'Key Vault', 'desc' => 'Secrets management', 'icon' => 'key', 'color' => 'secondary', 'status' => 'configured'],
                            ['name' => 'Service Bus', 'desc' => 'Async agent messaging', 'icon' => 'swap_horiz', 'color' => 'primary', 'status' => 'configured'],
                            ['name' => 'AI Foundry', 'desc' => 'Model management', 'icon' => 'model_training', 'color' => 'warning', 'status' => 'configured'],
                            ['name' => 'Azure Monitor', 'desc' => 'Alerts & diagnostics', 'icon' => 'notifications', 'color' => 'info', 'status' => config('services.app_insights.connection_string') ? 'active' : 'inactive'],
                            ['name' => 'Semantic Kernel', 'desc' => 'Agent orchestration SDK', 'icon' => 'hub', 'color' => 'success', 'status' => 'integrated'],
                        ];
                    @endphp
                    <div class="list-group list-group-flush">
                        @foreach($azureServices as $svc)
                            <div class="d-flex align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="wh-30 bg-{{ $svc['color'] }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0">
                                    <span class="material-symbols-outlined text-{{ $svc['color'] }}" style="font-size: 14px;">{{ $svc['icon'] }}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-medium fs-13">{{ $svc['name'] }}</span>
                                    <br><span class="text-secondary fs-11">{{ $svc['desc'] }}</span>
                                </div>
                                <span class="badge bg-{{ $svc['status'] === 'active' ? 'success' : ($svc['status'] === 'integrated' ? 'primary' : 'secondary') }} bg-opacity-10 text-{{ $svc['status'] === 'active' ? 'success' : ($svc['status'] === 'integrated' ? 'primary' : 'secondary') }}" style="font-size: 10px;">
                                    {{ ucfirst($svc['status']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Pipeline Runs --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">list_alt</span>
                Recent Pipeline Runs
            </h5>
            @if($recentRuns->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="fw-medium text-secondary">PR</th>
                                <th class="fw-medium text-secondary">Repository</th>
                                <th class="fw-medium text-secondary">Archaeologist</th>
                                <th class="fw-medium text-secondary">Historian</th>
                                <th class="fw-medium text-secondary">Negotiator</th>
                                <th class="fw-medium text-secondary">Chronicler</th>
                                <th class="fw-medium text-secondary">Risk</th>
                                <th class="fw-medium text-secondary">Decision</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentRuns as $pr)
                                <tr>
                                    <td><span class="fw-bold text-primary">#{{ $pr->pr_number }}</span></td>
                                    <td class="text-secondary fs-13">{{ $pr->repo_full_name }}</td>
                                    <td>
                                        <span class="material-symbols-outlined {{ $pr->blastRadius ? 'text-success' : 'text-secondary' }}" style="font-size: 18px;">
                                            {{ $pr->blastRadius ? 'check_circle' : 'radio_button_unchecked' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="material-symbols-outlined {{ $pr->riskAssessment ? 'text-success' : 'text-secondary' }}" style="font-size: 18px;">
                                            {{ $pr->riskAssessment ? 'check_circle' : 'radio_button_unchecked' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="material-symbols-outlined {{ $pr->deploymentDecision ? 'text-success' : 'text-secondary' }}" style="font-size: 18px;">
                                            {{ $pr->deploymentDecision ? 'check_circle' : 'radio_button_unchecked' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="material-symbols-outlined {{ $pr->deploymentOutcome ? 'text-success' : 'text-secondary' }}" style="font-size: 18px;">
                                            {{ $pr->deploymentOutcome ? 'check_circle' : 'radio_button_unchecked' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($pr->riskAssessment)
                                            <span class="badge bg-{{ $pr->risk_color }} bg-opacity-10 text-{{ $pr->risk_color }} fw-bold px-2 py-1">
                                                {{ $pr->riskAssessment->risk_score }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pr->deploymentDecision)
                                            <span class="badge bg-{{ $pr->deploymentDecision->decision_color }} bg-opacity-10 text-{{ $pr->deploymentDecision->decision_color }} px-2 py-1 text-capitalize fs-11">
                                                {{ str_replace('_', ' ', $pr->deploymentDecision->decision) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-outline-primary py-1 px-2">
                                            <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-secondary">
                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">play_circle</span>
                    <p class="mb-0">No pipeline runs yet. Analyze a PR from the <a href="{{ route('driftwatch.index') }}">Dashboard</a> to see results here.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Observability Panel --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold mb-1">
                        <span class="material-symbols-outlined align-middle me-1">monitoring</span>
                        Observability & Telemetry
                    </h5>
                    <p class="text-secondary fs-13 mb-0">OpenTelemetry traces and spans tracked via Application Insights and Azure Monitor.</p>
                </div>
                @if(config('services.app_insights.connection_string'))
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">check_circle</span>
                        App Insights Connected
                    </span>
                @endif
            </div>
            <div class="row">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <span class="d-block text-secondary fs-12 mb-1">Total Traces</span>
                        <span class="fs-24 fw-bold">{{ $observability['total_traces'] }}</span>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <span class="d-block text-secondary fs-12 mb-1">Avg Spans/Run</span>
                        <span class="fs-24 fw-bold">4</span>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <span class="d-block text-secondary fs-12 mb-1">Error Rate</span>
                        <span class="fs-24 fw-bold text-{{ $observability['error_rate'] > 10 ? 'danger' : 'success' }}">{{ $observability['error_rate'] }}%</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded-3 text-center">
                        <span class="d-block text-secondary fs-12 mb-1">Avg Latency</span>
                        <span class="fs-24 fw-bold">{{ $observability['avg_latency'] }}s</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Draw SVG connection lines from orchestrator to each agent
    function drawConnections() {
        var svg = document.getElementById('connectionsSvg');
        var container = document.getElementById('agentMap');
        if (!svg || !container) return;

        var containerRect = container.getBoundingClientRect();
        var hub = container.querySelector('.orchestrator-circle');
        var hubRect = hub.getBoundingClientRect();
        var hubX = hubRect.left - containerRect.left + hubRect.width / 2;
        var hubY = hubRect.top - containerRect.top + hubRect.height / 2;

        svg.innerHTML = '';

        var agents = ['archaeologist', 'historian', 'negotiator', 'chronicler'];
        var statuses = @json($agentStatuses);

        agents.forEach(function(agent) {
            var node = container.querySelector('.agent-node.' + agent + ' .agent-node-circle');
            if (!node) return;
            var nodeRect = node.getBoundingClientRect();
            var nodeX = nodeRect.left - containerRect.left + nodeRect.width / 2;
            var nodeY = nodeRect.top - containerRect.top + nodeRect.height / 2;

            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', hubX);
            line.setAttribute('y1', hubY);
            line.setAttribute('x2', nodeX);
            line.setAttribute('y2', nodeY);
            line.setAttribute('class', 'connection-line ' + (statuses[agent] || 'idle'));
            svg.appendChild(line);
        });

        // Draw sequential flow lines between agents (1→2→3→4)
        var flowPairs = [['archaeologist', 'historian'], ['historian', 'negotiator'], ['negotiator', 'chronicler']];
        flowPairs.forEach(function(pair) {
            var node1 = container.querySelector('.agent-node.' + pair[0] + ' .agent-node-circle');
            var node2 = container.querySelector('.agent-node.' + pair[1] + ' .agent-node-circle');
            if (!node1 || !node2) return;
            var r1 = node1.getBoundingClientRect();
            var r2 = node2.getBoundingClientRect();

            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', r1.left - containerRect.left + r1.width / 2);
            line.setAttribute('y1', r1.top - containerRect.top + r1.height / 2);
            line.setAttribute('x2', r2.left - containerRect.left + r2.width / 2);
            line.setAttribute('y2', r2.top - containerRect.top + r2.height / 2);
            line.setAttribute('class', 'connection-line active');
            line.style.opacity = '0.15';
            line.style.strokeDasharray = '4 6';
            svg.appendChild(line);
        });
    }

    drawConnections();
    window.addEventListener('resize', drawConnections);

    // Click agent nodes to navigate
    document.querySelectorAll('.agent-node').forEach(function(node) {
        node.addEventListener('click', function() {
            var agent = this.dataset.agent;
            window.location.href = '/driftwatch/agents/' + agent;
        });
    });

    // Pipeline Timeline Chart
    @php
        $timelineData = \App\Models\PullRequest::with('riskAssessment')
            ->whereHas('riskAssessment')
            ->latest()
            ->take(15)
            ->get()
            ->reverse()
            ->values();
        $timelineLabels = $timelineData->map(fn($pr) => 'PR #' . $pr->pr_number);
        $timelineScores = $timelineData->map(fn($pr) => $pr->riskAssessment->risk_score ?? 0);
    @endphp

    @if($timelineScores->count() > 0)
    new ApexCharts(document.querySelector("#pipelineTimelineChart"), {
        series: [{
            name: 'Risk Score',
            data: @json($timelineScores)
        }],
        chart: {
            type: 'area',
            height: 200,
            toolbar: { show: false },
            fontFamily: 'inherit',
            sparkline: { enabled: false }
        },
        colors: ['#605DFF'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 100]
            }
        },
        stroke: { curve: 'smooth', width: 2.5 },
        xaxis: {
            categories: @json($timelineLabels),
            labels: { style: { fontSize: '10px' } }
        },
        yaxis: {
            max: 100,
            labels: { formatter: function(val) { return Math.round(val); } }
        },
        tooltip: {
            y: { formatter: function(val) { return val + ' / 100 risk'; } }
        },
        annotations: {
            yaxis: [
                { y: 75, borderColor: '#dc3545', strokeDashArray: 3, label: { text: 'Block', style: { color: '#dc3545', background: 'transparent', fontSize: '9px' } } },
                { y: 50, borderColor: '#fd7e14', strokeDashArray: 3, label: { text: 'Review', style: { color: '#fd7e14', background: 'transparent', fontSize: '9px' } } }
            ]
        }
    }).render();
    @else
    document.querySelector("#pipelineTimelineChart").innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-secondary py-5"><div class="text-center"><span class="material-symbols-outlined d-block mb-2" style="font-size: 40px;">timeline</span>Pipeline timeline will appear after PR analyses</div></div>';
    @endif
});
</script>
@endpush
