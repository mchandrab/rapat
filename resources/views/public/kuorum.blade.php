@extends('layouts.guest')

@section('title', 'Papan Kuorum')
@section('container-class', 'container-xl')
@section('body-class', 'erat-quorum-page')

@section('content')
    <div class="erat-quorum-heading text-center mb-4">
        <div class="erat-quorum-logo mx-auto mb-3" aria-hidden="true">
            <i class="ti ti-users-group"></i>
        </div>
        <div class="text-uppercase fw-bold text-primary small mb-2">Koperasi Kredit Rukun</div>
        <h1 class="display-4 fw-bold mb-2">Papan Kuorum Publik</h1>
        <p class="text-secondary mb-0">Status kehadiran rapat secara langsung dan transparan.</p>
    </div>

    <livewire:kuorum-ringkas judul="Status Kuorum" :publik="true" />

    <div class="text-center text-secondary small mt-4">
        <i class="ti ti-shield-lock me-1" aria-hidden="true"></i>
        Hanya data agregat yang ditampilkan. Tidak ada data pribadi peserta pada halaman ini.
    </div>
@endsection
