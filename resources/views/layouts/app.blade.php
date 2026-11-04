<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'eRAT') — eRAT Koperasi Kredit Rukun</title>
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}">
</head>
<body>
<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg" aria-label="Navigasi utama">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="navbar-brand navbar-brand-autodark">
                <a href="{{ route('dashboard') }}" class="erat-brand text-reset text-decoration-none d-flex align-items-center gap-2">
                    <span class="erat-brand-mark bg-primary text-white rounded-2 d-inline-flex align-items-center justify-content-center fw-bold">eR</span>
                    <span>
                        <span class="erat-brand-title d-block fw-bold">eRAT</span>
                        <span class="erat-brand-subtitle d-block text-secondary">Koperasi Kredit Rukun</span>
                    </span>
                </a>
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
        <header class="navbar navbar-expand-md d-print-none">
            <div class="container-xl">
                <div class="navbar-nav flex-row order-md-last align-items-center">
                    <div class="nav-item me-2">
                        @include('layouts.partials.theme-toggle')
                    </div>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Menu pengguna">
                            <span class="avatar avatar-sm bg-primary-lt text-primary fw-bold">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <div class="d-none d-xl-block ps-2">
                                <div>{{ auth()->user()->name }}</div>
                                <div class="mt-1 small text-secondary">{{ ucfirst(auth()->user()->role) }}</div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                            <div class="dropdown-header text-secondary">Masuk sebagai {{ ucfirst(auth()->user()->role) }}</div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item"><i class="ti ti-logout me-2"></i>Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="text-secondary small d-none d-md-inline">{{ \App\Support\Demo::rapat()['judul'] }}</span>
                </div>
            </div>
        </header>

        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <div class="page-pretitle">@yield('pretitle', ucfirst(auth()->user()->role))</div>
                        <h2 class="page-title">@yield('title', 'Dashboard')</h2>
                    </div>
                    @hasSection('actions')
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">@yield('actions')</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                @yield('content')
            </div>
        </div>

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center">
                    <div class="col-12 text-secondary small">
                        eRAT — Rapat Anggota Tahunan Koperasi Kredit Rukun. Seluruh angka berasal dari database.
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
<script src="{{ asset('vendor/tabler/tabler.min.js') }}"></script>
<script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
