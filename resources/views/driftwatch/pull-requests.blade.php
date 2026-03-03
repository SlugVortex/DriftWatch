{{-- resources/views/driftwatch/pull-requests.blade.php --}}
{{-- Pull requests list with search and status filtering --}}
@extends('layouts.app')

@section('title', 'Pull Requests')
@section('heading', 'Pull Requests')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Pull Requests</span>
    </li>
@endsection

@section('content')
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            {{-- Filters --}}
            <form method="GET" action="{{ route('driftwatch.pull-requests') }}" class="d-flex gap-3 mb-4 flex-wrap">
                <div class="flex-grow-1">
                    <input type="text" name="search" class="form-control" placeholder="Search by title, author, or repo..."
                           value="{{ request('search') }}">
                </div>
                <select name="status" class="form-select" style="max-width: 200px;">
                    <option value="">All Statuses</option>
                    @foreach(['pending', 'analyzing', 'scored', 'approved', 'blocked', 'deployed', 'failed'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined align-middle">search</span> Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('driftwatch.pull-requests') }}" class="btn btn-outline-secondary">Clear</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="fw-medium text-secondary">PR</th>
                            <th class="fw-medium text-secondary">Title</th>
                            <th class="fw-medium text-secondary">Author</th>
                            <th class="fw-medium text-secondary">Changes</th>
                            <th class="fw-medium text-secondary">Risk</th>
                            <th class="fw-medium text-secondary">Status</th>
                            <th class="fw-medium text-secondary">Decision</th>
                            <th class="fw-medium text-secondary">Date</th>
                            <th class="fw-medium text-secondary">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pullRequests as $pr)
                            <tr>
                                <td><span class="fw-bold text-primary">#{{ $pr->pr_number }}</span></td>
                                <td>
                                    <a href="{{ route('driftwatch.show', $pr) }}" class="text-decoration-none fw-medium">
                                        {{ Str::limit($pr->pr_title, 45) }}
                                    </a>
                                    <br>
                                    <small class="text-secondary">{{ $pr->repo_full_name }}</small>
                                </td>
                                <td class="text-secondary">{{ $pr->pr_author }}</td>
                                <td>
                                    <small>
                                        <span class="text-success">+{{ $pr->additions }}</span> /
                                        <span class="text-danger">-{{ $pr->deletions }}</span>
                                    </small>
                                </td>
                                <td>
                                    @if($pr->riskAssessment)
                                        <span class="badge bg-{{ $pr->risk_color }} bg-opacity-10 text-{{ $pr->risk_color }} fw-bold px-3 py-2">
                                            {{ $pr->riskAssessment->risk_score }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
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
                                <td class="text-secondary fs-14">{{ $pr->created_at->format('M j') }}</td>
                                <td>
                                    <a href="{{ route('driftwatch.show', $pr) }}" class="btn btn-sm btn-outline-primary">
                                        <span class="material-symbols-outlined fs-16">visibility</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-secondary">
                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">inbox</span>
                                    No pull requests found.
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
