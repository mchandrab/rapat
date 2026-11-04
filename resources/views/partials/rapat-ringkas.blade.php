@use('App\Support\Demo')

@if ($rapatModel === null)
    <div class="alert alert-secondary" role="status">
        <i class="ti ti-calendar-off me-1" aria-hidden="true"></i>Belum ada rapat yang disiapkan Admin.
    </div>
@else
    @php
        $warnaStatus = match ($rapatModel->status) {
            'verifikasi' => 'bg-yellow-lt text-yellow',
            'berlangsung' => 'bg-blue-lt text-blue',
            'selesai' => 'bg-green-lt text-green',
            'dibatalkan' => 'bg-red-lt text-red',
            default => 'bg-secondary-lt text-secondary',
        };
    @endphp

    <div class="card mb-3">
        <div class="card-header">
            <div>
                <div class="text-secondary text-uppercase fw-bold small mb-1">Informasi rapat</div>
                <h3 class="card-title">{{ $rapatModel->judul }}</h3>
            </div>
            <div class="card-actions">
                <span class="badge {{ $warnaStatus }}">{{ $rapatModel->labelStatus() }}</span>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="text-secondary small">Tanggal</div>
                    <div class="fw-bold">{{ $rapatModel->tgl_rapat->format('d/m/Y') }}</div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-secondary small">Lokasi</div>
                    <div class="fw-bold">{{ $rapatModel->lokasi }}</div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-secondary small">Mulai presensi</div>
                    <div class="fw-bold">{{ $rapatModel->jam_mulai_presensi }}</div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-secondary small">Mulai acara</div>
                    <div class="fw-bold">{{ $rapatModel->jam_mulai_rat }}</div>
                </div>
            </div>

            @if ($role === 'helpdesk')
                <hr class="my-3">
                <div class="text-secondary text-uppercase fw-bold small mb-2">Kriteria kelayakan</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-secondary small">Mode penilaian</div>
                        <div class="fw-bold">{{ $rapatModel->labelModeKelayakan() }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">Minimum setahun</div>
                        <div class="fw-bold">{{ Demo::rupiah((float) $rapatModel->minimal_simpanan_tahunan) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">
                            {{ $rapatModel->mode_kelayakan === 'B' ? 'Pinjaman lancar' : 'Kolektibilitas maksimum' }}
                        </div>
                        <div class="fw-bold">
                            {{ $rapatModel->mode_kelayakan === 'B' ? ($rapatModel->wajib_pinjaman_lancar ? 'Wajib' : 'Tidak wajib') : 'Kol '.$rapatModel->batas_kolektibilitas }}
                        </div>
                    </div>
                </div>
            @elseif ($role === 'scanner')
                <hr class="my-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-secondary small">Mode presensi</div>
                        <div class="fw-bold">{{ $rapatModel->labelModePresensi() }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-secondary small">Gerbang presensi</div>
                        <div class="fw-bold {{ $rapatModel->presensiDibuka() ? 'text-green' : 'text-secondary' }}">
                            {{ $rapatModel->presensiDibuka() ? 'Dibuka — pemindaian diizinkan' : 'Belum dibuka Admin' }}
                        </div>
                    </div>
                </div>
            @elseif ($role === 'keuangan')
                <hr class="my-3">
                <div class="text-secondary text-uppercase fw-bold small mb-2">Konfigurasi transport</div>
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Mode presensi</div>
                        <div class="fw-bold">{{ $rapatModel->labelModePresensi() }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Transport dasar</div>
                        <div class="fw-bold">{{ Demo::rupiah((float) $rapatModel->transport_dasar) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Toleransi terlambat</div>
                        <div class="fw-bold">{{ $rapatModel->toleransi_menit }} menit</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-secondary small">Peringatan kas</div>
                        <div class="fw-bold">{{ Demo::rupiah($rapatModel->batasPeringatan()) }}</div>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer text-secondary small">
            <i class="ti ti-lock me-1" aria-hidden="true"></i>Informasi baca-saja. Perubahan parameter hanya dapat dilakukan Admin.
        </div>
    </div>
@endif
