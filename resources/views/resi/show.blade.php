@use('App\Support\Demo')

<!doctype html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resi {{ $bayar->kode_resi }} — eRAT</title>
    @include('layouts.partials.theme-init')
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erat.css') }}?v={{ filemtime(public_path('css/erat.css')) }}">
</head>

<body>
<div class="page">
    <div class="container container-tight py-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <div class="h2 mb-0">Koperasi Kredit Rukun</div>
                        <div class="text-secondary">Bukti Pembayaran Uang Transport</div>
                    </div>
                    <div class="text-end">
                        <div class="text-secondary small">Kode resi</div>
                        <div class="h2 mb-0 font-monospace">{{ $bayar->kode_resi }}</div>
                    </div>
                </div>

                <table class="table table-borderless mb-3">
                    <tbody>
                    <tr>
                        <td class="text-secondary" style="width:40%">Nama anggota</td>
                        <td class="fw-bold">{{ $bayar->peserta?->anggota?->nama ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">NBA</td>
                        <td class="font-monospace">{{ $bayar->peserta?->anggota?->nbaTampil() ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Rapat</td>
                        <td>{{ $bayar->peserta?->rapat?->judul ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Tanggal rapat</td>
                        <td>{{ $bayar->peserta?->rapat?->tgl_rapat?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Waktu dibayar</td>
                        <td>{{ $bayar->dibayar_pada?->format('d/m/Y H:i') ?? '—' }} WIB</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Nominal diterima</td>
                        <td class="h2 mb-0">{{ Demo::rupiah((float) $bayar->nominal) }}</td>
                    </tr>
                    <tr>
                        <td class="text-secondary">Diserahkan oleh</td>
                        <td>{{ $bayar->pembayar?->name ?? '—' }} (Scanner)</td>
                    </tr>
                    </tbody>
                </table>

                <div class="text-secondary small">
                    Nominal dihitung sistem dari jam datang terhadap jam mulai acara dan toleransi; Scanner tidak dapat mengubahnya.
                    Simpan resi ini sebagai tanda terima uang transport.
                </div>
            </div>
        </div>

        <div class="d-print-none mt-3 d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="ti ti-printer me-1"></i>Cetak resi
            </button>
            <a href="{{ url()->previous() }}" class="btn btn-link">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
