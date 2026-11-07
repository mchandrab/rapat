<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Undangan {{ $peserta->anggota?->nama ?? '—' }} — eRAT</title>
    @if ($publik ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}?v={{ filemtime(public_path('css/erat.css')) }}">
    <style>
        .qr-undangan svg { width: 35mm; height: 35mm; display: block; }
        @media print { .qr-undangan svg { width: 35mm; height: 35mm; } }
    </style>
</head>

<body>
<div class="page page-center">
    <div class="container container-tight py-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="h3 mb-0">Koperasi Kredit Rukun</div>
                    <div class="text-secondary small">Undangan {{ $peserta->rapat?->judul ?? 'Rapat Anggota' }}</div>
                </div>

                <table class="table table-sm table-borderless mb-2 small">
                    <tbody>
                    <tr>
                        <td class="text-secondary py-1" style="width:38%">Nama anggota</td>
                        <td class="fw-bold py-1">{{ $peserta->anggota?->nama ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">NBA</td>
                        <td class="font-monospace py-1">{{ $peserta->anggota?->nbaTampil() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Jenis anggota</td>
                        <td class="py-1">{{ $peserta->anggota?->labelJenis() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Rapat</td>
                        <td class="py-1">{{ $peserta->rapat?->judul ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Hari/Tanggal</td>
                        <td class="py-1">{{ $peserta->rapat?->labelHariTanggal() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Jam</td>
                        <td class="py-1">{{ $peserta->rapat?->labelJamAcara() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Lokasi</td>
                        <td class="py-1">{{ $peserta->rapat?->lokasi ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary py-1">Alamat</td>
                        <td class="py-1">{{ $peserta->rapat?->alamat_lokasi ?? '—' }}</td>
                    </tr>
                    @if ($peserta->rapat?->tautan_google_maps)
                        <tr>
                            <td class="text-secondary py-1">Peta</td>
                            <td class="py-1">
                                <a href="{{ $peserta->rapat->tautan_google_maps }}" target="_blank" rel="noopener noreferrer">Klik di sini</a>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>

                <div class="text-center">
                    <div class="qr-undangan d-inline-block">{!! $qr !!}</div>
                    <div class="text-secondary small mt-2">Tunjukkan QR ini di pintu masuk presensi.</div>
                    <div class="text-secondary small text-break">Kode cadangan: <span class="font-monospace">{{ $token }}</span></div>
                    <br>
                </div>

                @if (filled($peserta->rapat?->catatan_undangan))
                    <div class="bg-secondary-lt rounded p-2 mb-3 small">
                        <div class="text-secondary">Catatan:</div>
                        <div>{!! nl2br(e($peserta->rapat->catatan_undangan)) !!}</div>
                    </div>
                @endif
            </div>
        </div>

        @unless ($publik ?? false)
            <div class="d-print-none mt-3 d-flex gap-2">
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="ti ti-printer me-1"></i>Cetak undangan
                </button>
                <a href="{{ url()->previous() }}" class="btn btn-link">Kembali</a>
            </div>
        @endunless
    </div>
</div>
</body>
</html>
