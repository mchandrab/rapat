<?php

use App\Models\Rapat;
use App\Services\Kuorum;
use Livewire\Component;

new class extends Component
{
    public string $judul = 'Ringkasan Kuorum';

    public bool $publik = false;

    public function with(): array
    {
        $rapat = Rapat::query()->latest('id')->first();

        return [
            'rapat' => $rapat,
            'kuorum' => $rapat === null ? null : (new Kuorum)->hitung($rapat),
        ];
    }
};
?>

<div wire:poll.10s class="{{ $publik ? 'erat-quorum-public' : 'erat-quorum-summary' }}">
    @if ($rapat === null)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <span class="avatar avatar-xl bg-secondary-lt text-secondary mb-3">
                    <i class="ti ti-calendar-off fs-1" aria-hidden="true"></i>
                </span>
                <h2 class="h3 mb-2">Belum ada rapat</h2>
                <p class="text-secondary mb-0">Belum ada rapat yang dapat ditampilkan.</p>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header erat-quorum-card-header">
                <div>
                    <div class="text-secondary text-uppercase fw-bold small mb-1">{{ $judul }}</div>
                    <h2 class="card-title h3 mb-0">{{ $rapat->judul }}</h2>
                </div>
                <div class="card-actions">
                    <span class="badge erat-quorum-live">
                        <span class="erat-live-dot" aria-hidden="true"></span> Diperbarui otomatis
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="erat-quorum-status {{ $kuorum['tercapai'] ? 'is-reached' : 'is-waiting' }} mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="erat-quorum-status-icon">
                            <i class="ti {{ $kuorum['tercapai'] ? 'ti-circle-check' : 'ti-clock-hour-4' }}" aria-hidden="true"></i>
                        </span>
                        <div>
                            <div class="text-uppercase fw-bold small opacity-75">Status rapat</div>
                            <div class="h2 mb-0">{{ $kuorum['tercapai'] ? 'Kuorum tercapai — rapat sah' : 'Kuorum belum tercapai' }}</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-lg">
                        <div class="erat-quorum-stat is-primary">
                            <div class="erat-quorum-stat-icon"><i class="ti ti-user-check" aria-hidden="true"></i></div>
                            <div class="text-secondary small">Hadir</div>
                            <div class="display-5 fw-bold">{{ $kuorum['hadir'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="erat-quorum-stat">
                            <div class="erat-quorum-stat-icon"><i class="ti ti-target-arrow" aria-hidden="true"></i></div>
                            <div class="text-secondary small">Target</div>
                            <div class="display-5 fw-bold">{{ $kuorum['target'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="erat-quorum-stat">
                            <div class="erat-quorum-stat-icon"><i class="ti ti-users" aria-hidden="true"></i></div>
                            <div class="text-secondary small">Populasi</div>
                            <div class="display-5 fw-bold">{{ $kuorum['populasi'] }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="erat-quorum-stat">
                            <div class="erat-quorum-stat-icon"><i class="ti ti-chart-donut" aria-hidden="true"></i></div>
                            <div class="text-secondary small">Persentase</div>
                            <div class="display-5 fw-bold">{{ $kuorum['persen'] }}%</div>
                        </div>
                    </div>
                    <div class="col-12 col-lg">
                        <div class="erat-quorum-stat {{ $kuorum['kekurangan'] === 0 ? 'is-complete' : '' }}">
                            <div class="erat-quorum-stat-icon"><i class="ti ti-user-plus" aria-hidden="true"></i></div>
                            <div class="text-secondary small">Kekurangan</div>
                            <div class="display-5 fw-bold">{{ $kuorum['kekurangan'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mt-4 mb-2">
                    <div class="fw-bold">Progres menuju target</div>
                    <div class="text-secondary small">{{ $kuorum['hadir'] }} dari {{ $kuorum['target'] }}</div>
                </div>
                <div class="progress erat-quorum-progress" role="progressbar" aria-label="Progres kuorum"
                     aria-valuenow="{{ $kuorum['hadir'] }}" aria-valuemin="0" aria-valuemax="{{ max($kuorum['target'], 1) }}">
                    <div class="progress-bar {{ $kuorum['tercapai'] ? 'bg-green' : 'bg-yellow' }}"
                         style="width: {{ $kuorum['target'] === 0 ? 0 : min(100, round($kuorum['hadir'] / $kuorum['target'] * 100)) }}%"></div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2 justify-content-between text-secondary small">
                <span><i class="ti ti-info-circle me-1" aria-hidden="true"></i>Hanya anggota biasa aktif yang dihitung.</span>
                <span><i class="ti ti-refresh me-1" aria-hidden="true"></i>Pembaruan setiap 10 detik.</span>
            </div>
        </div>
    @endif
</div>
