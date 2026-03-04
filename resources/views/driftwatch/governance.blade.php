{{-- resources/views/driftwatch/governance.blade.php --}}
{{-- Responsible AI & Governance page - Microsoft AI principles compliance --}}
@extends('layouts.app')

@section('title', 'Governance')
@section('heading', 'Responsible AI & Governance')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Governance</span>
    </li>
@endsection

@section('content')
    {{-- Header --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold mb-2">
                        <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 28px;">verified_user</span>
                        Microsoft Responsible AI Compliance
                    </h4>
                    <p class="text-secondary mb-0 fs-14">DriftWatch is built in accordance with Microsoft's six Responsible AI principles. Every agent decision is transparent, auditable, and subject to human oversight.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">shield</span>
                        Azure AI Content Safety
                    </span>
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">key</span>
                        Azure Key Vault
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- 6 Microsoft Responsible AI Principles --}}
    <div class="row">
        {{-- Fairness --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                            <span class="material-symbols-outlined text-primary" style="font-size: 24px;">balance</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Fairness</h6>
                            <span class="text-secondary fs-12">Equitable treatment for all</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Risk scoring uses objective code metrics — file count, complexity, historical incident correlation — never developer identity, team, or seniority.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 text-primary fw-medium">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            Same code changes = same risk score, regardless of author
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reliability & Safety --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                            <span class="material-symbols-outlined text-success" style="font-size: 24px;">verified</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Reliability & Safety</h6>
                            <span class="text-secondary fs-12">Dependable under all conditions</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Multi-agent consensus with graceful degradation. If any agent fails, the pipeline falls back to mock/cached data rather than crashing or producing silent errors.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 text-success fw-medium">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            Mock fallback pattern ensures 100% uptime for risk decisions
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Privacy & Security --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                            <span class="material-symbols-outlined text-danger" style="font-size: 24px;">lock</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Privacy & Security</h6>
                            <span class="text-secondary fs-12">Data protection first</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Webhook payloads verified via HMAC-SHA256 signatures. API keys stored in Azure Key Vault. No PII logged. All agent communication uses HTTPS with TLS 1.2+.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 text-danger fw-medium">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            HMAC signature verification on every webhook request
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Inclusiveness --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                            <span class="material-symbols-outlined text-info" style="font-size: 24px;">groups</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Inclusiveness</h6>
                            <span class="text-secondary fs-12">Accessible to everyone</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Clear, jargon-free risk explanations accessible to developers at all levels. Visual dashboards with color-coded indicators, not just numbers. PR comments explain the "why" behind every decision.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 text-info fw-medium">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            Plain-language recommendations on every PR analysis
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transparency --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                            <span class="material-symbols-outlined text-warning" style="font-size: 24px;">visibility</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Transparency</h6>
                            <span class="text-secondary fs-12">Explainable decisions</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Every agent decision includes full reasoning chains: contributing risk factors, historical incident correlations, and the specific metrics that drove the score. Nothing is a black box.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 text-warning fw-medium">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            "Why This Risk Score?" section on every PR detail page
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Accountability --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card bg-white border-0 rounded-3 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="wh-50 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="background: rgba(139,92,246,0.1);">
                            <span class="material-symbols-outlined" style="font-size: 24px; color: #8B5CF6;">gavel</span>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Accountability</h6>
                            <span class="text-secondary fs-12">Human oversight always</span>
                        </div>
                    </div>
                    <p class="fs-14 text-secondary mb-3">Human-in-the-loop approval for all high-risk deployments. The Negotiator recommends, but humans make the final call. Every decision is logged with who approved/blocked and when.</p>
                    <div class="p-2 bg-light rounded-2">
                        <span class="fs-12 fw-medium" style="color: #8B5CF6;">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">check</span>
                            Manual approve/block override available on every PR
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Azure AI Content Safety --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">shield</span>
                Azure AI Content Safety Integration
            </h5>
            <p class="text-secondary fs-14 mb-4">All agent outputs pass through Azure AI Content Safety before being posted as PR comments or stored. This prevents harmful, offensive, or inappropriate content from reaching developers.</p>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="p-3 bg-light rounded-3">
                        <h6 class="fw-bold fs-14 mb-2">
                            <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 16px;">input</span>
                            Input Filtering
                        </h6>
                        <ul class="list-unstyled mb-0 fs-13 text-secondary">
                            <li class="mb-1"><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> PR diffs scanned for prompt injection attempts</li>
                            <li class="mb-1"><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> Commit messages sanitized before agent processing</li>
                            <li><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> Adversarial content in code comments detected</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="p-3 bg-light rounded-3">
                        <h6 class="fw-bold fs-14 mb-2">
                            <span class="material-symbols-outlined align-middle me-1 text-success" style="font-size: 16px;">output</span>
                            Output Filtering
                        </h6>
                        <ul class="list-unstyled mb-0 fs-13 text-secondary">
                            <li class="mb-1"><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> Agent PR comments screened before posting</li>
                            <li class="mb-1"><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> Risk recommendations checked for harmful language</li>
                            <li><span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">arrow_right</span> Notification messages validated for appropriateness</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center p-3 rounded-3 mt-2" style="background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 100%);">
                <span class="material-symbols-outlined text-primary me-3" style="font-size: 32px;">security</span>
                <div>
                    <span class="fw-bold fs-14">Content Safety Pipeline</span>
                    <div class="text-secondary fs-12">PR Diff → <strong>Content Safety Scan</strong> → Agent Processing → <strong>Output Safety Check</strong> → PR Comment</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Security Practices --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">security</span>
                Security Practices
            </h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="material-symbols-outlined text-danger me-2" style="font-size: 20px;">fingerprint</span>
                            <span class="fw-bold fs-14">Webhook Verification</span>
                        </div>
                        <p class="fs-13 text-secondary mb-0">HMAC-SHA256 signature validation on every incoming GitHub webhook. Requests without valid signatures are rejected with 401.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="material-symbols-outlined text-warning me-2" style="font-size: 20px;">key</span>
                            <span class="fw-bold fs-14">Secrets Management</span>
                        </div>
                        <p class="fs-13 text-secondary mb-0">Azure Key Vault for API keys, function keys, and connection strings. Environment variables never committed to source control. Keys rotated on schedule.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <span class="material-symbols-outlined text-info me-2" style="font-size: 20px;">verified_user</span>
                            <span class="fw-bold fs-14">Input Validation</span>
                        </div>
                        <p class="fs-13 text-secondary mb-0">All user inputs validated via Laravel Form Requests. PR URLs regex-validated. Agent payloads type-checked. SQL injection prevented via Eloquent ORM.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Audit Trail --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">history</span>
                Decision Audit Trail
            </h5>
            <p class="text-secondary fs-13 mb-3">All agent deployment decisions are logged with full context for accountability and review.</p>
            @if($recentDecisions->count() > 0)
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th class="fw-medium text-secondary">PR</th>
                                <th class="fw-medium text-secondary">Decision</th>
                                <th class="fw-medium text-secondary">Decided By</th>
                                <th class="fw-medium text-secondary">Notification</th>
                                <th class="fw-medium text-secondary">Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentDecisions as $decision)
                                <tr>
                                    <td>
                                        @if($decision->pullRequest)
                                            <span class="fw-bold text-primary">#{{ $decision->pullRequest->pr_number }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $decision->decision_color }} bg-opacity-10 text-{{ $decision->decision_color }} px-3 py-2 text-capitalize">
                                            {{ str_replace('_', ' ', $decision->decision) }}
                                        </span>
                                    </td>
                                    <td class="text-secondary fs-13">{{ $decision->decided_by ?: 'AI Agent' }}</td>
                                    <td class="text-secondary fs-13">{{ Str::limit($decision->notification_message, 60) ?: '—' }}</td>
                                    <td class="text-secondary fs-13">{{ $decision->decided_at ? $decision->decided_at->format('M j, g:i A') : ($decision->created_at ? $decision->created_at->format('M j, g:i A') : '—') }}</td>
                                    <td>
                                        @if($decision->pullRequest)
                                            <a href="{{ route('driftwatch.show', $decision->pullRequest) }}" class="btn btn-sm btn-outline-primary py-1 px-2">
                                                <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-secondary">
                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">gavel</span>
                    <p class="mb-0">No deployment decisions recorded yet.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Data Governance --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">
                <span class="material-symbols-outlined align-middle me-1">database</span>
                Data Governance
            </h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <h6 class="fw-bold fs-14 mb-2 text-primary">Data Collected</h6>
                        <ul class="list-unstyled fs-13 text-secondary mb-0">
                            <li class="mb-1">PR metadata (title, author, branch, files)</li>
                            <li class="mb-1">Code diff content (for analysis only)</li>
                            <li class="mb-1">Agent analysis results & scores</li>
                            <li>Deployment decisions & outcomes</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <h6 class="fw-bold fs-14 mb-2 text-warning">Data Processing</h6>
                        <ul class="list-unstyled fs-13 text-secondary mb-0">
                            <li class="mb-1">Processed via Azure OpenAI (GPT-4.1-mini)</li>
                            <li class="mb-1">No data used for model training</li>
                            <li class="mb-1">Encrypted in transit (TLS 1.2+)</li>
                            <li>Processed in Azure region (configurable)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-light rounded-3 h-100">
                        <h6 class="fw-bold fs-14 mb-2 text-success">Data Retention</h6>
                        <ul class="list-unstyled fs-13 text-secondary mb-0">
                            <li class="mb-1">PR analysis stored in Azure MySQL</li>
                            <li class="mb-1">90-day incident history for correlation</li>
                            <li class="mb-1">Audit logs retained per org policy</li>
                            <li>No raw diffs stored after analysis</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
