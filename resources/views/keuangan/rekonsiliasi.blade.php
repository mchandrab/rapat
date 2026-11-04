@extends('layouts.app')

@use('App\Models\KasScanner')
@use('App\Models\Rapat')
@use('App\Support\Demo')

@php
    $rapat = Rapat::query()->latest('id')->first();
    $kas = $rapat?->kas()->with(['scanner', 'penutup'])->orderBy('id')->get() ?? collect();
@endphp

@section('title', 'Rekonsiliasi Kas')
@section('pretitle', 'Kas & Transport')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-warning">
        <i class="ti ti-alert-triangle me-1"></i>
        Rekonsiliasi dilakukan per akun Scanner setelah antrean sinkron tuntas. Selisih bukan nol wajib diberi alasan dan diaudit.
    </div>

    @if ($rapat === null)
        <div class="card"><div class="card-body text-secondary">Belum ada rapat. Atur <b>Manajemen RAT</b> terlebih dahulu.</div></div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Penutupan kas per Scanner</h3>
                <div class="card-actions text-secondary small">{{ $rapat->judul }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead>
                    <tr>
                        <th>Scanner</th>
                        <th class="text-end">Kas Awal</th>
                        <th class="text-end">Terbayar</th>
                        <th class="text-end">Sisa Seharusnya</th>
                        <th class="text-end">Sisa Fisik</th>
                        <th class="text-end">Selisih</th>
                        <th>Status</th>
                        <th>Tindakan</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($kas as $row)
                        @php($seharusnya = $row->sisa())
                        <tr>
                            <td>
                                <div>{{ $row->scanner?->name ?? '-' }}</div>
                                <div class="text-secondary small">oleh {{ $row->penyerah?->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->kas_awal) }}</td>
                            <td class="text-end text-nowrap">{{ Demo::rupiah($row->terbayar()) }}</td>
                            <td class="text-end text-nowrap">{{ Demo::rupiah($seharusnya) }}</td>
                            <td class="text-end text-nowrap">
                                {{ $row->sisa_fisik === null ? '-' : Demo::rupiah((float) $row->sisa_fisik) }}
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($row->sisa_fisik === null)
                                    -
                                @else
                                    @php($selisih = round((float) $row->sisa_fisik - $seharusnya, 2))
                                    <span class="{{ abs($selisih) < 0.005 ? 'text-green' : 'text-red' }}">
                                        {{ $selisih < 0 ? '-' : '' }}{{ Demo::rupiah(abs($selisih)) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge @switch($row->status)
                                    @case(KasScanner::AKTIF) bg-green-lt text-green @break
                                    @case(KasScanner::MENUNGGU) bg-yellow-lt text-yellow @break
                                    @default bg-secondary-lt text-secondary
                                @endswitch">
                                    {{ $row->labelStatus() }}
                                </span>
                                @if ($row->status === KasScanner::DITUTUP)
                                    <div class="text-secondary small">
                                        {{ $row->penutup?->name ?? 'Sistem' }} ·
                                        {{ $row->ditutup_pada?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                    @if (filled($row->catatan_selisih))
                                        <div class="text-secondary small fst-italic">{{ $row->catatan_selisih }}</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if ($row->status === KasScanner::DITUTUP)
                                    <span class="text-secondary small">-</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#tutup-{{ $row->id }}">
                                        Tutup kas
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-secondary py-4">Belum ada kas dialokasikan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-secondary small">
                Sisa seharusnya = kas awal − pembayaran transport yang tercatat. Tutup kas hanya setelah uang fisik dihitung
                bersama Scanner; kas yang sudah ditutup tidak dapat diubah lagi.
            </div>
        </div>

        @foreach ($kas->whereIn('status', [KasScanner::AKTIF, KasScanner::MENUNGGU]) as $row)
            <div class="modal modal-blur fade" id="tutup-{{ $row->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form method="POST" action="{{ route('kas.tutup', $row) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Tutup kas {{ $row->scanner?->name }}</h5>
                                <div class="text-secondary small">Sisa seharusnya {{ Demo::rupiah($row->sisa()) }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="sisa-{{ $row->id }}">Sisa uang fisik (hasil hitung)</label>
                                <input id="sisa-{{ $row->id }}" name="sisa_fisik" type="number" min="0" step="1"
                                       class="form-control" value="{{ (int) $row->sisa() }}" required>
                                <div class="form-hint">Masukkan jumlah uang yang benar-benar ada di tangan Scanner.</div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="catatan-{{ $row->id }}">Catatan selisih</label>
                                <textarea id="catatan-{{ $row->id }}" name="catatan" rows="2" maxlength="500"
                                          class="form-control" placeholder="Wajib diisi bila sisa fisik berbeda dari sisa seharusnya"></textarea>
                                <div class="form-hint">Alasan selisih dan nominalnya dicatat di log audit.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tutup kas</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
