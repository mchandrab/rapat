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
