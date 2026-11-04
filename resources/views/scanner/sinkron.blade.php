@extends('layouts.app')

@section('title', 'Status Koneksi')
@section('pretitle', 'Presensi')

@section('content')
    <div class="alert alert-info">
        <i class="ti ti-wifi me-1"></i>
        <b>Aplikasi berjalan online saja (D-032).</b> Saat koneksi putus, pemindaian dihentikan total: tidak ada antrean,
        tidak ada pembayaran tunai, dan tidak ada presensi yang dicatat manual sebagai pengganti.
    </div>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Koneksi perangkat</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-secondary">Status</span>
                        <span id="badgeKoneksi" class="badge bg-green-lt text-green">Tersambung</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-secondary">Antrean offline</span>
                        <b>Tidak dipakai</b>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span class="text-secondary">Data menunggu sinkron</span>
                        <b>0</b>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Yang berlaku saat koneksi hilang</h3></div>
                <div class="card-body">
                    <ul class="ps-3 mb-0 text-secondary">
                        <li class="mb-2">Pemindaian berhenti; tombol <b>Catat Presensi</b> nonaktif dan muncul peringatan merah.</li>
                        <li class="mb-2">Tidak ada uang yang boleh diserahkan dan tidak ada catatan manual sebagai pengganti.</li>
                        <li class="mb-2">Arahkan peserta ke Scanner lain yang online.</li>
                        <li>Setelah koneksi kembali, periksa <b>Kas Saya</b> lalu lanjutkan pemindaian.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        const badge = document.getElementById('badgeKoneksi');

        const perbaruiKoneksi = () => {
            if (! badge) {
                return;
            }

            const putus = ! navigator.onLine;
            badge.textContent = putus ? 'Terputus' : 'Tersambung';
            badge.className = 'badge ' + (putus ? 'bg-red-lt text-red' : 'bg-green-lt text-green');
        };

        window.addEventListener('online', perbaruiKoneksi);
        window.addEventListener('offline', perbaruiKoneksi);
        perbaruiKoneksi();
    </script>
@endsection
