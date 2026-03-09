{{-- resources/views/driftwatch/repositories/index.blade.php --}}
{{-- Connected repositories list with connect form --}}
@extends('layouts.app')

@section('title', 'Repositories')
@section('heading', 'Repositories')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Repositories</span>
    </li>
@endsection

@section('content')
    {{-- Connect a Repository --}}
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-5 mb-3 mb-lg-0">
                    <h4 class="fw-bold mb-1">
                        <span class="material-symbols-outlined align-middle me-1 text-primary" style="font-size: 28px;">add_circle</span>
                        Connect a Repository
                    </h4>
                    <p class="text-secondary mb-0 fs-14">Paste a GitHub repo URL or owner/repo to connect it. DriftWatch will sync open PRs and track all future analyses.</p>
                </div>
                <div class="col-lg-7">
                    <form action="{{ route('driftwatch.repositories.connect') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        <div class="flex-grow-1">
                            <input type="text" name="repo_input" class="form-control form-control-lg @error('repo_input') is-invalid @enderror"
                                   placeholder="owner/repo or https://github.com/owner/repo"
                                   value="{{ old('repo_input') }}" required>
                            @error('repo_input')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg px-4 d-flex align-items-center gap-2">
                            <span class="material-symbols-outlined">link</span>
                            <span>Connect</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Connected Repositories --}}
    @if($repositories->count() > 0)
        <div class="row">
            @foreach($repositories as $repo)
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card bg-white border-0 rounded-3 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="wh-44 bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
                                        <span class="material-symbols-outlined text-primary">source</span>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $repo->name }}</h6>
                                        <span class="text-secondary fs-12">{{ $repo->owner }}</span>
                                    </div>
                                </div>
                                <span class="badge bg-{{ $repo->is_active ? 'success' : 'secondary' }} bg-opacity-10 text-{{ $repo->is_active ? 'success' : 'secondary' }} px-2 py-1">
                                    {{ $repo->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="d-flex gap-3 mb-3">
                                <div class="text-center flex-fill p-2 bg-light rounded-2">
                                    <span class="d-block fs-20 fw-bold text-primary">{{ $repo->pull_requests_count }}</span>
                                    <span class="fs-11 text-secondary">PRs</span>
                                </div>
                                <div class="text-center flex-fill p-2 bg-light rounded-2">
                                    <span class="d-block fs-12 fw-medium">{{ $repo->default_branch }}</span>
                                    <span class="fs-11 text-secondary">Branch</span>
                                </div>
                            </div>

                            {{-- Auto-Analyze Toggle --}}
                            <div class="d-flex align-items-center justify-content-between mb-3 p-2 rounded-2" style="background: {{ $repo->auto_analyze ? 'rgba(96,93,255,0.06)' : '#f8fafc' }}; border: 1px solid {{ $repo->auto_analyze ? 'rgba(96,93,255,0.2)' : '#e2e8f0' }};">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="material-symbols-outlined {{ $repo->auto_analyze ? 'text-primary' : 'text-secondary' }}" style="font-size: 18px;">auto_fix_high</span>
                                    <div>
                                        <span class="fs-12 fw-medium">Auto-Analyze</span>
                                        <span class="d-block fs-10 text-secondary">Scan new PRs automatically</span>
                                    </div>
                                </div>
                                <form action="{{ route('driftwatch.repositories.toggle-auto-analyze', $repo) }}" method="POST">
                                    @csrf
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" onchange="this.form.submit()" {{ $repo->auto_analyze ? 'checked' : '' }}>
                                    </div>
                                </form>
                            </div>

                            @if($repo->last_synced_at)
                                <p class="fs-12 text-secondary mb-3">
                                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">sync</span>
                                    Last synced {{ $repo->last_synced_at->diffForHumans() }}
                                </p>
                            @else
                                <p class="fs-12 text-secondary mb-3">
                                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 13px;">sync_disabled</span>
                                    Never synced
                                </p>
                            @endif

                            <div class="d-flex gap-2 flex-wrap">
                                <a href="{{ route('driftwatch.repositories.show', $repo) }}" class="btn btn-sm btn-primary flex-fill">
                                    <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">visibility</span> View PRs
                                </a>
                                <form action="{{ route('driftwatch.repositories.sync', $repo) }}" method="POST" class="flex-fill">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">sync</span> Sync
                                    </button>
                                </form>
                                <form action="{{ route('driftwatch.repositories.analyze-all', $repo) }}" method="POST" class="flex-fill">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success w-100" onclick="return confirm('Run DriftWatch analysis on all unanalyzed PRs?')">
                                        <span class="material-symbols-outlined align-middle me-1" style="font-size: 14px;">auto_fix_high</span> Analyze
                                    </button>
                                </form>
                                <form action="{{ route('driftwatch.repositories.disconnect', $repo) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Disconnect this repository?')">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">link_off</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card bg-white border-0 rounded-3">
            <div class="card-body p-5 text-center">
                <span class="material-symbols-outlined d-block mb-3 text-secondary" style="font-size: 64px;">source</span>
                <h5 class="fw-bold mb-2">No repositories connected</h5>
                <p class="text-secondary mb-0">Connect a GitHub repository above to start tracking PRs across your team.</p>
            </div>
        </div>
    @endif
@endsection
