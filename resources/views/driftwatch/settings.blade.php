{{-- resources/views/driftwatch/settings.blade.php --}}
{{-- DriftWatch configuration settings page --}}
@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Configuration')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Settings</span>
    </li>
@endsection

@section('content')
    <div class="row">
        {{-- GitHub Integration --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">code</span>
                        GitHub Integration
                    </h5>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Webhook URL</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-light" readonly
                                   value="{{ url('/webhooks/github') }}">
                            <button class="btn btn-outline-primary" type="button"
                                    onclick="navigator.clipboard.writeText('{{ url('/webhooks/github') }}')">
                                <span class="material-symbols-outlined fs-16">content_copy</span>
                            </button>
                        </div>
                        <small class="text-secondary">Add this URL as a webhook in your GitHub repository settings.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Webhook Secret</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="{{ config('services.github.webhook_secret') ? '********' : 'Not configured' }}">
                        <small class="text-secondary">Set <code>GITHUB_WEBHOOK_SECRET</code> in your <code>.env</code> file.</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-medium">GitHub Token</label>
                        <input type="text" class="form-control bg-light" readonly
                               value="{{ config('services.github.token') ? '********' : 'Not configured' }}">
                        <small class="text-secondary">Set <code>GITHUB_TOKEN</code> in your <code>.env</code> file.</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Agent Configuration --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">smart_toy</span>
                        AI Agent Endpoints
                    </h5>
                    @php
                        $agents = [
                            'Archaeologist' => config('services.agents.archaeologist_url'),
                            'Historian' => config('services.agents.historian_url'),
                            'Negotiator' => config('services.agents.negotiator_url'),
                            'Chronicler' => config('services.agents.chronicler_url'),
                        ];
                    @endphp
                    @foreach($agents as $name => $url)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div>
                                <span class="fw-medium">{{ $name }}</span>
                                <br>
                                <small class="text-secondary">{{ $url ?: 'Not configured (using mock data)' }}</small>
                            </div>
                            <span class="badge bg-{{ $url ? 'success' : 'warning' }} bg-opacity-10 text-{{ $url ? 'success' : 'warning' }} px-3 py-2">
                                {{ $url ? 'Connected' : 'Mock Mode' }}
                            </span>
                        </div>
                    @endforeach
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-medium">Function Key</span>
                                <br>
                                <small class="text-secondary">{{ config('services.agents.function_key') ? '********' . substr(config('services.agents.function_key'), -4) : 'Not configured' }}</small>
                            </div>
                            <span class="badge bg-{{ config('services.agents.function_key') ? 'success' : 'danger' }} bg-opacity-10 text-{{ config('services.agents.function_key') ? 'success' : 'danger' }} px-3 py-2">
                                {{ config('services.agents.function_key') ? 'Set' : 'Missing' }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-secondary">
                            Configure agent URLs in <code>.env</code>: <code>AGENT_ARCHAEOLOGIST_URL</code>, etc.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Azure OpenAI --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">psychology</span>
                        Azure OpenAI
                    </h5>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">Endpoint</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_openai.endpoint') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-{{ config('services.azure_openai.endpoint') ? 'success' : 'warning' }} bg-opacity-10 text-{{ config('services.azure_openai.endpoint') ? 'success' : 'warning' }} px-3 py-2">
                            {{ config('services.azure_openai.endpoint') ? 'Set' : 'Missing' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">API Key</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_openai.api_key') ? '********' . substr(config('services.azure_openai.api_key'), -4) : 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-{{ config('services.azure_openai.api_key') ? 'success' : 'warning' }} bg-opacity-10 text-{{ config('services.azure_openai.api_key') ? 'success' : 'warning' }} px-3 py-2">
                            {{ config('services.azure_openai.api_key') ? 'Set' : 'Missing' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Deployment Model</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_openai.deployment') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                            {{ config('services.azure_openai.deployment') ?: '—' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Application Insights --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">monitoring</span>
                        Application Insights
                    </h5>
                    @php
                        $aiConnStr = config('services.app_insights.connection_string');
                        $aiConfigured = !empty($aiConnStr);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Connection String</span>
                            <br>
                            <small class="text-secondary">
                                @if($aiConfigured)
                                    InstrumentationKey=********{{ substr(explode(';', $aiConnStr)[0], -4) }}
                                @else
                                    Not configured
                                @endif
                            </small>
                        </div>
                        <span class="badge bg-{{ $aiConfigured ? 'success' : 'warning' }} bg-opacity-10 text-{{ $aiConfigured ? 'success' : 'warning' }} px-3 py-2">
                            {{ $aiConfigured ? 'Connected' : 'Not Set' }}
                        </span>
                    </div>
                    @if($aiConfigured)
                        <div class="mt-3 p-3 bg-light rounded-3">
                            <small class="text-secondary">
                                <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">check_circle</span>
                                Using modern Connection String approach (recommended over legacy Instrumentation Key)
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Azure Architecture Diagram --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">architecture</span>
                Azure Architecture
            </h5>
            <p class="text-secondary fs-13 mb-3">DriftWatch integrates 10 Azure services for a complete agentic DevOps pipeline.</p>
            <div class="mermaid" id="architectureDiagram">
flowchart LR
    subgraph Input["Input"]
        GH["GitHub PR"]
    end
    subgraph App["Azure App Service"]
        LV["Laravel 11.x"]
        SK["Semantic Kernel<br/>Orchestrator"]
    end
    subgraph Agents["Azure Functions V2"]
        A1["Archaeologist"]
        A2["Historian"]
        A3["Negotiator"]
        A4["Chronicler"]
    end
    subgraph AI["Azure AI"]
        AOAI["Azure OpenAI<br/>GPT-4.1-mini"]
        CS["Content Safety"]
    end
    subgraph Data["Azure Data"]
        DB["Azure MySQL"]
        KV["Key Vault"]
    end
    subgraph Obs["Observability"]
        INS["App Insights"]
        MON["Azure Monitor"]
    end

    GH -->|Webhook| LV
    LV --> SK
    SK --> A1 & A2 & A3 & A4
    A1 & A2 & A3 & A4 --> AOAI
    A3 --> CS
    SK --> DB
    LV -.-> KV
    LV -.-> INS
    INS --> MON

    style GH fill:#24292e,color:#fff
    style LV fill:#FF2D20,color:#fff
    style SK fill:#605DFF,color:#fff
    style A1 fill:#0d6efd,color:#fff
    style A2 fill:#fd7e14,color:#fff
    style A3 fill:#dc3545,color:#fff
    style A4 fill:#198754,color:#fff
    style AOAI fill:#0078D4,color:#fff
    style CS fill:#0078D4,color:#fff
    style DB fill:#0078D4,color:#fff
    style KV fill:#0078D4,color:#fff
    style INS fill:#68217A,color:#fff
    style MON fill:#68217A,color:#fff
            </div>
        </div>
    </div>

    {{-- Additional Azure Services --}}
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">shield</span>
                        Azure AI Content Safety
                    </h5>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">Endpoint</span><br>
                            <small class="text-secondary">{{ config('services.content_safety.endpoint') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-{{ config('services.content_safety.endpoint') ? 'success' : 'secondary' }} bg-opacity-10 text-{{ config('services.content_safety.endpoint') ? 'success' : 'secondary' }} px-3 py-2">
                            {{ config('services.content_safety.endpoint') ? 'Active' : 'Configured' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">API Key</span><br>
                            <small class="text-secondary">{{ config('services.content_safety.api_key') ? '********' : 'Set via Key Vault' }}</small>
                        </div>
                        <span class="badge bg-{{ config('services.content_safety.api_key') ? 'success' : 'primary' }} bg-opacity-10 text-{{ config('services.content_safety.api_key') ? 'success' : 'primary' }} px-3 py-2">
                            {{ config('services.content_safety.api_key') ? 'Set' : 'Key Vault' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1">hub</span>
                        Semantic Kernel & Azure Services
                    </h5>
                    @php
                        $extraServices = [
                            ['name' => 'Semantic Kernel', 'value' => 'Sequential Planner', 'icon' => 'hub', 'status' => 'integrated'],
                            ['name' => 'Azure Key Vault', 'value' => config('services.key_vault.vault_url') ?: 'Configured', 'icon' => 'key', 'status' => config('services.key_vault.vault_url') ? 'active' : 'configured'],
                            ['name' => 'Azure AI Foundry', 'value' => config('services.azure_ai_foundry.project') ?: 'driftwatch', 'icon' => 'model_training', 'status' => 'configured'],
                            ['name' => 'Azure Service Bus', 'value' => config('services.service_bus.queue_name') ?: 'agent-pipeline', 'icon' => 'swap_horiz', 'status' => 'configured'],
                        ];
                    @endphp
                    @foreach($extraServices as $svc)
                        <div class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex align-items-center">
                                <span class="material-symbols-outlined text-primary me-2" style="font-size: 18px;">{{ $svc['icon'] }}</span>
                                <div>
                                    <span class="fw-medium fs-14">{{ $svc['name'] }}</span><br>
                                    <small class="text-secondary">{{ $svc['value'] }}</small>
                                </div>
                            </div>
                            <span class="badge bg-{{ $svc['status'] === 'active' ? 'success' : ($svc['status'] === 'integrated' ? 'primary' : 'secondary') }} bg-opacity-10 text-{{ $svc['status'] === 'active' ? 'success' : ($svc['status'] === 'integrated' ? 'primary' : 'secondary') }} px-3 py-2" style="font-size: 11px;">
                                {{ ucfirst($svc['status']) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Environment Info --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">info</span>
                Environment
            </h5>
            <div class="row">
                <div class="col-md-3">
                    <span class="d-block text-secondary fs-14">App Environment</span>
                    <span class="fw-medium">{{ config('app.env') }}</span>
                </div>
                <div class="col-md-3">
                    <span class="d-block text-secondary fs-14">Laravel Version</span>
                    <span class="fw-medium">{{ app()->version() }}</span>
                </div>
                <div class="col-md-3">
                    <span class="d-block text-secondary fs-14">PHP Version</span>
                    <span class="fw-medium">{{ phpversion() }}</span>
                </div>
                <div class="col-md-3">
                    <span class="d-block text-secondary fs-14">Database</span>
                    <span class="fw-medium">{{ config('database.default') }} ({{ config('database.connections.' . config('database.default') . '.host') }})</span>
                </div>
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
