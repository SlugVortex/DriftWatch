<div class="sidebar-area" id="sidebar-area">
    <div class="logo position-relative">
        <a href="/" class="d-block text-decoration-none position-relative">
            <span class="material-symbols-outlined me-1" style="font-size: 28px; color: #605DFF;">target</span>
            <span class="logo-text fw-bold text-dark">DriftWatch</span>
        </a>
        <button class="sidebar-burger-menu bg-transparent p-0 border-0 opacity-0 z-n1 position-absolute top-50 end-0 translate-middle-y" id="sidebar-burger-menu">
            <i data-feather="x"></i>
        </button>
    </div>

    <aside id="layout-menu" class="layout-menu menu-vertical menu active" data-simplebar>
        <ul class="menu-inner">

            {{-- DRIFTWATCH CORE --}}
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">DRIFTWATCH</span>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.index') }}" class="menu-link {{ Request::is('driftwatch') || Request::is('/') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">dashboard</span>
                    <span class="title">Dashboard</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.pull-requests') }}" class="menu-link {{ Request::is('driftwatch/pull-requests*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">merge_type</span>
                    <span class="title">Pull Requests</span>
                    <span class="hot tag">AI</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.incidents') }}" class="menu-link {{ Request::is('driftwatch/incidents*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">warning</span>
                    <span class="title">Incidents</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.analytics') }}" class="menu-link {{ Request::is('driftwatch/analytics*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">analytics</span>
                    <span class="title">Analytics</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.repositories') }}" class="menu-link {{ Request::is('driftwatch/repositories*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">source</span>
                    <span class="title">Repositories</span>
                </a>
            </li>

            {{-- AI AGENTS --}}
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">AI AGENTS</span>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.agent-map') }}" class="menu-link {{ Request::is('driftwatch/agent-map') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">hub</span>
                    <span class="title">Agent Map</span>
                    <span class="hot tag">Live</span>
                </a>
            </li>

            <li class="menu-item {{ Request::is('driftwatch/agents/*') ? 'open' : '' }}">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <span class="material-symbols-outlined menu-icon">smart_toy</span>
                    <span class="title">Agent Pipeline</span>
                </a>
                <ul class="menu-sub" {!! Request::is('driftwatch/agents/*') ? 'style="display: block;"' : '' !!}>
                    <li class="menu-item">
                        <a href="{{ route('driftwatch.agents.archaeologist') }}" class="menu-link {{ Request::is('driftwatch/agents/archaeologist') ? 'active' : '' }}">
                            Archaeologist
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="{{ route('driftwatch.agents.historian') }}" class="menu-link {{ Request::is('driftwatch/agents/historian') ? 'active' : '' }}">
                            Historian
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="{{ route('driftwatch.agents.negotiator') }}" class="menu-link {{ Request::is('driftwatch/agents/negotiator') ? 'active' : '' }}">
                            Negotiator
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="{{ route('driftwatch.agents.chronicler') }}" class="menu-link {{ Request::is('driftwatch/agents/chronicler') ? 'active' : '' }}">
                            Chronicler
                        </a>
                    </li>
                </ul>
            </li>

            {{-- GOVERNANCE & SETTINGS --}}
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">GOVERNANCE</span>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.governance') }}" class="menu-link {{ Request::is('driftwatch/governance*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">verified_user</span>
                    <span class="title">Responsible AI</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="{{ route('driftwatch.settings') }}" class="menu-link {{ Request::is('driftwatch/settings*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">settings</span>
                    <span class="title">Configuration</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="#" class="menu-link logout">
                    <span class="material-symbols-outlined menu-icon">logout</span>
                    <span class="title">Logout</span>
                </a>
            </li>

        </ul>
    </aside>
</div>
