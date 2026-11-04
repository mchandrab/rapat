@extends('layouts.app')

@use('App\Models\KasScanner')
@use('App\Models\Rapat')
@use('App\Models\User')
@use('App\Support\Demo')

@php
    $rapat = Rapat::query()->latest('id')->first();
    $kas = $rapat?->kas()->with(['scanner', 'penyerah'])->orderBy('id')->get() ?? collect();
    $scanner = User::query()->where('role', 'scanner')->where('active', true)->orderBy('name')->get();
    $sudah = $kas->pluck('scanner_user_id')->all();
    $bolehAlokasi = auth()->user()->role === 'keuangan';
@endphp

@section('title', 'Kas Scanner')
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
        Kas melekat pada <b>akun user Scanner</b>, bukan meja. Satu alokasi kas awal per Scanner per RAT, tanpa top-up, tambah kas,
        atau transfer antar-Scanner.
    </div>

    <div class="row row-cards">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Alokasi kas awal</h3>
                </div>
                <div class="card-body">
                    @if ($rapat === null)
                        <div class="alert alert-secondary mb-0">Belum ada rapat. Atur <b>Manajemen Rapat</b> terlebih dahulu.</div>
                    @elseif (! $bolehAlokasi)
                        <div class="alert alert-secondary mb-0">
                            <i class="ti ti-eye me-1"></i>Alokasi kas dilakukan role <b>Keuangan</b>. Halaman ini hanya untuk memantau.
                        </div>
                    @else
                        <form method="POST" action="{{ route('kas.store') }}">
                            @csrf
                            <div class="mb-2 text-secondary small">
                                Rapat: {{ $rapat->judul }} · {{ $rapat->labelStatus() }}
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="scanner">Akun Scanner</label>
                                <select id="scanner" name="scanner_user_id" class="form-select" required>
                                    <option value="">Pilih Scanner</option>
                                    @forelse ($scanner as $petugas)
                                        <option value="{{ $petugas->id }}" @disabled(in_array($petugas->id, $sudah, true))>
                                            {{ $petugas->name }}{{ in_array($petugas->id, $sudah, true) ? ' — sudah dialokasikan' : '' }}
                                        </option>
                                    @empty
                                        <option value="" disabled>Belum ada akun Scanner</option>
                                    @endforelse
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="nominal">Kas awal</label>
                                <input id="nominal" name="kas_awal" type="number" min="1" step="1" class="form-control"
                                       value="{{ old('kas_awal') }}" placeholder="Contoh: 1000000" required>
                                <div class="form-hint">Nominal tidak dapat diubah setelah dicatat. Tidak ada top-up atau transfer.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"
                                    @disabled($scanner->isEmpty() || count($sudah) >= $scanner->count())>
                                <i class="ti ti-cash me-1"></i>Serahkan &amp; catat alokasi
                            </button>
                        </form>
                    @endif
                </div>
                <div class="card-footer text-secondary small">
                    Kas berstatus <b>Menunggu</b> belum boleh dipakai memindai sampai Scanner menekan
                    <b>Saya sudah menerima kas</b> di layar Scanner. Penutupan dan rekonsiliasi dilakukan di menu
                    <b>Rekonsiliasi Kas</b>.
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Posisi kas per Scanner</h3>
                    <span class="badge bg-secondary-lt ms-2">{{ $kas->count() }} alokasi</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead>
                        <tr><th>Scanner</th><th class="text-end">Kas Awal</th><th class="text-end">Sisa Tersedia</th><th>Diserahkan</th><th>Status</th><th>Tindakan</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($kas as $row)
                            <tr wire:key="kas-{{ $row->id }}">
                                <td>
                                    <div>{{ $row->scanner?->name ?? '—' }}</div>
                                    <div class="text-secondary small">oleh {{ $row->penyerah?->name ?? 'Sistem' }}</div>
                                </td>
                                <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->kas_awal) }}</td>
                                <td class="text-end text-nowrap fw-bold">{{ Demo::rupiah($row->sisa()) }}</td>
                                <td class="text-nowrap text-secondary">{{ $row->diserahkan_pada?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    <span class="badge @switch($row->status)
                                        @case(KasScanner::AKTIF) bg-green-lt text-green @break
                                        @case(KasScanner::MENUNGGU) bg-yellow-lt text-yellow @break
                                        @default bg-secondary-lt text-secondary
                                    @endswitch">
                                        {{ $row->labelStatus() }}
                                    </span>
                                    @if ($rapat !== null && $row->status === KasScanner::AKTIF && $row->sisa() < $rapat->batasPeringatan())
                                        <span class="badge bg-yellow-lt text-yellow ms-1">Kas menipis</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($bolehAlokasi && in_array($row->status, [KasScanner::AKTIF, KasScanner::MENUNGGU], true))
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#koreksi-{{ $row->id }}">
                                            Koreksi
                                        </button>
                                    @else
                                        <span class="text-secondary small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada kas dialokasikan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-secondary small">
                    Sisa tersedia = kas awal − pembayaran transport yang sudah tercatat. Kas yang sudah ditutup tidak berubah lagi.
                </div>
            </div>
        </div>
    </div>

    {{-- Koreksi diletakkan di luar tabel agar modal Bootstrap tidak terpotong overflow tabel. --}}
    @if ($bolehAlokasi)
        @foreach ($kas->whereIn('status', [KasScanner::AKTIF, KasScanner::MENUNGGU]) as $row)
            <div class="modal modal-blur fade" id="koreksi-{{ $row->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <form method="POST" action="{{ route('kas.koreksi', $row) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title">Koreksi kas awal</h5>
                                <div class="text-secondary small">{{ $row->scanner?->name }} · tercatat {{ Demo::rupiah((float) $row->kas_awal) }}</div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="kas-baru-{{ $row->id }}">Kas awal baru</label>
                                <input id="kas-baru-{{ $row->id }}" name="kas_awal" type="number" min="1" step="1"
                                       class="form-control" value="{{ (int) $row->kas_awal }}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="alasan-{{ $row->id }}">Alasan koreksi</label>
                                <textarea id="alasan-{{ $row->id }}" name="alasan" rows="2" minlength="10" maxlength="500"
                                          class="form-control" required placeholder="Contoh: salah input nominal, seharusnya 1.500.000"></textarea>
                                <div class="form-hint">Nominal sebelum dan sesudah serta alasan ini dicatat di log audit.</div>
                            </div>
                            <div class="text-secondary small">
                                Koreksi hanya mengganti nominal, bukan menambah kas. Setelah kas dipakai membayar, koreksi ditutup.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan koreksi</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
@endsection
