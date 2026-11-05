@extends('layouts.app')

@section('title', 'Anggota')
@section('pretitle', 'Pelayanan Anggota')

@section('actions')
    @if (auth()->user()->role === 'admin')
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImpor">
            <i class="ti ti-upload me-1"></i>Impor XLSX
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="ti ti-plus me-1"></i>Tambah Anggota
        </button>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if (session('impor'))
        @php($impor = session('impor'))
        <div class="alert {{ $impor['galat'] === [] ? 'alert-success' : 'alert-warning' }}" role="status">
            <div class="fw-bold">
                Impor selesai: {{ $impor['dibuat'] }} anggota baru, {{ $impor['diperbarui'] }} diperbarui,
                {{ count($impor['galat']) }} baris ditolak.
            </div>
            @if ($impor['galat'] !== [])
                <ul class="mb-0 mt-2">
                    @foreach ($impor['galat'] as $galat)
                        <li>Baris {{ $galat['baris'] }}: {{ $galat['pesan'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold">Periksa kembali isian:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="text-secondary mb-3">
        Master anggota dan status verifikasi undangan dalam satu halaman. NBA disimpan 15 digit dan ditampilkan 6.3.3.3.
        Impor tahun berikutnya memperbarui anggota dengan NBA yang sama tanpa menghapus data lain.
    </div>

    <livewire:peserta-verifikasi />

    <p class="text-secondary small mt-2">
        Kelayakan dan status verifikasi berasal dari peserta rapat aktif. Status keanggotaan Aktif/Nonaktif selalu dibaca dari
        master terbaru dan anggota Nonaktif ditolak saat presensi.
    </p>

    @if (auth()->user()->role === 'admin')
        <div class="modal fade" id="modalImpor" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('anggota.impor') }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title">Impor Anggota (XLSX)</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="berkas">Berkas .xlsx</label>
                            <input id="berkas" name="berkas" type="file" accept=".xlsx" class="form-control" required>
                        </div>
                        <div class="alert alert-info mb-0 small">
                            Kolom <b>no_anggota</b> wajib diformat sebagai Teks 15 digit. Baris yang kehilangan nol awal
                            ditolak dan dilaporkan per baris, tanpa menambal nomor secara otomatis.
                            <a href="{{ route('anggota.template') }}">Unduh template XLSX</a>.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Impor</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ route('anggota.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title">Tambah Anggota</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.partials.anggota-form', ['anggota' => null, 'awalan' => 'baru-'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Anggota</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
