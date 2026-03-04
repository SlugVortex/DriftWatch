{{-- resources/views/driftwatch/repositories/show.blade.php --}}
{{-- Single repository view with its PRs --}}
@extends('layouts.app')

@section('title', $repository->full_name)
@section('heading', $repository->full_name)

@section('breadcrumbs')
    <li class="breadcrumb-item">
        <a href="{{ route('driftwatch.repositories') }}" class="text-decoration-none">
            <span class="text-secondary fw-medium hover">Repositories</span>
        </a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">{{ $repository->name }}</span>
    </li>
@endsection

@section('content')
    {{-- Repository Header --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center">
                    <div class="wh-60 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                        <span class="material-symbols-outlined text-primary" style="font-size: 28px;">source</span>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-1">{{ $repository->full_name }}</h4>
                        <div class="d-flex gap-3 flex-wrap">
                            <span class="text-secondary fs-14">
                                <span class="material-symbols-outlined align-middle" style="font-size: 14px;">account_tree</span>
                                {{ $repository->default_branch }}
                            </span>
                            <span class="badge bg-{{ $repository->is_active ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $repository->is_active ? 'success' : 'secondary' }} px-3 py-1">
                                {{ $repository->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            @if($repository->last_synced_at)
                                <span class="text-secondary fs-14">
                                    <span class="material-symbols-outlined align-middle" style="font-size: 14px;">sync</span>
                                    Synced {{ $repository->last_synced_at->diffForHumans() }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <form action="{{ route('driftwatch.repositories.sync', $repository) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined align-middle me-1">sync</span> Sync PRs
                        </button>
                    </form>
                    <a href="{{ $repository->github_url }}" target="_blank" class="btn btn-outline-primary">
                        <span class="material-symbols-outlined align-middle me-1">open_in_new</span> GitHub
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- PRs for this Repository --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Pull Requests</h5>
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
                                <th class="fw-medium text-secondary">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pullRequests as $pr)
                                <tr>
                                    <td><span class="fw-bold text-primary">#{{ $pr->pr_number }}</span></td>
                                    <td>
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="text-decoration-none fw-medium">
                                            {{ Str::limit($pr->pr_title, 50) }}
                                        </a>
                                    </td>
                                    <td class="text-secondary">{{ $pr->pr_author }}</td>
                                    <td>
                                        @if($pr->riskAssessment)
                                            <span class="badge bg-{{ $pr->risk_color }} bg-opacity-10 text-{{ $pr->risk_color }} fw-bold px-3 py-2">
                                                {{ $pr->riskAssessment->risk_score }}/100
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2">Pending</span>
                                        @endif
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
                                        <span class="badge bg-{{ $pr->status_color }} bg-opacity-10 text-{{ $pr->status_color }} px-2 py-1 text-capitalize">
                                            {{ str_replace('_', ' ', $pr->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined fs-16">visibility</span>
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
                    <span class="material-symbols-outlined d-block mb-3 text-secondary" style="font-size: 48px;">merge_type</span>
                    <h6 class="fw-bold mb-2">No PRs found for this repository</h6>
                    <p class="text-secondary mb-3 fs-14">Click "Sync PRs" to pull open PRs from GitHub, or analyze a PR URL from the <a href="{{ route('driftwatch.index') }}">Dashboard</a>.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
