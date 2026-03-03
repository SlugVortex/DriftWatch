{{-- resources/views/layouts/app.blade.php --}}
{{-- Main DriftWatch layout - wraps all authenticated pages with sidebar, header, footer --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') — DriftWatch</title>
        @include('partials.styles')
        @stack('styles')
    </head>
    <body class="boxed-size">
        @include('partials.preloader')
        @include('partials.sidebar')

        <div class="container-fluid">
            <div class="main-content d-flex flex-column">
                @include('partials.header')

                <div class="main-content-container overflow-hidden">
                    {{-- Breadcrumb --}}
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                        <h3 class="mb-0">@yield('heading', 'Dashboard')</h3>

                        <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                            <ol class="breadcrumb align-items-center mb-0 lh-1">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('driftwatch.index') }}" class="d-flex align-items-center text-decoration-none">
                                        <i class="ri-home-4-line fs-18 text-primary me-1"></i>
                                        <span class="text-secondary fw-medium hover">DriftWatch</span>
                                    </a>
                                </li>
                                @yield('breadcrumbs')
                            </ol>
                        </nav>
                    </div>

                    {{-- Flash Messages --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">check_circle</span>
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">error</span>
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                            <span class="material-symbols-outlined align-middle me-1" style="font-size: 18px;">warning</span>
                            {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    {{-- Page Content --}}
                    @yield('content')
                </div>

                <div class="flex-grow-1"></div>

                @include('partials.footer')
            </div>
        </div>

        @include('partials.theme_settings')
        @include('partials.scripts')
        @stack('scripts')
    </body>
</html>
