@extends('layouts.app')

@section('title', 'Pengaturan Rapat')
@section('pretitle', 'Manajemen Rapat')

@php
    $terkunci = $rapat->terkunci();
    $warnaStatus = match ($rapat->status) {
        'verifikasi' => 'bg-yellow-lt text-yellow',
        'berlangsung' => 'bg-blue-lt text-blue',
        'selesai' => 'bg-green-lt text-green',
        'dibatalkan' => 'bg-red-lt text-red',
        default => 'bg-secondary-lt text-secondary',
    };
    $tier = old('tier', $rapat->tier->map(fn ($baris) => [
        'menit_mulai' => $baris->menit_mulai,
        'menit_akhir' => $baris->menit_akhir,
        'potongan' => (float) $baris->potongan,
    ])->all());

    if ($tier === []) {
        $tier = [['menit_mulai' => '', 'menit_akhir' => '', 'potongan' => '']];
    }
@endphp

@section('actions')
    <span class="badge {{ $warnaStatus }} align-self-center">{{ $rapat->labelStatus() }}</span>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold">Periksa kembali isian:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="fw-bold">{{ $rapat->judul }}</div>
                    <div class="text-secondary small">
                        Tahun Acuan {{ $rapat->tahun_acuan }} · acuan saldo {{ $rapat->tanggal_acuan_saldo->format('d/m/Y') }}
                        · peserta {{ $rapat->peserta()->count() }} dari {{ \App\Models\Anggota::query()->count() }} anggota
                    </div>
                </div>
                <div class="btn-list">
                    @if (in_array($rapat->status, ['draf', 'verifikasi'], true))
                        <form method="POST" action="{{ route('peserta.siapkan', $rapat) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-users me-1"></i>Siapkan Peserta
                            </button>
                        </form>
                    @endif
                    @if ($rapat->status === 'draf')
                        <form method="POST" action="{{ route('rapat.transisi', $rapat) }}">
                            @csrf
                            <input type="hidden" name="aksi" value="verifikasi">
                            <button type="submit" class="btn btn-sm btn-primary">Mulai Verifikasi</button>
                        </form>
                    @endif

                    @if ($rapat->status === 'verifikasi')
                        <form method="POST" action="{{ route('rapat.transisi', $rapat) }}"
                              onsubmit="return confirm('Mulai rapat sekarang? Parameter rapat akan dikunci.')">
                            @csrf
                            <input type="hidden" name="aksi" value="mulai">
                            <button type="submit" class="btn btn-sm btn-primary">Mulai Rapat</button>
                        </form>
                    @endif

                    @if ($rapat->status === 'berlangsung')
                        <form method="POST" action="{{ route('rapat.transisi', $rapat) }}"
                              onsubmit="return confirm('Selesaikan rapat sekarang?')">
                            @csrf
                            <input type="hidden" name="aksi" value="selesai">
                            <button type="submit" class="btn btn-sm btn-primary">Selesaikan Rapat</button>
                        </form>
                    @endif

                    @if (in_array($rapat->status, ['draf', 'verifikasi'], true))
                        <form method="POST" action="{{ route('rapat.transisi', $rapat) }}"
                              onsubmit="return confirm('Batalkan rapat ini?')">
                            @csrf
                            <input type="hidden" name="aksi" value="batal">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                        </form>
                    @endif

                    @if ($rapat->presensi_dibuka_at === null && $rapat->status === 'verifikasi')
                        <form method="POST" action="{{ route('rapat.presensi', $rapat) }}"
                              onsubmit="return confirm('Buka presensi sekarang? Peserta Verified dapat dipindai Scanner.')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Buka Presensi</button>
                        </form>
                    @elseif ($rapat->presensi_dibuka_at !== null)
                        <span class="badge bg-green-lt text-green align-self-center">
                            Presensi dibuka {{ $rapat->presensi_dibuka_at->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($terkunci)
                <div class="alert alert-warning mt-3 mb-0" role="status">
                    Parameter rapat terkunci sejak {{ $rapat->locked_at?->format('d/m/Y H:i') ?? $rapat->labelStatus() }}.
                    Perubahan hanya dapat dilakukan lewat prosedur koreksi resmi.
                </div>
            @else
                <div class="alert alert-info mt-3 mb-0">
                    Presensi dibuka Admin secara manual setelah sarana siap; status rapat tetap {{ $rapat->labelStatus() }}.
                    Jam mulai acara tetap menjadi acuan keterlambatan transport.
                </div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('rapat.update', $rapat) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <div class="btn-list" role="tablist" aria-label="Bagian pengaturan rapat">
                    <button type="button" class="btn btn-sm btn-primary rat-tab" data-rat-tab="umum">Informasi Umum</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rat-tab" data-rat-tab="kelayakan">Kriteria Kelayakan</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rat-tab" data-rat-tab="presensi">Presensi</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rat-tab" data-rat-tab="transport">Uang Transport</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rat-tab" data-rat-tab="kuorum">Kuorum</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rat-tab" data-rat-tab="doorprize">Doorprize</button>
                </div>
            </div>

            <div class="card-body">
                <section class="rat-panel" data-rat-panel="umum">
                    <h3>Informasi Umum Rapat</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="judul">Nama Rapat</label>
                            <input id="judul" name="judul" class="form-control @error('judul') is-invalid @enderror"
                                   value="{{ old('judul', $rapat->judul) }}" @disabled($terkunci)>
                            @error('judul')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="jenis">Jenis Rapat</label>
                            <select id="jenis" name="jenis" class="form-select" @disabled($terkunci)>
                                <option value="rat" @selected(old('jenis', $rapat->jenis) === 'rat')>RAT</option>
                                <option value="biasa" @selected(old('jenis', $rapat->jenis) === 'biasa')>Rapat Biasa</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tgl_rapat">Tanggal Pelaksanaan</label>
                            <input id="tgl_rapat" name="tgl_rapat" type="date"
                                   class="form-control @error('tgl_rapat') is-invalid @enderror"
                                   value="{{ old('tgl_rapat', $rapat->tgl_rapat->toDateString()) }}" @disabled($terkunci)>
                            @error('tgl_rapat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="lokasi">Lokasi Rapat</label>
                            <input id="lokasi" name="lokasi" class="form-control @error('lokasi') is-invalid @enderror"
                                   value="{{ old('lokasi', $rapat->lokasi) }}" @disabled($terkunci)>
                            @error('lokasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="rat-panel d-none" data-rat-panel="kelayakan">
                    <h3>Kriteria Kelayakan Anggota</h3>
                    <p class="text-secondary">
                        Kelayakan dihitung sistem. Verified tetap keputusan petugas. Status Aktif/Nonaktif bukan kriteria kelayakan, tetapi Nonaktif ditolak saat presensi.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="tahun_acuan">Tahun Acuan</label>
                        <input id="tahun_acuan" name="tahun_acuan" type="number" min="2000" max="2100"
                               class="form-control @error('tahun_acuan') is-invalid @enderror"
                               value="{{ old('tahun_acuan', $rapat->tahun_acuan) }}" @disabled($terkunci)>
                        @error('tahun_acuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">Acuan simpanan wajib: 31 Desember Tahun Acuan. Data SHU tetap memakai Tahun Buku tersendiri.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mode_kelayakan">Mode Penilaian</label>
                        <select id="mode_kelayakan" name="mode_kelayakan" class="form-select" @disabled($terkunci)>
                            <option value="A" @selected(old('mode_kelayakan', $rapat->mode_kelayakan) === 'A')>Mode A — Simpanan Wajib &amp; Kolektibilitas</option>
                            <option value="B" @selected(old('mode_kelayakan', $rapat->mode_kelayakan) === 'B')>Mode B — Simpanan Wajib &amp; Pinjaman Lancar</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="minimal_simpanan_tahunan">Simpanan Wajib Minimum Setahun (Rp)</label>
                            <input id="minimal_simpanan_tahunan" name="minimal_simpanan_tahunan" type="number" min="0"
                                   class="form-control @error('minimal_simpanan_tahunan') is-invalid @enderror"
                                   value="{{ old('minimal_simpanan_tahunan', (float) $rapat->minimal_simpanan_tahunan) }}" @disabled($terkunci)>
                            @error('minimal_simpanan_tahunan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="simpanan_per_bulan">Tarif Simpanan Wajib per Bulan (Rp)</label>
                            <input id="simpanan_per_bulan" name="simpanan_per_bulan" type="number" min="0"
                                   class="form-control @error('simpanan_per_bulan') is-invalid @enderror"
                                   value="{{ old('simpanan_per_bulan', (float) $rapat->simpanan_per_bulan) }}" @disabled($terkunci)>
                            @error('simpanan_per_bulan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-check">
                                <input type="hidden" name="gunakan_proporsional" value="0">
                                <input class="form-check-input" type="checkbox" name="gunakan_proporsional" value="1"
                                       @checked(old('gunakan_proporsional', $rapat->gunakan_proporsional)) @disabled($terkunci)>
                                <span class="form-check-label">Gunakan perhitungan proporsional berdasarkan tanggal jadi anggota (tgl_ja)</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="batas_kolektibilitas">Kolektibilitas Maksimum</label>
                            <select id="batas_kolektibilitas" name="batas_kolektibilitas" class="form-select" @disabled($terkunci)>
                                @foreach (range(1, 5) as $kol)
                                    <option value="{{ $kol }}" @selected((int) old('batas_kolektibilitas', $rapat->batas_kolektibilitas) === $kol)>Kol {{ $kol }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check mt-md-4">
                                <input type="hidden" name="wajib_pinjaman_lancar" value="0">
                                <input class="form-check-input" type="checkbox" name="wajib_pinjaman_lancar" value="1"
                                       @checked(old('wajib_pinjaman_lancar', $rapat->wajib_pinjaman_lancar)) @disabled($terkunci)>
                                <span class="form-check-label">Mode B: wajib pinjaman lancar</span>
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-secondary mt-3 mb-0">
                        Contoh: Tahun Acuan 2026 dan anggota masuk November 2026 menghasilkan kewajiban sampai 31 Desember sebanyak 2 × Rp25.000 = Rp50.000.
                    </div>
                </section>

                <section class="rat-panel d-none" data-rat-panel="presensi">
                    <h3>Pengaturan Presensi</h3>
                    <div class="mb-3">
                        <label class="form-label" for="mode_presensi">Mode Presensi</label>
                        <select id="mode_presensi" name="mode_presensi" class="form-select @error('mode_presensi') is-invalid @enderror" @disabled($terkunci)>
                            <option value="1x" @selected(old('mode_presensi', $rapat->mode_presensi) === '1x')>Mode Presensi 1x (datang, transport dibayar saat itu)</option>
                            <option value="2x" @selected(old('mode_presensi', $rapat->mode_presensi) === '2x')>Mode Presensi 2x (Datang&ndash;Pulang, transport dibayar saat pulang)</option>
                        </select>
                        @error('mode_presensi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="jam_mulai_presensi">Jam Mulai Presensi</label>
                            <input id="jam_mulai_presensi" name="jam_mulai_presensi" type="time"
                                   class="form-control @error('jam_mulai_presensi') is-invalid @enderror"
                                   value="{{ old('jam_mulai_presensi', $rapat->jam_mulai_presensi) }}" @disabled($terkunci)>
                            @error('jam_mulai_presensi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="jam_mulai_rat">Jam Mulai Acara</label>
                            <input id="jam_mulai_rat" name="jam_mulai_rat" type="time"
                                   class="form-control @error('jam_mulai_rat') is-invalid @enderror"
                                   value="{{ old('jam_mulai_rat', $rapat->jam_mulai_rat) }}" @disabled($terkunci)>
                            @error('jam_mulai_rat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="jam_selesai">Jam Selesai (opsional)</label>
                            <input id="jam_selesai" name="jam_selesai" type="time"
                                   class="form-control @error('jam_selesai') is-invalid @enderror"
                                   value="{{ old('jam_selesai', $rapat->jam_selesai) }}" @disabled($terkunci)>
                            @error('jam_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Admin membuka presensi dan memulai rapat secara manual. Mode Presensi 2x (Datang-Pulang) membayar transport saat pulang (D-034).
                    </div>
                </section>

                <section class="rat-panel d-none" data-rat-panel="transport">
                    <h3>Uang Transport</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="transport_dasar">Nominal Dasar (Rp)</label>
                            <input id="transport_dasar" name="transport_dasar" type="number" min="0"
                                   class="form-control @error('transport_dasar') is-invalid @enderror"
                                   value="{{ old('transport_dasar', (float) $rapat->transport_dasar) }}" @disabled($terkunci)>
                            @error('transport_dasar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="toleransi_menit">Toleransi (menit)</label>
                            <input id="toleransi_menit" name="toleransi_menit" type="number" min="0"
                                   class="form-control @error('toleransi_menit') is-invalid @enderror"
                                   value="{{ old('toleransi_menit', $rapat->toleransi_menit) }}" @disabled($terkunci)>
                            @error('toleransi_menit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="batas_peringatan_kas">Peringatan Kas Menipis (Rp)</label>
                            <input id="batas_peringatan_kas" name="batas_peringatan_kas" type="number" min="0"
                                   class="form-control @error('batas_peringatan_kas') is-invalid @enderror"
                                   value="{{ old('batas_peringatan_kas', $rapat->batas_peringatan_kas !== null ? (float) $rapat->batas_peringatan_kas : '') }}"
                                   placeholder="{{ (int) (5 * (float) $rapat->transport_dasar) }}" @disabled($terkunci)>
                            @error('batas_peringatan_kas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Kosongkan untuk memakai 5 × nominal dasar.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="batasHangus">Batas Hangus Setelah Toleransi (menit)</label>
                            <input id="batasHangus" class="form-control" placeholder="Ditentukan tier potongan" disabled>
                            <div class="form-hint">Transport menjadi Rp0 bila potongan tier mencapai nominal dasar (D-022); presensi tetap tercatat tanpa pembayaran.</div>
                        </div>
                    </div>

                    <h4 class="mt-4">Tier Potongan (setelah toleransi)</h4>
                    @error('tier')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead><tr><th>Menit Awal</th><th>Menit Akhir</th><th>Potongan (Rp)</th><th aria-label="Tindakan"></th></tr></thead>
                            <tbody id="tierBody">
                            @foreach ($tier as $i => $baris)
                                <tr>
                                    <td><input class="form-control" type="number" min="1" name="tier[{{ $i }}][menit_mulai]" value="{{ $baris['menit_mulai'] }}" @disabled($terkunci)></td>
                                    <td><input class="form-control" type="number" min="1" name="tier[{{ $i }}][menit_akhir]" value="{{ $baris['menit_akhir'] }}" @disabled($terkunci)></td>
                                    <td><input class="form-control" type="number" min="0" name="tier[{{ $i }}][potongan]" value="{{ $baris['potongan'] }}" @disabled($terkunci)></td>
                                    <td>
                                        @unless ($terkunci)
                                            <button class="btn btn-ghost-danger btn-icon" type="button" data-hapus-tier aria-label="Hapus tier"><i class="ti ti-trash"></i></button>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @unless ($terkunci)
                        <button class="btn btn-sm btn-outline-primary" type="button" id="tambahTier"><i class="ti ti-plus me-1"></i>Tambah Tier</button>
                    @endunless
                    <div class="alert alert-info mt-3 mb-0">
                        Jam mulai 08.00 dengan toleransi 60 menit: datang 09.00 tanpa potongan; 09.01 masuk menit ke-1 setelah toleransi.
                        Tier harus bersambung mulai menit ke-1 (tanpa celah); kosongkan tabel bila tidak ada potongan.
                    </div>
                </section>

                <section class="rat-panel d-none" data-rat-panel="kuorum">
                    <h3>Pengaturan Kuorum</h3>
                    <div class="mb-3">
                        <label class="form-label" for="basis_kuorum">Basis Populasi</label>
                        <select id="basis_kuorum" name="basis_kuorum" class="form-select" @disabled($terkunci)>
                            <option value="semua_biasa" @selected(old('basis_kuorum', $rapat->basis_kuorum) === 'semua_biasa')>Seluruh Anggota Biasa</option>
                            <option value="terverifikasi" @selected(old('basis_kuorum', $rapat->basis_kuorum) === 'terverifikasi')>Anggota Biasa Terverifikasi</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="persen_kuorum">Persentase Kuorum (%)</label>
                            <input id="persen_kuorum" name="persen_kuorum" type="number" min="1" max="100"
                                   class="form-control @error('persen_kuorum') is-invalid @enderror"
                                   value="{{ old('persen_kuorum', $rapat->persen_kuorum) }}" @disabled($terkunci)>
                            @error('persen_kuorum')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="tambahan_kuorum">Tambahan Kuorum</label>
                            <input id="tambahan_kuorum" name="tambahan_kuorum" type="number" min="0"
                                   class="form-control @error('tambahan_kuorum') is-invalid @enderror"
                                   value="{{ old('tambahan_kuorum', $rapat->tambahan_kuorum) }}" @disabled($terkunci)>
                            @error('tambahan_kuorum')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">Rumus: floor(persentase × populasi) + tambahan. Hanya anggota jenis Biasa yang dihitung.</div>
                </section>

                <section class="rat-panel d-none" data-rat-panel="doorprize">
                    <h3>Pengaturan Doorprize</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="jumlah_pemenang_doorprize">Jumlah Pemenang</label>
                            <input id="jumlah_pemenang_doorprize" name="jumlah_pemenang_doorprize" type="number" min="1" max="100"
                                   class="form-control @error('jumlah_pemenang_doorprize') is-invalid @enderror"
                                   value="{{ old('jumlah_pemenang_doorprize', $rapat->jumlah_pemenang_doorprize ?? 10) }}" @disabled($terkunci)>
                            @error('jumlah_pemenang_doorprize')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Undian hanya memakai peserta yang sudah tercatat hadir. Peserta yang menang atau dinyatakan sudah pulang
                        tidak diundi lagi pada rapat ini.
                    </div>
                </section>

                <div class="border-top mt-4 pt-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <small class="text-secondary">
                        @if ($terkunci)
                            Parameter dikunci karena rapat sudah {{ $rapat->labelStatus() }}.
                        @else
                            Parameter dikunci otomatis saat RAT Berlangsung.
                        @endif
                    </small>
                    <button class="btn btn-primary" type="submit" @disabled($terkunci)><i class="ti ti-device-floppy me-1"></i>Simpan Pengaturan</button>
                </div>
            </div>
        </div>
    </form>

    <template id="tierBaru">
        <tr>
            <td><input class="form-control" type="number" min="1" name="tier[__i__][menit_mulai]"></td>
            <td><input class="form-control" type="number" min="1" name="tier[__i__][menit_akhir]"></td>
            <td><input class="form-control" type="number" min="0" name="tier[__i__][potongan]"></td>
            <td><button class="btn btn-ghost-danger btn-icon" type="button" data-hapus-tier aria-label="Hapus tier"><i class="ti ti-trash"></i></button></td>
        </tr>
    </template>

    <script>
        document.querySelectorAll('[data-rat-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                const target = button.dataset.ratTab;

                document.querySelectorAll('[data-rat-panel]').forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.dataset.ratPanel !== target);
                });

                document.querySelectorAll('[data-rat-tab]').forEach(function (tab) {
                    const active = tab.dataset.ratTab === target;
                    tab.classList.toggle('btn-primary', active);
                    tab.classList.toggle('btn-outline-secondary', !active);
                });
            });
        });

        (function () {
            const body = document.getElementById('tierBody');
            const tombol = document.getElementById('tambahTier');

            if (! body) {
                return;
            }

            let urutan = body.children.length;

            tombol?.addEventListener('click', function () {
                const baris = document.getElementById('tierBaru').innerHTML.replaceAll('__i__', urutan++);
                body.insertAdjacentHTML('beforeend', baris);
            });

            body.addEventListener('click', function (event) {
                const hapus = event.target.closest('[data-hapus-tier]');

                if (hapus) {
                    hapus.closest('tr').remove();
                }
            });
        })();
    </script>
@endsection
