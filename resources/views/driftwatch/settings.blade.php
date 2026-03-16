{{-- resources/views/driftwatch/settings.blade.php --}}
{{-- DriftWatch configuration settings page with pipeline orchestration --}}
@extends('layouts.app')

@section('title', 'Settings')
@section('heading', 'Configuration')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Settings</span>
    </li>
@endsection

@push('styles')
<style>
    .config-card { transition: all 0.2s; border: 2px solid transparent !important; }
    .config-card.active-template { border-color: #605DFF !important; }
    .config-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .agent-toggle { cursor: pointer; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; transition: all 0.15s; }
    .agent-toggle:hover { border-color: #605DFF; }
    .agent-toggle.disabled-agent { opacity: 0.5; background: #f8fafc; }
    [data-theme=dark] .agent-toggle { border-color: #334155; }
    [data-theme=dark] .agent-toggle.disabled-agent { background: #0f172a; }
    .env-row { padding: 12px 16px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 8px; }
    [data-theme=dark] .env-row { border-color: #334155; }
    .rule-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-size: 12px; background: #f1f5f9; }
    [data-theme=dark] .rule-badge { background: #1e293b; }
</style>
@endpush

@section('content')
    {{-- Pipeline Configuration --}}
    <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                <h5 class="fw-bold mb-0 dw-section-title">
                    <span class="material-symbols-outlined align-middle text-primary" style="font-size: 22px;">tune</span>
                    Pipeline Orchestration
                </h5>
                <form action="{{ route('driftwatch.settings.pipeline.reset') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Reset all pipeline configs to defaults?')">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:14px;">restart_alt</span> Reset to Defaults
                    </button>
                </form>
            </div>

            {{-- Template Selector Cards --}}
            <div class="row g-3 mb-4">
                @foreach($pipelineConfigs as $cfg)
                    @php
                        $templateIcon = match($cfg->name) {
                            'quick' => 'bolt',
                            'gated' => 'security',
                            default => 'play_circle',
                        };
                        $templateColor = match($cfg->name) {
                            'quick' => 'warning',
                            'gated' => 'danger',
                            default => 'primary',
                        };
                    @endphp
                    <div class="col-md-4">
                        <div class="card config-card rounded-3 h-100 {{ $cfg->is_default ? 'active-template' : '' }}" data-config-id="{{ $cfg->id }}" onclick="selectTemplate({{ $cfg->id }})" style="cursor:pointer;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="wh-36 bg-{{ $templateColor }} bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                        <span class="material-symbols-outlined text-{{ $templateColor }}" style="font-size:18px;">{{ $templateIcon }}</span>
                                    </div>
                                    <div>
                                        <span class="fw-bold fs-14">{{ $cfg->label }}</span>
                                        @if($cfg->is_default)
                                            <span class="badge bg-primary bg-opacity-10 text-primary fs-10 ms-1">Default</span>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-secondary fs-12 mb-2">{{ $cfg->description }}</p>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach(['archaeologist', 'historian', 'negotiator', 'chronicler'] as $agent)
                                        <span class="badge {{ $cfg->{'agent_' . $agent} ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary' }} fs-10">
                                            {{ ucfirst($agent) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Config Editor Form --}}
            @php $editConfig = $defaultConfig; @endphp
            <form action="{{ route('driftwatch.settings.pipeline') }}" method="POST" id="pipelineConfigForm">
                @csrf
                <input type="hidden" name="config_id" id="selectedConfigId" value="{{ $editConfig->id }}">

                {{-- Agent Enable/Disable Toggles --}}
                <h6 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">smart_toy</span>
                    Agent Pipeline
                </h6>
                <div class="row g-3 mb-4">
                    @php
                        $agentMeta = [
                            'archaeologist' => ['icon' => 'explore', 'color' => 'primary', 'label' => 'Archaeologist', 'desc' => 'Blast radius mapping'],
                            'historian' => ['icon' => 'history', 'color' => 'warning', 'label' => 'Historian', 'desc' => 'Risk score calculation'],
                            'negotiator' => ['icon' => 'gavel', 'color' => 'danger', 'label' => 'Negotiator', 'desc' => 'Deploy gate decision'],
                            'chronicler' => ['icon' => 'auto_stories', 'color' => 'success', 'label' => 'Chronicler', 'desc' => 'Feedback loop'],
                        ];
                    @endphp
                    @foreach($agentMeta as $agentKey => $meta)
                        @php $enabled = $editConfig->{'agent_' . $agentKey}; @endphp
                        <div class="col-md-3 col-sm-6">
                            <label class="agent-toggle d-block {{ !$enabled ? 'disabled-agent' : '' }}">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="material-symbols-outlined text-{{ $meta['color'] }}" style="font-size:18px;">{{ $meta['icon'] }}</span>
                                        <span class="fw-bold fs-13">{{ $meta['label'] }}</span>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" name="agent_{{ $agentKey }}" value="1"
                                               {{ $enabled ? 'checked' : '' }}>
                                    </div>
                                </div>
                                <span class="text-secondary fs-11">{{ $meta['desc'] }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                {{-- Approval Gates --}}
                <h6 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">verified_user</span>
                    Approval Gates
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="require_approval_after_scoring" value="1"
                                   id="requireApproval" {{ $editConfig->require_approval_after_scoring ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium fs-13" for="requireApproval">Require manual approval after scoring</label>
                        </div>
                        <small class="text-secondary fs-11">Pipeline pauses after Historian before Negotiator makes its decision.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium fs-13">Auto-approve below score</label>
                        <input type="number" class="form-control form-control-sm" name="auto_approve_below_score"
                               value="{{ $editConfig->auto_approve_below_score }}" min="0" max="100">
                        <small class="text-secondary fs-11">PRs below this score skip the approval gate.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium fs-13">Auto-block above score</label>
                        <input type="number" class="form-control form-control-sm" name="auto_block_above_score"
                               value="{{ $editConfig->auto_block_above_score }}" min="0" max="100">
                        <small class="text-secondary fs-11">PRs above this score are blocked without review.</small>
                    </div>
                </div>

                {{-- Environment Thresholds --}}
                <h6 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">dns</span>
                    Environment Risk Thresholds
                </h6>
                <div class="mb-4">
                    @php $envThresholds = $editConfig->environment_thresholds ?? []; @endphp
                    @foreach(['production' => ['icon' => 'shield', 'color' => 'danger'], 'staging' => ['icon' => 'science', 'color' => 'warning'], 'development' => ['icon' => 'code', 'color' => 'info']] as $envName => $envMeta)
                        @php
                            $envConfig = $envThresholds[$envName] ?? ['risk_threshold' => 50, 'require_approval' => false];
                        @endphp
                        <div class="env-row d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2" style="min-width:140px;">
                                <span class="material-symbols-outlined text-{{ $envMeta['color'] }}" style="font-size:18px;">{{ $envMeta['icon'] }}</span>
                                <span class="fw-bold fs-13 text-capitalize">{{ $envName }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="fs-12 text-secondary text-nowrap">Risk threshold:</label>
                                <input type="number" class="form-control form-control-sm" style="width:70px;"
                                       name="env_{{ $envName }}_threshold" value="{{ $envConfig['risk_threshold'] }}" min="0" max="100">
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="env_{{ $envName }}_approval" value="1"
                                       id="env_{{ $envName }}_approval" {{ !empty($envConfig['require_approval']) ? 'checked' : '' }}>
                                <label class="form-check-label fs-12" for="env_{{ $envName }}_approval">Require approval above threshold</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Retry Settings --}}
                <h6 class="fw-bold mb-3">
                    <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">refresh</span>
                    Retry & Resilience
                </h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-medium fs-13">Max retries per agent</label>
                        <input type="number" class="form-control form-control-sm" name="max_retries_per_agent"
                               value="{{ $editConfig->max_retries_per_agent }}" min="0" max="5">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="retry_on_timeout" value="1"
                                   id="retryTimeout" {{ $editConfig->retry_on_timeout ? 'checked' : '' }}>
                            <label class="form-check-label fw-medium fs-13" for="retryTimeout">Retry on timeout</label>
                        </div>
                    </div>
                </div>

                {{-- Conditional Rules Display --}}
                @if(!empty($editConfig->conditional_rules))
                    <h6 class="fw-bold mb-3">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">rule</span>
                        Conditional Rules
                    </h6>
                    <div class="mb-4">
                        @foreach($editConfig->conditional_rules as $rule)
                            <div class="rule-badge mb-1">
                                <span class="material-symbols-outlined text-warning" style="font-size:14px;">
                                    {{ ($rule['action'] ?? '') === 'skip_all' ? 'skip_next' : 'security' }}
                                </span>
                                <span>{{ $rule['label'] ?? $rule['pattern'] ?? 'Rule' }}</span>
                                <code class="ms-1 fs-10 text-primary">{{ $rule['pattern'] ?? $rule['threshold'] ?? '' }}</code>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Save Button --}}
                <div class="d-flex align-items-center gap-3 pt-3 border-top">
                    <button type="submit" class="btn btn-primary px-4">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">save</span>
                        Save Configuration
                    </button>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" name="set_default" value="1" id="setDefault">
                        <label class="form-check-label fs-13" for="setDefault">Set as default template</label>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        {{-- GitHub Integration --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">code</span>
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
                        <label class="form-label fw-medium d-flex align-items-center gap-2">
                            GitHub Token
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" data-bs-toggle="modal" data-bs-target="#githubTokenHelpModal">
                                <span class="material-symbols-outlined align-middle" style="font-size:14px;">help</span> How to create
                            </button>
                        </label>
                        <form action="{{ route('driftwatch.settings.save-token') }}" method="POST">
                            @csrf
                            <div class="input-group">
                                <input type="text" class="form-control" name="github_token" id="githubTokenInput"
                                       placeholder="{{ config('services.github.token') ? 'Token configured (paste new one to update)' : 'Paste your GitHub token here' }}"
                                       autocomplete="off">
                                <button type="submit" class="btn btn-primary">
                                    <span class="material-symbols-outlined align-middle" style="font-size:16px;">save</span> Save
                                </button>
                            </div>
                            @if(config('services.github.token'))
                                <small class="text-success"><span class="material-symbols-outlined align-middle" style="font-size:13px;">check_circle</span> Token is configured ({{ Str::mask(config('services.github.token'), '*', 4, -4) }})</small>
                            @else
                                <small class="text-warning"><span class="material-symbols-outlined align-middle" style="font-size:13px;">warning</span> No token — agents cannot read PR code</small>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Agent Configuration --}}
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">smart_toy</span>
                        AI Agent Endpoints
                    </h5>
                    @php
                        $agents = [
                            'Archaeologist' => config('services.agents.archaeologist_url'),
                            'Historian' => config('services.agents.historian_url'),
                            'Negotiator' => config('services.agents.negotiator_url'),
                            'Chronicler' => config('services.agents.chronicler_url'),
                            'Security' => config('services.agents.security_url'),
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
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">psychology</span>
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
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">monitoring</span>
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

    {{-- Teams & Notifications --}}
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">chat</span>
                        Microsoft Teams Notifications
                    </h5>
                    @php
                        $teamsUrl = config('services.teams.webhook_url');
                        $teamsConfigured = !empty($teamsUrl);
                        $teamsThreshold = config('services.teams.notify_above_score', 60);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">Webhook URL</span>
                            <br>
                            <small class="text-secondary">
                                @if($teamsConfigured)
                                    {{ substr($teamsUrl, 0, 40) }}********
                                @else
                                    Not configured
                                @endif
                            </small>
                        </div>
                        <span class="badge bg-{{ $teamsConfigured ? 'success' : 'warning' }} bg-opacity-10 text-{{ $teamsConfigured ? 'success' : 'warning' }} px-3 py-2">
                            {{ $teamsConfigured ? 'Connected' : 'Not Set' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Notification Threshold</span>
                            <br>
                            <small class="text-secondary">Sends alerts when risk score exceeds {{ $teamsThreshold }}</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                            Score > {{ $teamsThreshold }}
                        </span>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-secondary">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">info</span>
                            Set <code>TEAMS_WEBHOOK_URL</code> in your <code>.env</code> file. Optionally set <code>TEAMS_NOTIFY_ABOVE_SCORE</code> (default: 60).
                            Adaptive Card notifications include Approve/Block buttons for human decision loop.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">code</span>
                        Code Analysis
                    </h5>
                    @php
                        $ghToken = config('services.github.token');
                        $codeAnalysisEnabled = !empty($ghToken);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">PR Code Fetching</span>
                            <br>
                            <small class="text-secondary">
                                @if($codeAnalysisEnabled)
                                    Agents receive full source code, diffs, and file contents from GitHub API
                                @else
                                    Agents only see file names (no code context). Set GITHUB_TOKEN to enable.
                                @endif
                            </small>
                        </div>
                        <span class="badge bg-{{ $codeAnalysisEnabled ? 'success' : 'warning' }} bg-opacity-10 text-{{ $codeAnalysisEnabled ? 'success' : 'warning' }} px-3 py-2">
                            {{ $codeAnalysisEnabled ? 'Full Code' : 'Metadata Only' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Analysis Mode</span>
                            <br>
                            <small class="text-secondary">
                                @if(config('services.agents.archaeologist_url'))
                                    Azure Function agents with Azure OpenAI
                                @else
                                    Local mock analysis (built-in code classifier)
                                @endif
                            </small>
                        </div>
                        <span class="badge bg-{{ config('services.agents.archaeologist_url') ? 'success' : 'info' }} bg-opacity-10 text-{{ config('services.agents.archaeologist_url') ? 'success' : 'info' }} px-3 py-2">
                            {{ config('services.agents.archaeologist_url') ? 'AI Agents' : 'Local Analysis' }}
                        </span>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-secondary">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">info</span>
                            When GITHUB_TOKEN is set, the pipeline fetches PR diffs and up to 30 file contents for deep code analysis.
                            High-risk files (migrations, middleware, auth, config, controllers) are automatically fetched in full.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Azure Speech & AI Search --}}
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">record_voice_over</span>
                        Azure Speech (TTS)
                    </h5>
                    @php
                        $speechKey = config('services.azure_speech.key');
                        $speechConfigured = !empty($speechKey);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">Endpoint</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_speech.endpoint') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-{{ $speechConfigured ? 'success' : 'warning' }} bg-opacity-10 text-{{ $speechConfigured ? 'success' : 'warning' }} px-3 py-2">
                            {{ $speechConfigured ? 'Connected' : 'Not Set' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Region</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_speech.region') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                            {{ config('services.azure_speech.region') ?: '—' }}
                        </span>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-secondary">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">info</span>
                            Text-to-Speech narration for PR analysis cards. Uses <code>en-US-JennyNeural</code> voice via SSML.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100 dw-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 dw-section-title">
                        <span class="material-symbols-outlined align-middle">security</span>
                        Azure AI Search (Security RAG)
                    </h5>
                    @php
                        $searchKey = config('services.azure_ai_search.key');
                        $searchConfigured = !empty($searchKey);
                    @endphp
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="fw-medium">Search Endpoint</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_ai_search.endpoint') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-{{ $searchConfigured ? 'success' : 'warning' }} bg-opacity-10 text-{{ $searchConfigured ? 'success' : 'warning' }} px-3 py-2">
                            {{ $searchConfigured ? 'Connected' : 'Not Set' }}
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div>
                            <span class="fw-medium">Index Name</span>
                            <br>
                            <small class="text-secondary">{{ config('services.azure_ai_search.index') ?: 'Not configured' }}</small>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                            {{ config('services.azure_ai_search.index') ?: '—' }}
                        </span>
                    </div>
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-secondary">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">info</span>
                            RAG pipeline for the Security Agent. Indexes OWASP, CVE, and CWE knowledge for vulnerability-aware PR analysis.
                            Set <code>AZURE_AI_SEARCH_ENDPOINT</code>, <code>AZURE_AI_SEARCH_KEY</code>, and <code>AGENT_SECURITY_URL</code> in <code>.env</code>.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Azure Architecture Diagram --}}
    <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 dw-section-title">
                <span class="material-symbols-outlined align-middle">architecture</span>
                Azure Architecture
            </h5>
            <p class="text-secondary fs-13 mb-3">DriftWatch integrates 14 Azure services for a complete agentic DevOps pipeline with security intelligence.</p>
            <div class="mermaid" id="architectureDiagram">
flowchart LR
    subgraph Input["Input"]
        GH["GitHub PR"]
        TM["MS Teams"]
    end
    subgraph App["Azure App Service"]
        LV["Laravel 11.x"]
        SK["Semantic Kernel<br/>Orchestrator"]
        TTS["Azure Speech<br/>TTS"]
    end
    subgraph Agents["Azure Functions V2"]
        A1["Archaeologist"]
        A2["Historian"]
        A3["Negotiator"]
        A4["Chronicler"]
        A5["Security Agent"]
    end
    subgraph AI["Azure AI"]
        AOAI["Azure OpenAI<br/>GPT-4.1-mini"]
        CS["Content Safety"]
        AIF["AI Foundry"]
    end
    subgraph RAG["Security RAG"]
        AIS["Azure AI Search"]
        KB["Security<br/>Knowledge Base"]
    end
    subgraph Data["Azure Data"]
        DB["Azure MySQL"]
        KV["Key Vault"]
        SB["Service Bus"]
    end
    subgraph Obs["Observability"]
        INS["App Insights"]
        MON["Azure Monitor"]
    end

    GH -->|Webhook| LV
    LV --> SK
    SK --> A1 & A2 & A3 & A4
    SK --> A5
    A1 & A2 & A3 & A4 --> AOAI
    A5 --> AOAI
    A5 --> AIS
    AIS --> KB
    A3 --> CS
    A3 -->|Adaptive Card| TM
    TM -->|Approve/Block| LV
    LV --> TTS
    SK --> DB
    SK -.-> SB
    LV -.-> KV
    LV -.-> INS
    LV -.-> AIF
    INS --> MON

    style GH fill:#24292e,color:#fff
    style TM fill:#6264A7,color:#fff
    style LV fill:#FF2D20,color:#fff
    style SK fill:#605DFF,color:#fff
    style TTS fill:#0078D4,color:#fff
    style A1 fill:#0d6efd,color:#fff
    style A2 fill:#fd7e14,color:#fff
    style A3 fill:#dc3545,color:#fff
    style A4 fill:#198754,color:#fff
    style A5 fill:#9333EA,color:#fff
    style AOAI fill:#0078D4,color:#fff
    style CS fill:#0078D4,color:#fff
    style AIF fill:#0078D4,color:#fff
    style AIS fill:#9333EA,color:#fff
    style KB fill:#7C3AED,color:#fff
    style DB fill:#0078D4,color:#fff
    style KV fill:#0078D4,color:#fff
    style SB fill:#0078D4,color:#fff
    style INS fill:#68217A,color:#fff
    style MON fill:#68217A,color:#fff
            </div>
        </div>
    </div>

    {{-- Environment Info --}}
    <div class="card bg-white border-0 rounded-3 mb-4 dw-card">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 dw-section-title">
                <span class="material-symbols-outlined align-middle">info</span>
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

    {{-- GitHub Token Help Modal --}}
    <div class="modal fade" id="githubTokenHelpModal" tabindex="-1" aria-labelledby="githubTokenHelpLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="githubTokenHelpLabel">
                        <span class="material-symbols-outlined align-middle me-2">key</span>
                        How to Create a GitHub Personal Access Token
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info fs-13 mb-4">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">info</span>
                        DriftWatch needs a GitHub token to read PR diffs, file contents, and post risk assessment comments. Without it, agents only see file names — not actual code.
                    </div>

                    <h6 class="fw-bold mb-3">Step-by-step instructions:</h6>

                    <div class="d-flex gap-3 mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">1</span>
                        <div>
                            <strong>Go to GitHub Token Settings</strong><br>
                            <span class="fs-13 text-secondary">Open <a href="https://github.com/settings/tokens?type=beta" target="_blank" class="text-primary">github.com/settings/tokens</a> (or: GitHub avatar → Settings → Developer settings → Personal access tokens → Fine-grained tokens)</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">2</span>
                        <div>
                            <strong>Click "Generate new token"</strong><br>
                            <span class="fs-13 text-secondary">Choose <strong>Fine-grained token</strong> (recommended) or Classic token</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">3</span>
                        <div>
                            <strong>Configure the token</strong><br>
                            <ul class="fs-13 text-secondary mb-0 mt-1">
                                <li><strong>Name:</strong> DriftWatch</li>
                                <li><strong>Expiration:</strong> 90 days (or custom)</li>
                                <li><strong>Repository access:</strong> Select the repos you want DriftWatch to analyze</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">4</span>
                        <div>
                            <strong>Set permissions</strong><br>
                            <ul class="fs-13 text-secondary mb-0 mt-1">
                                <li><strong>Contents:</strong> Read (to read file contents)</li>
                                <li><strong>Pull requests:</strong> Read & Write (to read diffs and post comments)</li>
                                <li><strong>Issues:</strong> Read & Write (for Copilot Agent Mode integration)</li>
                                <li><strong>Checks:</strong> Read & Write (to create check runs)</li>
                                <li><strong>Webhooks:</strong> Read & Write (if you want DriftWatch to auto-register)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">5</span>
                        <div>
                            <strong>Click "Generate token" and copy it</strong><br>
                            <span class="fs-13 text-secondary">You'll only see it once! Copy it immediately.</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4 p-3 bg-light rounded-3">
                        <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:28px;height:28px;">6</span>
                        <div>
                            <strong>Paste it into the GitHub Token field on this page and click Save</strong>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2">What does a valid token look like?</h6>
                    <div class="p-3 bg-dark text-white rounded-3 mb-3" style="font-family: monospace; font-size: 13px;">
                        <div class="mb-2">
                            <span class="text-info">Fine-grained token (recommended):</span><br>
                            <code class="text-warning">github_pat_11BU6RA2I0xxxx...xxxx</code>
                        </div>
                        <div>
                            <span class="text-info">Classic token:</span><br>
                            <code class="text-warning">ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx</code>
                        </div>
                    </div>

                    <div class="alert alert-warning fs-13 mb-0">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">security</span>
                        <strong>Security note:</strong> The token is stored in your server's <code>.env</code> file. Never commit <code>.env</code> to version control. Rotate tokens regularly.
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="https://github.com/settings/tokens?type=beta" target="_blank" class="btn btn-outline-dark">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size:16px;">open_in_new</span>
                        Open GitHub Token Settings
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
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

    function selectTemplate(configId) {
        document.getElementById('selectedConfigId').value = configId;
        document.querySelectorAll('.config-card').forEach(function(card) {
            card.classList.remove('active-template');
        });
        document.querySelector('[data-config-id="' + configId + '"]').classList.add('active-template');
    }
</script>
@endpush