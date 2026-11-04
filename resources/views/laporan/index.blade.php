@extends('layouts.app')

@use('App\Support\Demo')

@section('title', 'Laporan')
@section('pretitle', 'Rapat')

@section('actions')
    @if ($rapat !== null)
        <a href="{{ route('laporan.ekspor', ['rapat' => $rapat->id]) }}" class="btn btn-primary">
            <i class="ti ti-download me-1"></i>Ekspor XLSX
        </a>
    @endif
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.index') }}" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="rapat">Rapat</label>
                    <select id="rapat" name="rapat" class="form-select" onchange="this.form.submit()">
                        @forelse ($daftarRapat as $pilihan)
                            <option value="{{ $pilihan->id }}" @selected($rapat?->id === $pilihan->id)>
                                {{ $pilihan->judul }} &mdash; {{ $pilihan->tgl_rapat->format('d/m/Y') }}
                                ({{ $pilihan->labelStatus() }})
                            </option>
                        @empty
                            <option value="">Belum ada rapat</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" type="submit">
                        <i class="ti ti-refresh me-1"></i>Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if ($rapat === null)
        <div class="alert alert-warning mb-0">
            <i class="ti ti-info-circle me-1"></i>Belum ada rapat. Laporan dibuat setelah Admin menyiapkan rapat.
        </div>
    @else
        @if (isset($rekap['kuorum']))
            @php($kuorum = $rekap['kuorum'])
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Kuorum</h3>
                    <div class="card-actions">
                        <span class="badge {{ $kuorum['tercapai'] ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                            {{ $kuorum['tercapai'] ? 'Tercapai' : 'Belum tercapai' }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="text-secondary small">Populasi dasar</div>
                            <div class="h1 mb-0">{{ $kuorum['populasi'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary small">Target kuorum</div>
                            <div class="h1 mb-0">{{ $kuorum['target'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary small">Hadir (biasa)</div>
                            <div class="h1 mb-0">{{ $kuorum['hadir'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-secondary small">Hadir luar biasa</div>
                            <div class="h1 mb-0">{{ $kuorum['hadir_luar_biasa'] }}</div>
                        </div>
                    </div>
                    <div class="text-secondary small mt-3">
                        Persentase hadir {{ $kuorum['persen'] }}% &middot;
                        jam tercapai {{ $kuorum['jam_tercapai']?->format('d/m/Y H:i') ?? '-' }}
                    </div>
                </div>
            </div>
        @endif

        <div class="row row-cards">
            @if (isset($rekap['peserta']))
                @php($peserta = $rekap['peserta'])
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Peserta &amp; verifikasi</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Keterangan</th><th class="text-end">Jumlah</th></tr></thead>
                                <tbody>
                                <tr><td>Total peserta</td><td class="text-end">{{ $peserta['total'] }}</td></tr>
                                <tr><td>Hasil layak</td><td class="text-end">{{ $peserta['layak'] }}</td></tr>
                                <tr><td>Hasil tidak layak</td><td class="text-end">{{ $peserta['tidak_layak'] }}</td></tr>
                                <tr><td>Verified</td><td class="text-end">{{ $peserta['verified'] }}</td></tr>
                                <tr><td>Rejected</td><td class="text-end">{{ $peserta['rejected'] }}</td></tr>
                                <tr><td>Pending</td><td class="text-end">{{ $peserta['pending'] }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (isset($rekap['undangan']))
                @php($undangan = $rekap['undangan'])
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Undangan per petugas</h3>
                            <div class="card-actions text-secondary small">{{ $undangan['total'] }} aksi</div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr><th>Petugas</th><th class="text-end">WhatsApp</th><th class="text-end">Cetak</th><th class="text-end">Total</th></tr>
                                </thead>
                                <tbody>
                                @forelse ($undangan['per_petugas'] as $baris)
                                    <tr>
                                        <td>{{ $baris['petugas'] }}</td>
                                        <td class="text-end">{{ $baris['wa'] }}</td>
                                        <td class="text-end">{{ $baris['cetak'] }}</td>
                                        <td class="text-end fw-bold">{{ $baris['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada undangan tercatat.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (isset($rekap['presensi']))
                @php($presensi = $rekap['presensi'])
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Presensi &amp; transport per petugas</h3>
                            <div class="card-actions text-secondary small">
                                {{ $presensi['total'] }} hadir &middot; {{ Demo::rupiah($presensi['nominal']) }}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr><th>Petugas</th><th class="text-end">Presensi</th><th class="text-end">Transport</th></tr>
                                </thead>
                                <tbody>
                                @forelse ($presensi['per_petugas'] as $baris)
                                    <tr>
                                        <td>{{ $baris['petugas'] }}</td>
                                        <td class="text-end">{{ $baris['total'] }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['nominal']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada presensi tercatat.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (isset($rekap['kas']))
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Kas per Scanner</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Scanner</th>
                                    <th class="text-end">Kas awal</th>
                                    <th class="text-end">Terbayar</th>
                                    <th class="text-end">Sisa seharusnya</th>
                                    <th class="text-end">Sisa fisik</th>
                                    <th class="text-end">Selisih</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($rekap['kas'] as $baris)
                                    <tr>
                                        <td>{{ $baris['scanner'] }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['kas_awal']) }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['terbayar']) }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['sisa']) }}</td>
                                        <td class="text-end">{{ $baris['sisa_fisik'] === null ? '-' : Demo::rupiah($baris['sisa_fisik']) }}</td>
                                        <td class="text-end {{ $baris['selisih'] !== null && abs($baris['selisih']) >= 0.005 ? 'text-danger fw-bold' : '' }}">
                                            {{ $baris['selisih'] === null ? '-' : Demo::rupiah($baris['selisih']) }}
                                        </td>
                                        <td>{{ $baris['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada kas Scanner pada rapat ini.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (isset($rekap['shu']))
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">SHU per tahun buku</h3></div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Tahun</th>
                                    <th class="text-end">Anggota</th>
                                    <th class="text-end">Jasa saham</th>
                                    <th class="text-end">Jasa pinjaman</th>
                                    <th class="text-end">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($rekap['shu'] as $baris)
                                    <tr>
                                        <td>{{ $baris['tahun_buku'] }}</td>
                                        <td class="text-end">{{ $baris['jumlah'] }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['saham']) }}</td>
                                        <td class="text-end">{{ Demo::rupiah($baris['pinjaman']) }}</td>
                                        <td class="text-end fw-bold">{{ Demo::rupiah($baris['total']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada data SHU diimpor.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            @if (isset($rekap['doorprize']))
                @php($doorprize = $rekap['doorprize'])
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Doorprize</h3>
                            <div class="card-actions text-secondary small">
                                {{ $doorprize['menang'] }} dari {{ $doorprize['target'] }} pemenang &middot;
                                {{ $doorprize['gugur'] }} gugur
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Urutan</th><th>Nama</th><th>NBA</th></tr></thead>
                                <tbody>
                                @forelse ($doorprize['pemenang'] as $baris)
                                    <tr>
                                        <td>{{ $baris['urutan'] }}</td>
                                        <td>{{ $baris['nama'] }}</td>
                                        <td class="text-nowrap">{{ $baris['nba'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada pemenang.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
@endsection
