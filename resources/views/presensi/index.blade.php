@extends('layouts.app')

@use('App\Models\Presensi')
@use('App\Models\Rapat')
@use('App\Support\Demo')

@php
    $rapat = Rapat::query()->latest('id')->first();
    $presensi = $rapat === null ? collect() : Presensi::query()
        ->whereHas('peserta', fn ($q) => $q->where('rapat_id', $rapat->id))
        ->with(['peserta.anggota', 'pencatat'])
        ->latest('datang_at')
        ->get();
@endphp

@section('title', 'Presensi & Bukti')
@section('pretitle', 'Kas & Transport')

@section('content')
    <div class="alert alert-info">
        <i class="ti ti-lock me-1"></i>
        Foto dan tanda tangan hanya dapat dibuka Admin dan Keuangan. Berkas disimpan di penyimpanan privat,
        tidak pernah menjadi tautan publik, dan tidak dihapus otomatis.
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Presensi {{ $rapat?->judul ?? '—' }}</h3>
            <div class="card-actions text-secondary small">{{ $presensi->count() }} peserta tercatat</div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr>
                    <th>Waktu datang</th>
                    <th>Waktu pulang</th>
                    <th>Nama anggota</th>
                    <th>NBA</th>
                    <th>Nominal</th>
                    <th>Dicatat oleh</th>
                    <th class="text-end">Bukti</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($presensi as $row)
                    <tr>
                        <td class="text-secondary">{{ $row->datang_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-secondary">{{ $row->pulang_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="fw-bold">{{ $row->peserta?->anggota?->nama ?? '—' }}</td>
                        <td class="font-monospace">{{ $row->peserta?->anggota?->nbaTampil() ?? '—' }}</td>
                        <td>{{ Demo::rupiah((float) $row->nominal_transport_final) }}</td>
                        <td class="text-secondary">{{ $row->pencatat?->name ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('presensi.bukti', [$row, 'foto']) }}" target="_blank" rel="noopener"
                               class="btn btn-sm btn-outline-primary">Foto</a>
                            <a href="{{ route('presensi.bukti', [$row, 'tte']) }}" target="_blank" rel="noopener"
                               class="btn btn-sm btn-outline-primary">Tanda tangan</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-4">
                            Belum ada presensi tercatat. Bukti muncul setelah Scanner memindai peserta.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
