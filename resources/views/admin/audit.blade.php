@extends('layouts.app')

@use('App\Models\LogAudit')
@use('App\Models\LogCekShu')
@use('App\Support\Nba')

@php
    $audit = LogAudit::query()->with('aktor')->latest('id')->limit(100)->get();
    $cekShu = LogCekShu::query()->latest('id')->limit(50)->get();
@endphp

@section('title', 'Audit')
@section('pretitle', 'Pengaturan')

@section('content')
    <div class="row row-cards">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Log audit perubahan</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Waktu</th><th>Aksi</th><th>Aktor</th><th>Entitas</th></tr></thead>
                        <tbody>
                        @forelse ($audit as $row)
                            <tr>
                                <td class="text-nowrap text-secondary">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ ucfirst($row->aksi) }}</td>
                                <td>{{ $row->aktor?->name ?? 'Sistem' }}</td>
                                <td class="text-secondary">{{ $row->entitas }} #{{ $row->entitas_id }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada aktivitas tercatat.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">Log audit bersifat append-only dan tidak ikut dibersihkan oleh purge log pencarian SHU.</div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat pencarian SHU</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="ti ti-clock me-1"></i>Riwayat pencarian SHU tersedia selama <b>14 hari</b>.
                    </div>
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Waktu</th><th>NBA</th><th>Tahun</th><th>Hasil</th></tr></thead>
                        <tbody>
                        @forelse ($cekShu as $row)
                            <tr>
                                <td class="text-nowrap text-secondary">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-nowrap">{{ $row->nba_dicari === null ? '-' : Nba::format($row->nba_dicari) }}</td>
                                <td>{{ $row->tahun_buku ?? '-' }}</td>
                                <td class="text-secondary">{{ str_replace('_', ' ', $row->hasil) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada riwayat pencarian.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    Log tidak menyimpan nominal SHU. Hanya Admin yang dapat membaca riwayat ini; data SHU dan audit perubahan Admin tidak terhapus.
                </div>
            </div>
        </div>
    </div>
@endsection
