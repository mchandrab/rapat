@extends('layouts.app')

@use('App\Models\LogUndangan')

@php
    // BR-005 tetap menyimpan setiap aksi; halaman ini hanya mengelompokkannya per anggota.
    $log = LogUndangan::query()
        ->with(['peserta.anggota', 'petugas'])
        ->latest('waktu_aksi')
        ->limit(500)
        ->get();

    $grup = $log
        ->groupBy('peserta_id')
        ->map(fn ($baris) => $baris->sortByDesc('waktu_aksi')->values())
        ->sortByDesc(fn ($baris) => $baris->first()->waktu_aksi->getTimestamp());
@endphp

@section('title', 'Riwayat Undangan')
@section('pretitle', 'Pelayanan')

@section('actions')
    <button class="btn btn-outline-primary" disabled title="Ekspor belum tersedia pada shell UI">
        <i class="ti ti-download me-1"></i>Ekspor
    </button>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Log kirim &amp; cetak undangan</h3>
            <span class="badge bg-secondary-lt ms-2">{{ $grup->count() }} anggota · {{ $log->count() }} aksi</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr><th>Anggota</th><th>Aksi</th><th>Metode terakhir</th><th>Status terakhir</th><th>Petugas terakhir</th><th>Waktu terakhir</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($grup as $baris)
                    @php($terakhir = $baris->first())
                    <tr data-bs-toggle="collapse" data-bs-target="#rincian-{{ $loop->index }}" role="button" aria-expanded="false">
                        <td>
                            <div>{{ $terakhir->peserta?->anggota?->nama ?? '—' }}</div>
                            <div class="text-secondary small">{{ $terakhir->peserta?->anggota ? $terakhir->peserta->anggota->nbaTampil() : '—' }}</div>
                        </td>
                        <td><span class="badge bg-secondary-lt">{{ $baris->count() }} aksi</span></td>
                        <td>
                            <span class="badge {{ $terakhir->metode === LogUndangan::METODE_WA ? 'bg-green-lt text-green' : 'bg-azure-lt text-azure' }}">
                                <i class="ti ti-{{ $terakhir->metode === LogUndangan::METODE_WA ? 'brand-whatsapp' : 'printer' }} me-1"></i>{{ $terakhir->labelMetode() }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ match ($terakhir->status) {
                                LogUndangan::DICETAK => 'bg-green-lt text-green',
                                LogUndangan::TERKIRIM => 'bg-green-lt text-green',
                                LogUndangan::DITERIMA_API => 'bg-azure-lt text-azure',
                                LogUndangan::GAGAL => 'bg-red-lt text-red',
                                default => 'bg-yellow-lt text-yellow',
                            } }}">{{ $terakhir->labelStatus() }}</span>
                        </td>
                        <td>{{ $terakhir->petugas?->name ?? 'Sistem' }}</td>
                        <td class="text-nowrap text-secondary">{{ $terakhir->waktu_aksi->format('d/m/Y H:i') }}</td>
                        <td class="text-end text-secondary"><i class="ti ti-chevron-down"></i></td>
                    </tr>
                    <tr class="collapse" id="rincian-{{ $loop->index }}">
                        <td colspan="7" class="bg-secondary-lt">
                            <table class="table table-sm table-vcenter mb-0">
                                <thead>
                                <tr><th>Waktu</th><th>Metode</th><th>Petugas</th><th>Status</th><th>Catatan</th></tr>
                                </thead>
                                <tbody>
                                @foreach ($baris as $row)
                                    <tr>
                                        <td class="text-nowrap">{{ $row->waktu_aksi->format('d/m/Y H:i') }}</td>
                                        <td>{{ $row->labelMetode() }}</td>
                                        <td>{{ $row->petugas?->name ?? 'Sistem' }}</td>
                                        <td>{{ $row->labelStatus() }}</td>
                                        <td class="text-secondary small">{{ $row->detail_error ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada undangan dikirim atau dicetak.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-secondary small">
            Status WhatsApp mengikuti bukti dari penyedia. Permintaan API yang diterima tidak otomatis berarti pesan sudah diterima anggota.
            Setiap kirim dan cetak ulang tetap tercatat; klik baris anggota untuk melihat seluruh aksinya. QR rapat tetap sama.
        </div>
    </div>
@endsection
