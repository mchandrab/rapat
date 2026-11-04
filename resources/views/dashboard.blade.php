@extends('layouts.app')

@use('App\Support\Demo')
@use('App\Support\Nba')

@php
    $role = auth()->user()->role;
    $rapatModel = \App\Models\Rapat::query()->latest('id')->first();
    $rapat = Demo::rapat();
    $peserta = $rapatModel?->peserta()->with('anggota')->get() ?? collect();
    $undanganGagal = $rapatModel?->id === null ? 0 : \App\Models\LogUndangan::query()
        ->where('rapat_id', $rapatModel->id)
        ->where('status', \App\Models\LogUndangan::GAGAL)
        ->count();
    $kas = $rapatModel?->id === null ? collect() : \App\Models\KasScanner::query()
        ->with('scanner')
        ->where('rapat_id', $rapatModel->id)
        ->orderBy('id')
        ->get();
    $kasSaya = $kas->firstWhere('scanner_user_id', auth()->id());
    $aktivitas = \App\Models\LogAudit::query()->with('aktor')->latest('id')->limit(5)->get();
@endphp

@section('title', 'Dashboard')
@section('pretitle', ucfirst($role))

@section('content')
    @if (in_array($role, ['helpdesk', 'scanner', 'keuangan'], true))
        @include('partials.rapat-ringkas')
    @endif

    @if ($role === 'admin')
        <div class="alert alert-info">
            <i class="ti ti-info-circle me-1"></i>
            <b>{{ $rapat['judul'] }}</b> berada pada tahap <b>{{ $rapat['status'] }}</b>.
            Jam mulai presensi ({{ $rapat['jam_presensi'] }}) diatur terpisah dari jam mulai acara ({{ $rapat['jam_rat'] }}).
        </div>

        <div class="row row-cards mb-3">
            @foreach ([
                ['Data Anggota', (string) \App\Models\Anggota::query()->count(), 'users', 'bg-blue-lt text-blue'],
                ['Peserta Verified', $peserta->where('status_verifikasi', 'verified')->count().' / '.$peserta->count(), 'checks', 'bg-green-lt text-green'],
                ['Menunggu Verifikasi', $peserta->where('status_verifikasi', 'pending')->count(), 'clock', 'bg-yellow-lt text-yellow'],
                ['Kuorum', '—', 'users-group', 'bg-azure-lt text-azure'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-sm-6 col-lg-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="subheader">{{ $label }}</div>
                                <span class="avatar avatar-sm rounded {{ $tone }}"><i class="ti ti-{{ $icon }}"></i></span>
                            </div>
                            <div class="h1 mb-0">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row row-cards">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Perkembangan Verifikasi Undangan</h3></div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Keputusan verifikasi anggota</span>
                            <span class="text-secondary">{{ $peserta->where('status_verifikasi', 'verified')->count() }} dari {{ $peserta->count() }} peserta</span>
                        </div>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-primary" style="width: {{ round($peserta->where('status_verifikasi', 'verified')->count() / max($peserta->count(), 1) * 100) }}%"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="text-secondary small">Verified</div>
                                <div class="h2 mb-0">{{ $peserta->where('status_verifikasi', 'verified')->count() }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary small">Pending</div>
                                <div class="h2 mb-0">{{ $peserta->where('status_verifikasi', 'pending')->count() }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary small">Rejected</div>
                                <div class="h2 mb-0">{{ $peserta->where('status_verifikasi', 'rejected')->count() }}</div>
                            </div>
                        </div>
                        <div class="text-secondary small mt-3">
                            Hanya peserta <b>Verified</b> yang menerima QR unik untuk rapat ini.
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">Verifikasi dibuka H-30 sampai H-7.</span>
                        <a href="{{ route('verifikasi.index') }}" class="btn btn-outline-primary btn-sm">Buka Verifikasi</a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h3 class="card-title">Aktivitas Terakhir</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter mb-0">
                            <tbody>
                                @forelse ($aktivitas as $log)
                                    <tr>
                                        <td class="text-secondary text-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                        <td>{{ ucfirst($log->aksi) }} {{ $log->entitas }}</td>
                                        <td class="text-secondary">{{ $log->aktor?->name ?? 'Sistem' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-secondary">Belum ada aktivitas tercatat.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Informasi Rapat</h3></div>
                    <div class="card-body">
                        <div class="text-secondary small">Tahun Acuan {{ $rapat['tahun_acuan'] }}</div>
                        <h3 class="mb-3">{{ $rapat['judul'] }}</h3>
                        @foreach ([
                            'Tanggal' => $rapat['tgl_rapat'],
                            'Lokasi' => $rapat['lokasi'],
                            'Alamat' => $rapat['alamat_lokasi'] ?: 'Belum diisi',
                            'Mulai presensi' => $rapat['jam_presensi'],
                            'Mulai acara' => $rapat['jam_rat'],
                            'Mode presensi' => $rapat['mode_presensi'],
                            'Transport dasar' => Demo::rupiah($rapat['transport_dasar']),
                            'Toleransi' => $rapat['toleransi_menit'].' menit',
                        ] as $label => $value)
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-secondary">{{ $label }}</span>
                                <b class="text-end">{{ $value }}</b>
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-between py-2">
                            <span class="text-secondary">Status</span>
                            <span class="badge bg-yellow-lt text-yellow">{{ $rapat['status'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($role === 'helpdesk')
        <div class="row row-cards mb-3">
            @foreach ([
                ['Antrean Verifikasi', $peserta->where('status_verifikasi', 'pending')->count(), 'clock', 'bg-yellow-lt text-yellow'],
                ['Verified Hari Ini', $peserta->where('status_verifikasi', 'verified')->count(), 'checks', 'bg-green-lt text-green'],
                ['Undangan Gagal', $undanganGagal, 'alert-triangle', 'bg-red-lt text-red'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-sm-6 col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="subheader">{{ $label }}</div>
                                <span class="avatar avatar-sm rounded {{ $tone }}"><i class="ti ti-{{ $icon }}"></i></span>
                            </div>
                            <div class="h1 mb-0">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Antrean pelayanan hari ini</h3>
                <div class="card-actions">
                    <a href="{{ route('verifikasi.index') }}" class="btn btn-primary btn-sm">Cari & verifikasi</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead><tr><th>Anggota</th><th>Kelayakan</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($peserta->where('status_verifikasi', 'pending') as $row)
                            <tr>
                                <td>
                                    <div>{{ $row->anggota?->nama ?? '—' }}</div>
                                    <div class="text-secondary small">{{ $row->anggota ? $row->anggota->nbaTampil() : '—' }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $row->hasil_kelayakan === 'layak' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' }}">{{ $row->labelHasil() }}</span>
                                </td>
                                <td><span class="badge bg-yellow-lt text-yellow">{{ $row->labelStatus() }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-secondary">Belum ada peserta menunggu verifikasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($role === 'scanner')
        <div class="row row-cards">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center py-4">
                            <span class="avatar avatar-xl bg-primary-lt text-primary mb-3"><i class="ti ti-scan" style="font-size:2rem"></i></span>
                            <h3>Pindai QR Undangan</h3>
                            <p class="text-secondary mb-3">Presensi baru aktif setelah Admin membuka presensi pada hari-H.</p>
                            <div class="mx-auto" style="max-width:320px">
                                @if ($rapatModel?->presensiDibuka())
                                    <span class="badge bg-green-lt text-green mb-3">Presensi dibuka</span>
                                    <p class="text-secondary mb-3">Pindai QR peserta Verified dari halaman Kas Saya.</p>
                                    <a href="{{ route('kas-saya') }}" class="btn btn-primary w-100">
                                        <i class="ti ti-scan me-1"></i>Buka Pemindai
                                    </a>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary mb-3">Presensi belum dibuka</span>
                                    <p class="text-secondary mb-3">Presensi aktif setelah Admin membuka presensi pada hari-H.</p>
                                    <a href="{{ route('kas-saya') }}" class="btn btn-outline-primary w-100">Lihat Kas Saya</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Kas Saya</h3></div>
                    <div class="card-body">
                        <div class="text-secondary small">Kas awal (satu kali per RAT)</div>
                        <div class="h2">{{ Demo::rupiah($kasSaya === null ? 0 : (float) $kasSaya->kas_awal) }}</div>
                        <div class="text-secondary small">Sisa tersedia</div>
                        <div class="h3 mb-3">{{ Demo::rupiah($kasSaya === null ? 0 : $kasSaya->sisa()) }}</div>
                        @if ($kasSaya === null)
                            <div class="alert alert-secondary mb-0">
                                <i class="ti ti-info-circle me-1"></i>Kas awal belum dialokasikan Keuangan.
                                Bila kas tidak cukup, presensi yang memerlukan pembayaran ditolak — arahkan anggota ke Scanner lain.
                            </div>
                        @else
                            <div class="alert alert-info mb-0">
                                <i class="ti ti-info-circle me-1"></i>Kas {{ $kasSaya->labelStatus() }} sejak
                                {{ $kasSaya->diserahkan_pada?->format('d/m/Y H:i') ?? '—' }}.
                                Bila kas tidak cukup, presensi yang memerlukan pembayaran ditolak — arahkan anggota ke Scanner lain.
                            </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('kas-saya') }}" class="btn btn-outline-primary btn-sm">Lihat rincian kas</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3">
            <livewire:kuorum-ringkas />
        </div>
    @endif

    @if ($role === 'keuangan')
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">Total Kas Awal</div>
                    <div class="h1 mb-0">{{ Demo::rupiah((float) $kas->sum('kas_awal')) }}</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">Terbayar</div>
                    <div class="h1 mb-0">—</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">Sisa Tercatat</div>
                    <div class="h1 mb-0">{{ Demo::rupiah($kas->sum(fn ($row) => $row->sisa())) }}</div>
                </div></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card"><div class="card-body">
                    <div class="subheader">Scanner Menerima Kas</div>
                    <div class="h1 mb-0">{{ $kas->count() }}</div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Kas per akun Scanner</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead><tr><th>Scanner</th><th class="text-end">Kas Awal</th><th class="text-end">Dibayarkan</th><th class="text-end">Sisa</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($kas as $row)
                            <tr>
                                <td>{{ $row->scanner?->name ?? '—' }}</td>
                                <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->kas_awal) }}</td>
                                <td class="text-end">—</td>
                                <td class="text-end text-nowrap fw-bold">{{ Demo::rupiah($row->sisa()) }}</td>
                                <td>
                                    <span class="badge {{ $row->status === \App\Models\KasScanner::AKTIF ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">{{ $row->labelStatus() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada kas Scanner dialokasikan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-secondary small">Kas terikat ke akun Scanner, bukan meja. Tidak ada top-up atau transfer antar-Scanner.</div>
        </div>
    @endif
@endsection
