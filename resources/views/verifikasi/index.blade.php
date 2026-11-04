@extends('layouts.app')

@section('title', 'Verifikasi Undangan')
@section('pretitle', 'Pelayanan')

@section('content')
    <div class="alert alert-info">
        <i class="ti ti-info-circle me-1"></i>
        Layak/Tidak Layak dihitung sistem dari konfigurasi rapat; <b>Verified</b> adalah keputusan petugas dan menjadi syarat tunggal
        penerbitan QR unik rapat.
    </div>

    <div class="row row-cards">
        <div class="col-lg-8">
            <livewire:peserta-verifikasi />
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Prosedur pelayanan</h3></div>
                <div class="card-body">
                    <ol class="ps-3 mb-0">
                        <li class="mb-2">Cari anggota saat datang ke pelayanan (NBA dengan atau tanpa titik).</li>
                        <li class="mb-2">Tinjau hasil kelayakan otomatis beserta snapshot saldo tahun buku.</li>
                        <li class="mb-2">Jika Tidak Layak tetapi disetujui, isi alasan tertulis.</li>
                        <li class="mb-2">Pilih Verified agar token QR unik rapat terbentuk sekali.</li>
                        <li>Tanyakan kanal undangan: WhatsApp atau cetak, lalu catat hasilnya.</li>
                    </ol>
                </div>
                <div class="card-footer">
                    <a href="{{ route('undangan.index') }}" class="btn btn-outline-primary btn-sm">Riwayat undangan</a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Catatan aturan</h3></div>
                <div class="card-body text-secondary small">
                    Status anggota Nonaktif tidak memengaruhi hasil Layak/Tidak Layak, tetapi memblokir presensi di pintu masuk.
                    Kirim ulang undangan tidak membuat QR baru.
                </div>
            </div>
        </div>
    </div>
@endsection
