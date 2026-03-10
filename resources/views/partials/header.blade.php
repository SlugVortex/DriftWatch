{{-- resources/views/partials/header.blade.php --}}
{{-- DriftWatch top header bar - burger menu, search, quick links, notifications --}}
<header class="header-area bg-white mb-4 rounded-bottom-15" id="header-area">
    <div class="row align-items-center">
        <div class="col-lg-4 col-sm-6">
            <div class="left-header-content">
                <ul class="d-flex align-items-center ps-0 mb-0 list-unstyled justify-content-center justify-content-sm-start">
                    <li>
                        <button class="header-burger-menu bg-transparent p-0 border-0" id="header-burger-menu">
                            <span class="material-symbols-outlined">menu</span>
                        </button>
                    </li>
                    <li>
                        <form class="src-form position-relative" action="{{ route('driftwatch.pull-requests') }}" method="GET">
                            <input type="text" name="search" class="form-control" placeholder="Search PRs..." value="{{ request('search') }}" />
                            <button type="submit" class="src-btn position-absolute top-50 end-0 translate-middle-y bg-transparent p-0 border-0">
                                <span class="material-symbols-outlined">search</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-lg-8 col-sm-6">
            <div class="right-header-content">
                <ul class="d-flex align-items-center justify-content-center justify-content-sm-end ps-0 mb-0 list-unstyled gap-1">

                    {{-- Quick Navigation --}}
                    <li>
                        <div class="dropdown notifications apps">
                            <button class="btn btn-secondary border-0 p-0 position-relative wh-40 rounded-circle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Quick Navigation">
                                <span class="material-symbols-outlined fs-22">apps</span>
                            </button>
                            <div class="dropdown-menu dropdown-lg p-0 border-0 py-4 px-3">
                                <div class="notification-menu d-flex flex-wrap justify-content-between gap-4">
                                    <a href="{{ route('driftwatch.index') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-primary mb-1" style="font-size: 24px;">dashboard</span>
                                        <span>Dashboard</span>
                                    </a>
                                    <a href="{{ route('driftwatch.pull-requests') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-info mb-1" style="font-size: 24px;">merge_type</span>
                                        <span>PRs</span>
                                    </a>
                                    <a href="{{ route('driftwatch.incidents') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-warning mb-1" style="font-size: 24px;">warning</span>
                                        <span>Incidents</span>
                                    </a>
                                    <a href="{{ route('driftwatch.analytics') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-success mb-1" style="font-size: 24px;">analytics</span>
                                        <span>Analytics</span>
                                    </a>
                                    <a href="{{ route('driftwatch.settings') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-secondary mb-1" style="font-size: 24px;">settings</span>
                                        <span>Settings</span>
                                    </a>
                                    <a href="{{ route('driftwatch.agents.archaeologist') }}" class="dropdown-item p-0 text-center">
                                        <span class="material-symbols-outlined text-danger mb-1" style="font-size: 24px;">smart_toy</span>
                                        <span>Agents</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>

                    {{-- Notifications (recent PR activity) --}}
                    <li>
                        <div class="dropdown notifications">
                            <button class="btn btn-secondary border-0 p-0 position-relative wh-40 rounded-circle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                                <span class="material-symbols-outlined fs-22">notifications</span>
                                @php
                                    $pendingCount = \App\Models\DeploymentDecision::where('decision', 'pending_review')->count();
                                @endphp
                                @if($pendingCount > 0)
                                    <div class="notify position-absolute rounded-circle bg-danger" style="width: 8px; height: 8px; top: 0; right: 0;"></div>
                                @endif
                            </button>
                            <div class="dropdown-menu dropdown-lg p-0 border-0">
                                <div class="p-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-0">Notifications</h6>
                                        @if($pendingCount > 0)
                                            <span class="badge bg-danger">{{ $pendingCount }} pending</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-3" style="max-height: 300px; overflow-y: auto;" data-simplebar>
                                    @php
                                        $recentPRs = \App\Models\PullRequest::with('riskAssessment')
                                            ->latest()
                                            ->limit(5)
                                            ->get();
                                    @endphp
                                    @forelse($recentPRs as $pr)
                                        <a href="{{ route('driftwatch.show', $pr) }}" class="dropdown-item d-flex align-items-center gap-3 py-2 px-2 rounded">
                                            <div class="wh-35 rounded-circle bg-{{ $pr->status_color }} bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0">
                                                <span class="material-symbols-outlined text-{{ $pr->status_color }}" style="font-size: 18px;">merge_type</span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="fw-medium d-block fs-14">PR #{{ $pr->pr_number }}</span>
                                                <small class="text-secondary">{{ Str::limit($pr->pr_title, 30) }}</small>
                                            </div>
                                            @if($pr->riskAssessment)
                                                <span class="badge bg-{{ $pr->risk_color }} bg-opacity-10 text-{{ $pr->risk_color }}">
                                                    {{ $pr->riskAssessment->risk_score }}
                                                </span>
                                            @endif
                                        </a>
                                    @empty
                                        <p class="text-center text-secondary py-3 mb-0 fs-14">No recent activity</p>
                                    @endforelse
                                </div>
                                <div class="p-2 border-top text-center">
                                    <a href="{{ route('driftwatch.pull-requests') }}" class="text-decoration-none fs-14">View All PRs</a>
                                </div>
                            </div>
                        </div>
                    </li>

                    {{-- Theme Settings Toggle --}}
                    <li>
                        <button class="btn btn-secondary border-0 p-0 wh-40 rounded-circle d-flex align-items-center justify-content-center" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasScrolling" aria-controls="offcanvasScrolling" title="Customization">
                            <span class="material-symbols-outlined fs-22">palette</span>
                        </button>
                    </li>

                    {{-- User Profile --}}
                    <li>
                        <div class="dropdown admin-profile">
                            <button class="btn btn-secondary border-0 p-0 d-flex align-items-center gap-2 rounded-pill px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
                                @auth
                                    <div class="wh-30 rounded-circle d-flex align-items-center justify-content-center" style="background: {{ auth()->user()->avatar_color ?? '#605DFF' }}; color: #fff; font-size: 11px; font-weight: 700;">
                                        {{ auth()->user()->initials() }}
                                    </div>
                                @else
                                    <div class="wh-30 rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
                                        <span class="material-symbols-outlined text-primary" style="font-size: 18px;">person</span>
                                    </div>
                                @endauth
                            </button>
                            <ul class="dropdown-menu border-0 py-3 px-3" style="min-width: 220px;">
                                <li class="mb-2">
                                    <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                        @auth
                                            <div class="wh-35 rounded-circle d-flex align-items-center justify-content-center" style="background: {{ auth()->user()->avatar_color ?? '#605DFF' }}; color: #fff; font-size: 12px; font-weight: 700;">
                                                {{ auth()->user()->initials() }}
                                            </div>
                                            <div>
                                                <span class="fw-bold d-block fs-14">{{ auth()->user()->name }}</span>
                                                <small class="text-secondary text-capitalize">{{ auth()->user()->role }}</small>
                                            </div>
                                        @else
                                            <div class="wh-35 rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center">
                                                <span class="material-symbols-outlined text-primary" style="font-size: 20px;">person</span>
                                            </div>
                                            <div>
                                                <span class="fw-bold d-block fs-14">Guest</span>
                                                <small class="text-secondary">Not signed in</small>
                                            </div>
                                        @endauth
                                    </div>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 px-2 rounded" href="{{ route('driftwatch.settings') }}">
                                        <span class="material-symbols-outlined fs-18">settings</span>
                                        Settings
                                    </a>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 px-2 rounded text-danger w-100 border-0 bg-transparent">
                                            <span class="material-symbols-outlined fs-18">logout</span>
                                            Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
