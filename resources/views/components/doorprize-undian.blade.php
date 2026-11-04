<?php

use App\Models\DoorprizePemenang;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Services\Doorprize;
use Livewire\Component;

new class extends Component
{
    public ?int $kandidatId = null;

    public string $pesan = '';

    public function with(): array
    {
        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            return ['rapat' => null, 'target' => 0, 'menang' => collect(), 'gugur' => collect(), 'sisa' => 0, 'kandidat' => null];
        }

        $layanan = new Doorprize;

        $hasil = DoorprizePemenang::query()
            ->with('peserta.anggota')
            ->where('rapat_id', $rapat->id)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();

        return [
            'rapat' => $rapat,
            'target' => $layanan->target($rapat),
            'menang' => $hasil->where('hasil', DoorprizePemenang::MENANG)->values(),
            'gugur' => $hasil->where('hasil', DoorprizePemenang::GUGUR)->values(),
            'sisa' => $layanan->sisa($rapat),
            'kandidat' => $this->kandidatId === null ? null : Peserta::query()->with('anggota')->find($this->kandidatId),
        ];
    }

    public function undi(): void
    {
        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            return;
        }

        $layanan = new Doorprize;

        if ($layanan->sisa($rapat) === 0) {
            $this->pesan = 'Jumlah pemenang sudah terpenuhi.';

            return;
        }

        $kandidat = $layanan->undi($rapat);

        if ($kandidat === null) {
            $this->pesan = 'Tidak ada peserta hadir yang bisa diundi.';

            return;
        }

        $this->kandidatId = $kandidat->id;
        $this->pesan = '';
    }

    public function putuskan(bool $masihAda): void
    {
        $rapat = Rapat::query()->latest('id')->first();
        $kandidat = $this->kandidatId === null ? null : Peserta::query()->with('anggota')->find($this->kandidatId);

        if ($rapat === null || $kandidat === null) {
            $this->batal();

            return;
        }

        (new Doorprize)->putuskan($rapat, $kandidat, $masihAda);

        $this->pesan = $masihAda
            ? $kandidat->anggota?->nama.' menang.'
            : $kandidat->anggota?->nama.' gugur (sudah pulang).';

        $this->kandidatId = null;
    }

    public function batal(): void
    {
        $this->kandidatId = null;
        $this->pesan = '';
    }
};
?>

<div>
    @if ($rapat === null)
        <div class="alert alert-warning mb-0">Belum ada rapat. Admin perlu menyiapkan rapat lebih dulu.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small">Target pemenang</div>
                    <div class="display-6 fw-bold">{{ $target }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small">Sudah menang</div>
                    <div class="display-6 fw-bold text-green">{{ $menang->count() }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small">Gugur (sudah pulang)</div>
                    <div class="display-6 fw-bold text-secondary">{{ $gugur->count() }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card"><div class="card-body text-center">
                    <div class="text-secondary small">Sisa kursi</div>
                    <div class="display-6 fw-bold">{{ $sisa }}</div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Undian — {{ $rapat->judul }}</h3>
                <div class="card-actions">
                    <button type="button" class="btn btn-primary" wire:click="undi"
                            wire:loading.attr="disabled" @disabled($sisa === 0 || $kandidat !== null)>
                        <i class="ti ti-gift me-1"></i>Undi
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if ($pesan !== '')
                    <div class="alert alert-info" role="status">{{ $pesan }}</div>
                @endif

                @if ($sisa === 0)
                    <div class="alert alert-success mb-3">Semua kursi pemenang sudah terisi.</div>
                @endif

                @if ($kandidat !== null)
                    <div class="text-secondary small">
                        Kandidat terpilih. Konfirmasi apakah peserta masih berada di ruangan.
                    </div>
                @else
                    <div class="text-secondary small">
                        Klik <b>Undi</b> untuk memilih satu peserta hadir secara acak. Peserta yang sudah menang atau
                        dinyatakan pulang tidak akan muncul lagi.
                    </div>
                @endif
            </div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Pemenang</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter mb-0">
                            <thead><tr><th>#</th><th>NBA</th><th>Nama</th></tr></thead>
                            <tbody>
                            @forelse ($menang as $baris)
                                <tr>
                                    <td>{{ $baris->urutan }}</td>
                                    <td class="text-nowrap">{{ $baris->peserta?->anggota?->nbaTampil() }}</td>
                                    <td>{{ $baris->peserta?->anggota?->nama }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-secondary py-3">Belum ada pemenang.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Gugur (sudah pulang)</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter mb-0">
                            <thead><tr><th>NBA</th><th>Nama</th></tr></thead>
                            <tbody>
                            @forelse ($gugur as $baris)
                                <tr>
                                    <td class="text-nowrap">{{ $baris->peserta?->anggota?->nbaTampil() }}</td>
                                    <td>{{ $baris->peserta?->anggota?->nama }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-secondary py-3">Tidak ada.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if ($kandidat !== null)
            <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
                 aria-labelledby="judul-konfirmasi-doorprize">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="judul-konfirmasi-doorprize">Konfirmasi kehadiran</h2>
                        </div>
                        <div class="modal-body text-center">
                            <div class="h1 mb-1">{{ $kandidat->anggota?->nama }}</div>
                            <div class="text-secondary mb-3">{{ $kandidat->anggota?->nbaTampil() }}</div>
                            <div class="fw-bold">Apakah peserta masih ada di ruangan?</div>
                            <div class="text-secondary small mt-1">
                                <b>Masih ada</b> = menang. <b>Sudah pulang</b> = gugur, dan tidak diundi lagi.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-link me-auto" wire:click="batal">Batal</button>
                            <button type="button" class="btn btn-outline-danger" wire:click="putuskan(false)">
                                Sudah pulang
                            </button>
                            <button type="button" class="btn btn-success" wire:click="putuskan(true)">
                                Masih ada
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif
    @endif
</div>
