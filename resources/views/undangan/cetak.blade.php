@use('App\Support\Demo')

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Undangan {{ $peserta->anggota?->nama ?? '—' }} — eRAT</title>
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}">
    <style>
        .qr-undangan svg { width: 35mm; height: 35mm; display: block; }
        @media print { .qr-undangan svg { width: 35mm; height: 35mm; } }
    </style>
</head>

<body>
<div class="page">
    <div class="container container-tight py-4">
        <div class="card">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h2 mb-0">Koperasi Kredit Rukun</div>
                    <div class="text-secondary">Undangan Rapat Anggota Tahunan</div>
                </div>

                <table class="table table-borderless mb-3">
                    <tbody>
                    <tr>
                        <td class="text-secondary" style="width:40%">Nama anggota</td>
                        <td class="fw-bold">{{ $peserta->anggota?->nama ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">NBA</td>
                        <td class="font-monospace">{{ $peserta->anggota?->nbaTampil() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Rapat</td>
                        <td>{{ $peserta->rapat?->judul ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Tanggal</td>
                        <td>{{ $peserta->rapat?->tgl_rapat?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Presensi dibuka</td>
                        <td>{{ $peserta->rapat?->jam_mulai_presensi ?? '—' }} WIB</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Lokasi</td>
                        <td>{{ $peserta->rapat?->lokasi ?? '—' }}</td>
                    </tr>
                    </tbody>
                </table>

                <div class="text-center">
                    <div class="qr-undangan d-inline-block">{!! $qr !!}</div>
                    <div class="text-secondary small mt-2">Tunjukkan QR ini di pintu masuk presensi.</div>
                    <div class="text-secondary small">Kode cadangan: <span class="font-monospace">{{ $token }}</span></div>
                </div>
            </div>
        </div>

        <div class="d-print-none mt-3 d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="ti ti-printer me-1"></i>Cetak undangan
            </button>
            <a href="{{ url()->previous() }}" class="btn btn-link">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
