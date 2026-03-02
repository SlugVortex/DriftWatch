<div class="sidebar-area" id="sidebar-area">
    <div class="logo position-relative">
        <a href="/" class="d-block text-decoration-none position-relative">
            <img src="/assets/images/logo-icon.png" alt="logo-icon">
            <span class="logo-text fw-bold text-dark">Trezo</span>
        </a>
        <button class="sidebar-burger-menu bg-transparent p-0 border-0 opacity-0 z-n1 position-absolute top-50 end-0 translate-middle-y" id="sidebar-burger-menu">
            <i data-feather="x"></i>
        </button>
    </div>

    <aside id="layout-menu" class="layout-menu menu-vertical menu active" data-simplebar>
        <ul class="menu-inner">

            <!-- MAIN DASHBOARD -->
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">MAIN</span>
            </li>
            <li class="menu-item">
                <a href="/starter" class="menu-link {{ Request::is('starter') || Request::is('/') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">dashboard</span>
                    <span class="title">Dashboard</span>
                </a>
            </li>

            <!-- AZURE AI INTEGRATIONS REFERENCE -->
            <!-- Use this section as a blueprint for adding your new Azure AI features -->
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">AZURE AI SERVICES</span>
            </li>

            <!-- Example 1: Single Link Example -->
            <li class="menu-item">
                <a href="#" class="menu-link">
                    <span class="material-symbols-outlined menu-icon">smart_toy</span>
                    <span class="title">Chatbot (Single Link)</span>
                    <span class="hot tag">AI</span>
                </a>
            </li>

            <!-- Example 2: Dropdown Menu Example -->
            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <span class="material-symbols-outlined menu-icon">psychology</span>
                    <span class="title">Cognitive Services</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Text Analytics
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Computer Vision
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            Speech to Text
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Example 3: Multi-Level Menu Example -->
            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle">
                    <span class="material-symbols-outlined menu-icon">layers</span>
                    <span class="title">Multi-Level Example</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item after-sub-menu">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <span class="title">Level One</span>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="#" class="menu-link">
                                    Level Two Item
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </li>

            <!-- APP PAGES -->
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">APP PAGES</span>
            </li>

            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle {{ Request::is('user-profile') || Request::is('add-user') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">group</span>
                    <span class="title">Users</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="/user-profile" class="menu-link {{ Request::is('user-profile') ? 'active' : '' }}">
                            User Profile
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/add-user" class="menu-link {{ Request::is('add-user') ? 'active' : '' }}">
                            Add User
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle {{ Request::is('login') || Request::is('register') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">lock</span>
                    <span class="title">Authentication</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="/login" class="menu-link {{ Request::is('login') ? 'active' : '' }}">
                            Login
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/register" class="menu-link {{ Request::is('register') ? 'active' : '' }}">
                            Register
                        </a>
                    </li>
                </ul>
            </li>

            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle {{ Request::is('404-error-page') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">chat_error</span>
                    <span class="title">Errors</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="/404-error-page" class="menu-link {{ Request::is('404-error-page') ? 'active' : '' }}">
                            404 Error Page
                        </a>
                    </li>
                </ul>
            </li>

            <!-- OTHERS -->
            <li class="menu-title small text-uppercase">
                <span class="menu-title-text">OTHERS</span>
            </li>

            <li class="menu-item">
                <a href="/profile" class="menu-link {{ Request::is('profile') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">account_circle</span>
                    <span class="title">My Profile</span>
                </a>
            </li>

            <li class="menu-item">
                <a href="javascript:void(0);" class="menu-link menu-toggle {{ Request::is('account-settings') || Request::is('change-password') ? 'active' : '' }}">
                    <span class="material-symbols-outlined menu-icon">settings</span>
                    <span class="title">Settings</span>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="/account-settings" class="menu-link {{ Request::is('account-settings') ? 'active' : '' }}">
                            Account Settings
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/change-password" class="menu-link {{ Request::is('change-password') ? 'active' : '' }}">
                            Change Password
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Placeholder for Logout logic later -->
            <li class="menu-item">
                <a href="#" class="menu-link logout">
                    <span class="material-symbols-outlined menu-icon">logout</span>
                    <span class="title">Logout</span>
                </a>
            </li>

        </ul>
    </aside>
</div>
