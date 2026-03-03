{{-- resources/views/driftwatch/incidents.blade.php --}}
{{-- Historical incidents list used by the Historian agent --}}
@extends('layouts.app')

@section('title', 'Incidents')
@section('heading', 'Historical Incidents')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">
        <span class="fw-medium">Incidents</span>
    </li>
@endsection

@section('content')
    <div class="card bg-white border-0 rounded-3 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0 fw-bold">Incident History (Last 90 Days)</h4>
                <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2">
                    {{ $incidents->total() }} incidents
                </span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th class="fw-medium text-secondary">ID</th>
                            <th class="fw-medium text-secondary">Incident</th>
                            <th class="fw-medium text-secondary">Severity</th>
                            <th class="fw-medium text-secondary">Affected Services</th>
                            <th class="fw-medium text-secondary">Duration</th>
                            <th class="fw-medium text-secondary">Engineers</th>
                            <th class="fw-medium text-secondary">Occurred</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incidents as $incident)
                            <tr>
                                <td><code class="fw-bold">{{ $incident->incident_id }}</code></td>
                                <td>
                                    <span class="fw-medium">{{ Str::limit($incident->title, 50) }}</span>
                                    <br>
                                    <small class="text-secondary">{{ Str::limit($incident->description, 80) }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $incident->severity_color }} bg-opacity-10 text-{{ $incident->severity_color }} px-3 py-2">
                                        {{ $incident->severity_label }}
                                    </span>
                                </td>
                                <td>
                                    @foreach(array_slice($incident->affected_services ?? [], 0, 3) as $service)
                                        <span class="badge bg-primary bg-opacity-10 text-primary me-1">{{ $service }}</span>
                                    @endforeach
                                    @if(count($incident->affected_services ?? []) > 3)
                                        <span class="text-secondary fs-14">+{{ count($incident->affected_services) - 3 }} more</span>
                                    @endif
                                </td>
                                <td class="text-secondary">
                                    @if($incident->duration_minutes)
                                        @if($incident->duration_minutes >= 60)
                                            {{ floor($incident->duration_minutes / 60) }}h {{ $incident->duration_minutes % 60 }}m
                                        @else
                                            {{ $incident->duration_minutes }}m
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $incident->engineers_paged ?? '—' }}</td>
                                <td class="text-secondary fs-14">{{ $incident->occurred_at->format('M j, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <span class="material-symbols-outlined d-block mb-2" style="font-size: 48px;">check_circle</span>
                                    No incidents recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $incidents->links() }}
        </div>
    </div>
@endsection
