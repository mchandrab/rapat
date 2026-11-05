@extends('layouts.app')

@section('title', 'Master Hadiah Doorprize')
@section('pretitle', 'Pengelolaan Admin')

@section('actions')
    <a href="{{ route('doorprize.undian') }}" class="btn btn-primary">
        <i class="ti ti-confetti me-1"></i>Buka Halaman Undian
    </a>
@endsection

@section('content')
    @if ($rapat === null)
        <div class="alert alert-warning mb-0">Belum ada rapat. Siapkan rapat sebelum menambahkan hadiah.</div>
    @else
        <div class="card mb-3">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-2">
                <div>
                    <div class="text-secondary small">Rapat aktif</div>
                    <div class="fw-bold">{{ $rapat->judul }}</div>
                </div>
                <div class="text-md-end">
                    <div class="text-secondary small">Status</div>
                    <span class="badge bg-secondary-lt">{{ $rapat->labelStatus() }}</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Daftar Hadiah</h3></div>
            <div class="card-body">
                <livewire:doorprize-hadiah :rapat="$rapat" />
                <div class="alert alert-info mt-3 mb-0">
                    Jumlah adalah stok awal dan tidak dikurangi. Hadiah bersejarah tidak dapat dihapus, dan jumlah tidak dapat
                    diturunkan di bawah pemenang yang sudah tercatat.
                </div>
            </div>
        </div>
    @endif
@endsection
