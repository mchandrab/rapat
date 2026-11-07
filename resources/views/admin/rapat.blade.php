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

    $modeB = old('mode_kelayakan', $rapat->mode_kelayakan) === 'B';
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
                        Acuan saldo {{ $rapat->tanggal_acuan_saldo->format('d/m/Y') }}
                        · peserta {{ $rapat->peserta()->count() }} dari {{ \App\Models\Anggota::query()->count() }} anggota
                    </div>
                </div>
                <div class="btn-list">
                    @if (in_array($rapat->status, ['draf', 'verifikasi'], true))
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#modalSiapkanPeserta">
                            <i class="ti ti-users me-1"></i>Siapkan Peserta
                        </button>
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

    @if (in_array($rapat->status, ['draf', 'verifikasi'], true))
        <div class="modal fade" id="modalSiapkanPeserta" tabindex="-1" aria-hidden="true"
             data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <form id="formSiapkanPeserta" method="POST" action="{{ route('peserta.siapkan', $rapat) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 id="judulSiapkanPeserta" class="modal-title">Siapkan Peserta Rapat</h2>
                        <button id="tutupSiapkanPeserta" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div id="prosesSiapkanPeserta" class="alert alert-primary d-none" role="status" aria-live="polite">
                            <div class="d-flex align-items-start gap-3">
                                <span class="spinner-border spinner-border-sm mt-1" aria-hidden="true"></span>
                                <div class="flex-fill">
                                    <div class="fw-bold">Peserta sedang disiapkan</div>
                                    <div class="small mt-1">Sistem sedang menghitung kelayakan dan membuat snapshot peserta untuk rapat ini.</div>
                                    <div class="progress progress-sm mt-3" aria-hidden="true">
                                        <div class="progress-bar progress-bar-indeterminate"></div>
                                    </div>
                                    <div class="small mt-2">
                                        Memproses ribuan anggota dapat memerlukan beberapa menit. Jangan tutup halaman atau mengirim ulang proses.
                                        Ringkasan peserta baru, Pending yang dihitung ulang, dan keputusan yang dilewati akan muncul setelah selesai.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="konfirmasiSiapkanPeserta">
                            <div class="d-flex align-items-start gap-3 mb-3">
                                <span class="avatar bg-primary-lt text-primary rounded">
                                    <i class="ti ti-users-plus fs-2" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <div class="fw-bold">Buat daftar peserta dari master anggota</div>
                                    <div class="text-secondary small">
                                        Sistem akan menghitung kelayakan dan menyimpan snapshot berdasarkan pengaturan rapat saat ini.
                                    </div>
                                </div>
                            </div>
                            <div class="datagrid mb-3">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Master anggota</div>
                                    <div class="datagrid-content">{{ number_format(\App\Models\Anggota::query()->count(), 0, ',', '.') }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Sudah menjadi peserta</div>
                                    <div class="datagrid-content">{{ number_format($rapat->peserta()->count(), 0, ',', '.') }}</div>
                                </div>
                            </div>
                            <div class="alert alert-warning mb-0 small">
                                <i class="ti ti-alert-triangle me-1" aria-hidden="true"></i>
                                Pastikan pengaturan rapat sudah disimpan. Peserta Pending akan dihitung ulang; peserta Verified/Rejected dilewati agar keputusan tidak berubah.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="batalSiapkanPeserta" type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button id="tombolSiapkanPeserta" type="submit" class="btn btn-primary">
                            <span class="teks-siap"><i class="ti ti-users-plus me-1" aria-hidden="true"></i>Siapkan Peserta</span>
                            <span class="teks-proses d-none"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Menyiapkan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('rapat.update', $rapat) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header py-2">
                <ul class="nav nav-pills erat-form-tabs" role="tablist" aria-label="Bagian pengaturan rapat">
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link active" id="tab-umum" role="tab"
                                aria-controls="panel-umum" aria-selected="true" data-rat-tab="umum">
                            <i class="ti ti-info-circle me-1" aria-hidden="true"></i>Informasi Umum
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="tab-kelayakan" role="tab"
                                aria-controls="panel-kelayakan" aria-selected="false" data-rat-tab="kelayakan">
                            <i class="ti ti-shield-check me-1" aria-hidden="true"></i>Kriteria Kelayakan
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="tab-presensi" role="tab"
                                aria-controls="panel-presensi" aria-selected="false" data-rat-tab="presensi">
                            <i class="ti ti-qrcode me-1" aria-hidden="true"></i>Presensi
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="tab-transport" role="tab"
                                aria-controls="panel-transport" aria-selected="false" data-rat-tab="transport">
                            <i class="ti ti-cash me-1" aria-hidden="true"></i>Uang Transport
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="tab-kuorum" role="tab"
                                aria-controls="panel-kuorum" aria-selected="false" data-rat-tab="kuorum">
                            <i class="ti ti-users-group me-1" aria-hidden="true"></i>Kuorum
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <section class="rat-panel" id="panel-umum" role="tabpanel" aria-labelledby="tab-umum" data-rat-panel="umum">
                    <header class="erat-section-head">
                        <h2 class="erat-section-title">Informasi Umum Rapat</h2>
                        <p class="text-secondary mb-0">
                            Identitas, jadwal, dan lokasi acara. Jam Mulai Acara menjadi acuan keterlambatan transport.
                        </p>
                    </header>

                    <h3 class="erat-subsection-title mt-0">Identitas Rapat</h3>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="judul">Nama Rapat</label>
                            <input id="judul" name="judul" class="form-control @error('judul') is-invalid @enderror"
                                   value="{{ old('judul', $rapat->judul) }}" @disabled($terkunci)>
                            @error('judul')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="jenis">Jenis Rapat</label>
                            <select id="jenis" name="jenis" class="form-select" @disabled($terkunci)>
                                <option value="rat" @selected(old('jenis', $rapat->jenis) === 'rat')>RAT</option>
                                <option value="biasa" @selected(old('jenis', $rapat->jenis) === 'biasa')>Rapat Biasa</option>
                            </select>
                        </div>
                    </div>

                    <h3 class="erat-subsection-title">Jadwal</h3>
                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label" for="tgl_rapat">Tanggal Pelaksanaan</label>
                            <input id="tgl_rapat" name="tgl_rapat" type="date"
                                   class="form-control @error('tgl_rapat') is-invalid @enderror"
                                   value="{{ old('tgl_rapat', $rapat->tgl_rapat->toDateString()) }}" @disabled($terkunci)>
                            @error('tgl_rapat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label" for="jam_mulai_presensi">Jam Mulai Presensi</label>
                            <input id="jam_mulai_presensi" name="jam_mulai_presensi" type="time"
                                   class="form-control @error('jam_mulai_presensi') is-invalid @enderror"
                                   value="{{ old('jam_mulai_presensi', substr((string) $rapat->jam_mulai_presensi, 0, 5)) }}" @disabled($terkunci)>
                            @error('jam_mulai_presensi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label" for="jam_mulai_rat">Jam Mulai Acara</label>
                            <input id="jam_mulai_rat" name="jam_mulai_rat" type="time"
                                   class="form-control @error('jam_mulai_rat') is-invalid @enderror"
                                   value="{{ old('jam_mulai_rat', substr((string) $rapat->jam_mulai_rat, 0, 5)) }}" @disabled($terkunci)>
                            @error('jam_mulai_rat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label" for="jam_selesai">Jam Selesai (opsional)</label>
                            <input id="jam_selesai" name="jam_selesai" type="time"
                                   class="form-control @error('jam_selesai') is-invalid @enderror"
                                   value="{{ old('jam_selesai', $rapat->jam_selesai ? substr((string) $rapat->jam_selesai, 0, 5) : '') }}" @disabled($terkunci)>
                            @error('jam_selesai')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <h3 class="erat-subsection-title">Lokasi</h3>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="lokasi">Nama Lokasi Rapat</label>
                            <input id="lokasi" name="lokasi" class="form-control @error('lokasi') is-invalid @enderror"
                                   value="{{ old('lokasi', $rapat->lokasi) }}" @disabled($terkunci)>
                            @error('lokasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="tautan_google_maps">Tautan Titik Lokasi Google Maps (opsional)</label>
                            <input id="tautan_google_maps" name="tautan_google_maps" type="url"
                                   class="form-control @error('tautan_google_maps') is-invalid @enderror"
                                   value="{{ old('tautan_google_maps', $rapat->tautan_google_maps) }}"
                                   placeholder="https://maps.app.goo.gl/11FgFNfe5FVhPPmq6" @disabled($terkunci)>
                            @error('tautan_google_maps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Salin tautan lokasi dari Google Maps agar peserta dapat membuka petunjuk arah.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="alamat_lokasi">Alamat Lengkap Lokasi Rapat</label>
                            <textarea id="alamat_lokasi" name="alamat_lokasi" rows="2"
                                      class="form-control @error('alamat_lokasi') is-invalid @enderror"
                                      @disabled($terkunci)>{{ old('alamat_lokasi', $rapat->alamat_lokasi) }}</textarea>
                            @error('alamat_lokasi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="rat-panel d-none" id="panel-kelayakan" role="tabpanel" aria-labelledby="tab-kelayakan" data-rat-panel="kelayakan">
                    <header class="erat-section-head">
                        <h2 class="erat-section-title">Kriteria Kelayakan Anggota</h2>
                        <p class="text-secondary mb-0">
                            Kelayakan dihitung sistem. Verified tetap keputusan petugas. Status Aktif/Nonaktif bukan
                            kriteria kelayakan, tetapi Nonaktif ditolak saat presensi. Mode A menilai kolektibilitas,
                            Mode B menilai status pinjaman lancar.
                        </p>
                    </header>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="tanggal_acuan_saldo">Tanggal Acuan Saldo</label>
                            <input id="tanggal_acuan_saldo" name="tanggal_acuan_saldo" type="date"
                                   class="form-control @error('tanggal_acuan_saldo') is-invalid @enderror"
                                   value="{{ old('tanggal_acuan_saldo', $rapat->tanggal_acuan_saldo->format('Y-m-d')) }}" @disabled($terkunci)>
                            @error('tanggal_acuan_saldo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Tanggal terakhir saldo simpanan wajib yang digunakan.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mode_kelayakan">Mode Penilaian</label>
                            <select id="mode_kelayakan" name="mode_kelayakan" class="form-select" @disabled($terkunci)>
                                <option value="A" @selected(old('mode_kelayakan', $rapat->mode_kelayakan) === 'A')>Mode A — Simpanan Wajib &amp; Kolektibilitas</option>
                                <option value="B" @selected(old('mode_kelayakan', $rapat->mode_kelayakan) === 'B')>Mode B — Simpanan Wajib &amp; Pinjaman Lancar</option>
                            </select>
                        </div>
                    </div>

                    <h3 class="erat-subsection-title">Ambang Simpanan</h3>
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
                    </div>

                    <h3 class="erat-subsection-title">Pinjaman</h3>
                    <div class="row g-3">
                        <div class="col-md-4 {{ $modeB ? 'd-none' : '' }}" id="grup-kolektibilitas">
                            <label class="form-label" for="batas_kolektibilitas">Kolektibilitas Maksimum</label>
                            <select id="batas_kolektibilitas" name="batas_kolektibilitas" class="form-select" @disabled($terkunci)>
                                @foreach (range(1, 5) as $kol)
                                    <option value="{{ $kol }}" @selected((int) old('batas_kolektibilitas', $rapat->batas_kolektibilitas) === $kol)>Kol {{ $kol }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8 {{ $modeB ? '' : 'd-none' }}" id="grup-pinjaman">
                            <label class="form-check form-switch mb-0">
                                <input type="hidden" name="wajib_pinjaman_lancar" value="0">
                                <input id="wajib_pinjaman_lancar" class="form-check-input" type="checkbox" name="wajib_pinjaman_lancar" value="1"
                                       @checked($modeB) @disabled($terkunci)>
                                <span class="form-check-label">Wajib pinjaman lancar</span>
                            </label>
                            <div class="form-hint">Aktif otomatis saat Mode B dipilih, karena Mode B menilai pinjaman lancar sebagai pengganti kolektibilitas.</div>
                        </div>
                    </div>

                    <div class="alert alert-secondary mt-3 mb-0">
                        Contoh: acuan saldo 31 Oktober 2026 dan anggota masuk Oktober 2026 menghasilkan kewajiban 1 × Rp25.000 = Rp25.000.
                    </div>
                </section>

                <section class="rat-panel d-none" id="panel-presensi" role="tabpanel" aria-labelledby="tab-presensi" data-rat-panel="presensi">
                    <header class="erat-section-head">
                        <h2 class="erat-section-title">Pengaturan Presensi</h2>
                        <p class="text-secondary mb-0">
                            Pilih kapan uang transport dibayarkan. Jumlah pemindaian per anggota mengikuti mode ini.
                        </p>
                    </header>

                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label" for="mode_presensi">Mode Presensi</label>
                            <select id="mode_presensi" name="mode_presensi" class="form-select @error('mode_presensi') is-invalid @enderror" @disabled($terkunci)>
                                <option value="1x" @selected(old('mode_presensi', $rapat->mode_presensi) === '1x')>Mode Presensi 1x (datang, transport dibayar saat itu)</option>
                                <option value="2x" @selected(old('mode_presensi', $rapat->mode_presensi) === '2x')>Mode Presensi 2x (Datang&ndash;Pulang, transport dibayar saat pulang)</option>
                            </select>
                            @error('mode_presensi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Jadwal presensi dan acara diatur pada Informasi Umum. Admin membuka presensi dan memulai rapat secara manual. Mode Presensi 2x (Datang-Pulang) membayar transport saat pulang (D-034).
                    </div>
                </section>

                <section class="rat-panel d-none" id="panel-transport" role="tabpanel" aria-labelledby="tab-transport" data-rat-panel="transport">
                    <header class="erat-section-head">
                        <h2 class="erat-section-title">Uang Transport</h2>
                        <p class="text-secondary mb-0">
                            Nominal dibayarkan per anggota hadir, dikurangi potongan keterlambatan setelah toleransi.
                        </p>
                    </header>

                    <h3 class="erat-subsection-title">Nominal &amp; Toleransi</h3>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label" for="transport_dasar">Nominal Dasar (Rp)</label>
                            <input id="transport_dasar" name="transport_dasar" type="number" min="0"
                                   class="form-control @error('transport_dasar') is-invalid @enderror"
                                   value="{{ old('transport_dasar', (float) $rapat->transport_dasar) }}" @disabled($terkunci)>
                            @error('transport_dasar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label" for="toleransi_menit">Toleransi (menit)</label>
                            <input id="toleransi_menit" name="toleransi_menit" type="number" min="0"
                                   class="form-control @error('toleransi_menit') is-invalid @enderror"
                                   value="{{ old('toleransi_menit', $rapat->toleransi_menit) }}" @disabled($terkunci)>
                            @error('toleransi_menit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label class="form-label" for="batas_peringatan_kas">Peringatan Kas Menipis (Rp)</label>
                            <input id="batas_peringatan_kas" name="batas_peringatan_kas" type="number" min="0"
                                   class="form-control @error('batas_peringatan_kas') is-invalid @enderror"
                                   value="{{ old('batas_peringatan_kas', $rapat->batas_peringatan_kas !== null ? (float) $rapat->batas_peringatan_kas : '') }}"
                                   placeholder="{{ (int) (5 * (float) $rapat->transport_dasar) }}" @disabled($terkunci)>
                            @error('batas_peringatan_kas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Kosongkan untuk memakai 5 × nominal dasar.</div>
                        </div>
                    </div>

                    <h3 class="erat-subsection-title">Tier Potongan (setelah toleransi)</h3>
                    @error('tier')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <div class="table-responsive">
                        <table class="table table-vcenter erat-tier-table">
                            <thead>
                                <tr>
                                    <th scope="col">Menit Awal</th>
                                    <th scope="col">Menit Akhir</th>
                                    <th scope="col">Potongan (Rp)</th>
                                    <th scope="col" class="w-1"><span class="visually-hidden">Tindakan</span></th>
                                </tr>
                            </thead>
                            <tbody id="tierBody">
                            @foreach ($tier as $i => $baris)
                                <tr>
                                    <td><input class="form-control" type="number" min="1" name="tier[{{ $i }}][menit_mulai]" value="{{ $baris['menit_mulai'] }}" aria-label="Menit awal tier {{ $i + 1 }}" @disabled($terkunci)></td>
                                    <td><input class="form-control" type="number" min="1" name="tier[{{ $i }}][menit_akhir]" value="{{ $baris['menit_akhir'] }}" aria-label="Menit akhir tier {{ $i + 1 }}" @disabled($terkunci)></td>
                                    <td><input class="form-control" type="number" min="0" name="tier[{{ $i }}][potongan]" value="{{ $baris['potongan'] }}" aria-label="Potongan tier {{ $i + 1 }}" @disabled($terkunci)></td>
                                    <td class="text-end">
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
                        <button class="btn btn-sm btn-outline-primary mt-2" type="button" id="tambahTier"><i class="ti ti-plus me-1"></i>Tambah Tier</button>
                    @endunless
                    <div class="alert alert-info mt-3 mb-0">
                        Jam mulai 08.00 dengan toleransi 60 menit: datang 09.00 tanpa potongan; 09.01 masuk menit ke-1 setelah toleransi.
                        Tier harus bersambung mulai menit ke-1 (tanpa celah); kosongkan tabel bila tidak ada potongan.
                        Kedatangan setelah tier terakhir tetap memakai potongan tier terakhir, jadi penalti tidak kembali ke nol.
                        Bila potongan tier mencapai nominal dasar, transport menjadi Rp0 (hangus) dan presensi tetap tercatat tanpa pembayaran.
                    </div>
                </section>

                <section class="rat-panel d-none" id="panel-kuorum" role="tabpanel" aria-labelledby="tab-kuorum" data-rat-panel="kuorum">
                    <header class="erat-section-head">
                        <h2 class="erat-section-title">Pengaturan Kuorum</h2>
                        <p class="text-secondary mb-0">
                            Kuorum dihitung dari populasi anggota jenis Biasa, bukan dari jumlah peserta terverifikasi.
                        </p>
                    </header>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="form-label" for="basis_kuorum">Basis Populasi</label>
                            <select id="basis_kuorum" name="basis_kuorum" class="form-select" @disabled($terkunci)>
                                <option value="semua_biasa" @selected(old('basis_kuorum', $rapat->basis_kuorum) === 'semua_biasa')>Seluruh Anggota Biasa</option>
                                <option value="terverifikasi" @selected(old('basis_kuorum', $rapat->basis_kuorum) === 'terverifikasi')>Anggota Biasa Terverifikasi</option>
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="persen_kuorum">Persentase Kuorum (%)</label>
                            <input id="persen_kuorum" name="persen_kuorum" type="number" min="1" max="100"
                                   class="form-control @error('persen_kuorum') is-invalid @enderror"
                                   value="{{ old('persen_kuorum', $rapat->persen_kuorum) }}" @disabled($terkunci)>
                            @error('persen_kuorum')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label" for="tambahan_kuorum">Tambahan Kuorum</label>
                            <input id="tambahan_kuorum" name="tambahan_kuorum" type="number" min="0"
                                   class="form-control @error('tambahan_kuorum') is-invalid @enderror"
                                   value="{{ old('tambahan_kuorum', $rapat->tambahan_kuorum) }}" @disabled($terkunci)>
                            @error('tambahan_kuorum')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">Rumus: floor(persentase × populasi) + tambahan. Hanya anggota jenis Biasa yang dihitung.</div>
                </section>
            </div>

            <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <small class="text-secondary">
                    @if ($terkunci)
                        Parameter dikunci karena rapat sudah {{ $rapat->labelStatus() }}.
                    @else
                        Parameter dikunci otomatis saat rapat Berlangsung.
                    @endif
                </small>
                <button class="btn btn-primary" type="submit" @disabled($terkunci)><i class="ti ti-device-floppy me-1"></i>Simpan Pengaturan</button>
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
        (function () {
            const form = document.getElementById('formSiapkanPeserta');

            if (! form) {
                return;
            }

            const modal = document.getElementById('modalSiapkanPeserta');
            const proses = document.getElementById('prosesSiapkanPeserta');
            const konfirmasi = document.getElementById('konfirmasiSiapkanPeserta');
            const judul = document.getElementById('judulSiapkanPeserta');
            const tombol = document.getElementById('tombolSiapkanPeserta');
            const batal = document.getElementById('batalSiapkanPeserta');
            const tutup = document.getElementById('tutupSiapkanPeserta');
            let berjalan = false;

            modal?.addEventListener('hide.bs.modal', function (event) {
                if (berjalan) {
                    event.preventDefault();
                }
            });

            form.addEventListener('submit', function (event) {
                if (berjalan) {
                    event.preventDefault();

                    return;
                }

                berjalan = true;
                form.setAttribute('aria-busy', 'true');
                proses?.classList.remove('d-none');
                konfirmasi?.classList.add('d-none');
                tombol.disabled = true;
                batal.disabled = true;
                tutup.classList.add('d-none');
                judul.textContent = 'Menyiapkan Peserta Rapat';
                tombol.querySelector('.teks-siap')?.classList.add('d-none');
                tombol.querySelector('.teks-proses')?.classList.remove('d-none');
            });
        })();

        (function () {
            const tabs = document.querySelectorAll('[data-rat-tab]');
            const panels = document.querySelectorAll('[data-rat-panel]');

            function tampilkan(target) {
                panels.forEach(function (panel) {
                    panel.classList.toggle('d-none', panel.dataset.ratPanel !== target);
                });

                tabs.forEach(function (tab) {
                    const aktif = tab.dataset.ratTab === target;
                    tab.classList.toggle('active', aktif);
                    tab.setAttribute('aria-selected', aktif ? 'true' : 'false');
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tampilkan(tab.dataset.ratTab);
                });
            });

            // Buka bagian yang berisi isian tidak valid agar pesan galat terlihat tanpa mencari tab.
            const galat = document.querySelector('.rat-panel .is-invalid, .rat-panel .alert-danger');

            if (galat) {
                tampilkan(galat.closest('.rat-panel').dataset.ratPanel);
            }
        })();

        (function () {
            const mode = document.getElementById('mode_kelayakan');
            const kolektibilitas = document.getElementById('grup-kolektibilitas');
            const pinjaman = document.getElementById('grup-pinjaman');
            const wajibPinjaman = document.getElementById('wajib_pinjaman_lancar');

            if (! mode || ! kolektibilitas || ! pinjaman || ! wajibPinjaman) {
                return;
            }

            function perbarui() {
                const modeB = mode.value === 'B';

                kolektibilitas.classList.toggle('d-none', modeB);
                pinjaman.classList.toggle('d-none', ! modeB);
                wajibPinjaman.checked = modeB;
            }

            mode.addEventListener('change', perbarui);
            perbarui();
        })();

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
