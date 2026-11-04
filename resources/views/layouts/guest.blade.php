<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Masuk') — eRAT</title>
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}">
</head>

<body class="d-flex flex-column @yield('body-class')">
<div class="position-fixed top-0 end-0 p-3 z-3">
    @include('layouts.partials.theme-toggle')
</div>
<div class="page page-center">
    <div class="container @yield('container-class', 'container-tight') py-4">
        @yield('content')
        <div class="text-center text-secondary mt-3 small">eRAT — Koperasi Kredit Rukun</div>
    </div>
</div>
<script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
