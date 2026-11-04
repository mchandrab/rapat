<?php

use App\Models\Rapat;
use App\Services\Kuorum;
use Livewire\Component;

new class extends Component
{
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

<div wire:poll.10s>
    @if ($rapat === null)
        <div class="alert alert-warning mb-0">Belum ada rapat. Admin perlu menyiapkan rapat lebih dulu.</div>
    @else
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kuorum — {{ $rapat->judul }}</h3>
                <div class="card-actions">
                    <span class="badge {{ $kuorum['tercapai'] ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                        {{ $kuorum['tercapai'] ? 'Kuorum tercapai — rapat sah' : 'Kuorum belum tercapai' }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                @if ($kuorum['populasi'] === 0)
                    <div class="alert alert-warning">
                        <i class="ti ti-info-circle me-1"></i>Peserta belum disiapkan untuk rapat ini. Admin perlu menekan
                        <b>Siapkan Peserta</b> agar populasi kuorum terisi.
                    </div>
                @endif
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">Hadir (anggota biasa)</div>
                        <div class="display-5 fw-bold">{{ $kuorum['hadir'] }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">Target kuorum</div>
                        <div class="display-5 fw-bold">{{ $kuorum['target'] }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">Populasi dasar</div>
                        <div class="display-5 fw-bold">{{ $kuorum['populasi'] }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-secondary small">Persentase hadir</div>
                        <div class="display-5 fw-bold">{{ $kuorum['persen'] }}%</div>
                    </div>
                </div>

                <div class="progress mt-3" role="progressbar" aria-label="Progres kuorum"
                     aria-valuenow="{{ $kuorum['hadir'] }}" aria-valuemin="0" aria-valuemax="{{ max($kuorum['target'], 1) }}">
                    <div class="progress-bar {{ $kuorum['tercapai'] ? 'bg-green' : 'bg-yellow' }}"
                         style="width: {{ $kuorum['target'] === 0 ? 0 : min(100, round($kuorum['hadir'] / $kuorum['target'] * 100)) }}%"></div>
                </div>

                <div class="datagrid mt-3">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Basis populasi</div>
                        <div class="datagrid-content">
                            {{ $rapat->basis_kuorum === 'terverifikasi' ? 'Anggota biasa terverifikasi' : 'Semua anggota biasa' }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Rumus target</div>
                        <div class="datagrid-content">
                            floor({{ (int) $rapat->persen_kuorum }}% × {{ $kuorum['populasi'] }}) + {{ (int) $rapat->tambahan_kuorum }}
                            = {{ $kuorum['target'] }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Kekurangan</div>
                        <div class="datagrid-content">
                            {{ $kuorum['tercapai'] ? 'Tidak ada' : $kuorum['kekurangan'].' peserta lagi' }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Kuorum tercapai pada</div>
                        <div class="datagrid-content">
                            {{ $kuorum['jam_tercapai']?->format('d/m/Y H:i:s') ?? 'Belum tercapai' }}
                        </div>
                    </div>
                </div>

                @if ($kuorum['hadir_luar_biasa'] > 0)
                    <div class="text-secondary small mt-3">
                        {{ $kuorum['hadir_luar_biasa'] }} anggota luar biasa hadir dan tidak dihitung dalam kuorum.
                    </div>
                @endif
            </div>
            <div class="card-footer text-secondary small">
                Hanya anggota jenis <b>biasa</b> yang berstatus aktif dihitung. Angka diperbarui otomatis setiap 10 detik.
            </div>
        </div>
    @endif
</div>
