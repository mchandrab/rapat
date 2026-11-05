<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'eRAT') — eRAT Koperasi Kredit Rukun</title>
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}?v={{ filemtime(public_path('css/erat.css')) }}">
</head>
<body class="erat-app">
<a class="erat-skip-link" href="#konten-utama">Lewati ke konten utama</a>
<div class="page erat-shell">
    <aside class="navbar navbar-vertical navbar-expand-lg" aria-label="Navigasi utama">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="navbar-brand navbar-brand-autodark erat-sidebar-brand">
                <a href="{{ route('dashboard') }}" class="erat-brand text-reset text-decoration-none d-flex align-items-center gap-2">
                    <span class="erat-brand-mark bg-primary text-white rounded-2 d-inline-flex align-items-center justify-content-center fw-bold">eR</span>
                    <span>
                        <span class="erat-brand-title d-block fw-bold">eRAT</span>
                        <span class="erat-brand-subtitle d-block text-secondary">Koperasi Kredit Rukun</span>
                    </span>
                </a>
            </div>

            <div class="navbar-nav flex-row d-lg-none ms-auto align-items-center erat-mobile-actions">
                <div class="nav-item me-2">
                    @include('layouts.partials.theme-toggle')
                </div>
                @include('layouts.partials.user-menu')
            </div>

            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    @foreach (config('menu.'.auth()->user()->role, []) as $group)
                        <li class="nav-item erat-nav-section mt-3">
                            <span class="nav-link text-secondary small text-uppercase fw-bold">{{ $group['label'] }}</span>
                        </li>
                        @foreach ($group['items'] as $item)
                            @php($itemActive = request()->routeIs(...($item['active'] ?? [$item['route']])))
                            <li class="nav-item {{ $itemActive ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route($item['route']) }}">
                                    <span class="nav-link-icon"><i class="ti ti-{{ $item['icon'] }}"></i></span>
                                    <span class="nav-link-title">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <header class="navbar navbar-expand-md d-none d-lg-flex d-print-none erat-topbar">
            <div class="container-xl">
                <div class="navbar-nav flex-row order-md-last align-items-center">
                    <div class="nav-item me-2">
                        @include('layouts.partials.theme-toggle')
                    </div>
                    @include('layouts.partials.user-menu')
                </div>
                <div class="d-flex align-items-center min-w-0">
                    <span class="erat-meeting-context text-secondary small d-none d-md-flex align-items-center gap-2 text-truncate">
                        <i class="ti ti-calendar-event" aria-hidden="true"></i>{{ \App\Support\Demo::rapat()['judul'] }}
                    </span>
                </div>
            </div>
        </header>

        <div class="page-header d-print-none erat-page-header">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">@yield('pretitle', ucfirst(auth()->user()->role))</div>
                        <h2 class="page-title">@yield('title', 'Dashboard')</h2>
                    </div>
                    @hasSection('actions')
                        <div class="col-12 col-md-auto ms-md-auto d-print-none">
                            <div class="btn-list">@yield('actions')</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <main id="konten-utama" class="page-body" tabindex="-1">
            <div class="container-xl">
                @yield('content')
            </div>
        </main>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center">
                    <div class="col-12 text-secondary small">
                        eRAT — Manajemen rapat Koperasi Kredit Rukun. Seluruh angka berasal dari database.
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
<script src="{{ asset('vendor/tabler/tabler.min.js') }}"></script>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
</body>
</html>
