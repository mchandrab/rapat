@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')
@section('pretitle', 'Pengaturan')

@section('content')
    @include('partials.pengaturan-nav')

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="alert alert-danger" role="alert">
        <div class="d-flex">
            <i class="ti ti-alert-triangle me-2"></i>
            <div>
                <div class="fw-bold">Reset total tidak dapat dibatalkan.</div>
                Seluruh rapat, peserta, presensi, kas, pembayaran transport, SHU, master anggota, log audit,
                dan berkas foto/tanda tangan akan dihapus. Akun Admin dibuat ulang dengan sandi
                <span class="font-monospace">password</span> dengan username <span class="font-monospace">admin</span>;
                akun petugas lain dihapus dan dibuat lagi
                lewat menu <strong>Pengguna</strong>.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Data yang akan dihapus</h3>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr><th>Data</th><th class="text-end">Jumlah sekarang</th></tr>
                </thead>
                <tbody>
                @foreach ($jumlah as $label => $nilai)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="text-end">{{ number_format($nilai, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Reset semua data</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengaturan.aplikasi.reset') }}" autocomplete="off"
                  onsubmit="return confirm('Hapus SEMUA data aplikasi sekarang? Tindakan ini tidak dapat dibatalkan.')">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="konfirmasi">
                        Ketik <span class="font-monospace fw-bold">HAPUS SEMUA DATA</span> untuk mengonfirmasi
                    </label>
                    <input id="konfirmasi" name="konfirmasi" class="form-control @error('konfirmasi') is-invalid @enderror"
                           value="" placeholder="HAPUS SEMUA DATA" required>
                    @error('konfirmasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-danger">
                    <i class="ti ti-trash me-1"></i>Hapus semua data
                </button>
                <span class="text-secondary small ms-2">Anda akan keluar dari aplikasi setelah reset.</span>
            </form>
        </div>
    </div>
@endsection
