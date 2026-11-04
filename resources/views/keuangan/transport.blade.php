@extends('layouts.app')

@use('App\Models\Rapat')
@use('App\Support\Demo')

@php($rapat = Rapat::query()->latest('id')->first())
@php($tier = $rapat?->tier()->orderBy('urutan')->get() ?? collect())

@section('title', 'Laporan Transport')
@section('pretitle', 'Kas & Transport')

@section('content')
    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tier potongan transport</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Menit setelah toleransi</th><th>Potongan</th><th>Nominal diterima</th></tr></thead>
                        <tbody>
                        @forelse ($tier as $row)
                            <tr>
                                <td>{{ $row->menit_mulai }} – {{ $row->menit_akhir }}</td>
                                <td>{{ Demo::rupiah((float) $row->potongan) }}</td>
                                <td>{{ Demo::rupiah(max((float) $rapat->transport_dasar - (float) $row->potongan, 0)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada tier potongan. Atur pada Manajemen Rapat.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    Acuan keterlambatan adalah jam mulai acara ({{ $rapat?->jam_mulai_rat ?? '—' }}) ditambah toleransi, bukan jam mulai presensi.
                    Transport Rp0 akibat hangus tetap tercatat sebagai presensi tanpa pembayaran (D-022).
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Rekap pembayaran transport</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr><th>Scanner</th><th>Pembayaran</th><th>Total</th></tr></thead>
                        <tbody>
                        <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada pembayaran transport.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-outline-primary" disabled>Ekspor laporan</button>
                </div>
            </div>
        </div>
    </div>
@endsection
