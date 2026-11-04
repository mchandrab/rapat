@extends('layouts.app')

@use('App\Support\Demo')
@use('App\Support\Nba')

@php
    $log = \App\Models\LogUndangan::query()
        ->with(['peserta.anggota', 'petugas'])
        ->latest('waktu_aksi')
        ->limit(100)
        ->get();
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
            <span class="badge bg-secondary-lt ms-2">{{ $log->count() }} catatan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr><th>Waktu</th><th>Anggota</th><th>Metode</th><th>Petugas</th><th>Status</th></tr>
                </thead>
                <tbody>
                @forelse ($log as $row)
                    <tr>
                        <td class="text-nowrap text-secondary">{{ $row->waktu_aksi->format('d/m/Y H:i') }}</td>
                        <td>
                            <div>{{ $row->peserta?->anggota?->nama ?? '—' }}</div>
                            <div class="text-secondary small">{{ $row->peserta?->anggota ? $row->peserta->anggota->nbaTampil() : '—' }}</div>
                        </td>
                        <td>
                            <span class="badge {{ $row->metode === \App\Models\LogUndangan::METODE_WA ? 'bg-green-lt text-green' : 'bg-azure-lt text-azure' }}">
                                <i class="ti ti-{{ $row->metode === \App\Models\LogUndangan::METODE_WA ? 'brand-whatsapp' : 'printer' }} me-1"></i>{{ $row->labelMetode() }}
                            </span>
                        </td>
                        <td>{{ $row->petugas?->name ?? 'Sistem' }}</td>
                        <td>
                            <span class="badge {{ match ($row->status) {
                                \App\Models\LogUndangan::DICETAK => 'bg-green-lt text-green',
                                \App\Models\LogUndangan::TERKIRIM => 'bg-green-lt text-green',
                                \App\Models\LogUndangan::DITERIMA_API => 'bg-azure-lt text-azure',
                                \App\Models\LogUndangan::GAGAL => 'bg-red-lt text-red',
                                default => 'bg-yellow-lt text-yellow',
                            } }}">{{ $row->labelStatus() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada undangan dikirim atau dicetak.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer text-secondary small">
            Status WhatsApp mengikuti bukti dari penyedia. Permintaan API yang diterima tidak otomatis berarti pesan sudah diterima anggota.
            Cetak ulang dan kirim ulang tercatat sebagai aksi baru; QR rapat tetap sama.
        </div>
    </div>
@endsection
