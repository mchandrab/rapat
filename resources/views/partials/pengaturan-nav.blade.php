<nav class="card mb-3" aria-label="Navigasi pengaturan">
    <div class="card-body p-2">
        <div class="nav-overflow">
            <ul class="nav nav-pills flex-nowrap erat-settings-nav">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pengguna.*') ? 'active' : '' }}"
                       href="{{ route('pengguna.index') }}">
                        <i class="ti ti-user-cog me-2" aria-hidden="true"></i>Pengguna
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('integrasi.*') ? 'active' : '' }}"
                       href="{{ route('integrasi.index') }}">
                        <i class="ti ti-plug me-2" aria-hidden="true"></i>Integrasi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('pengaturan.aplikasi') ? 'active' : '' }}"
                       href="{{ route('pengaturan.aplikasi') }}">
                        <i class="ti ti-adjustments me-2" aria-hidden="true"></i>Aplikasi
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
