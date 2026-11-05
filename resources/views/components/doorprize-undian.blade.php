<?php

use App\Models\DoorprizeHadiah;
use App\Models\DoorprizePemenang;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Services\Doorprize;
use Livewire\Component;

new class extends Component
{
    public ?int $hadiahId = null;

    public ?int $kandidatId = null;

    public ?int $kandidatHadiahId = null;

    public string $pesan = '';

    public function with(): array
    {
        $rapat = Rapat::query()->latest('id')->first();
        if ($rapat === null) {
            return ['rapat' => null, 'aktif' => collect(), 'habis' => collect(), 'menang' => collect(), 'gugur' => collect(), 'kandidat' => null, 'bolehUndi' => false, 'jumlahKandidat' => 0];
        }

        $hadiah = $rapat->doorprizeHadiah()
            ->withCount(['hasil as terisi' => fn ($query) => $query->where('hasil', DoorprizePemenang::MENANG)])
            ->orderBy('id')
            ->get();
        $hasil = DoorprizePemenang::query()
            ->with('peserta.anggota')
            ->where('rapat_id', $rapat->id)
            ->orderBy('id')
            ->get();

        return [
            'rapat' => $rapat,
            'aktif' => $hadiah->filter(fn ($baris) => $baris->terisi < $baris->jumlah)->values(),
            'habis' => $hadiah->filter(fn ($baris) => $baris->terisi >= $baris->jumlah)->values(),
            'menang' => $hasil->where('hasil', DoorprizePemenang::MENANG)->values(),
            'gugur' => $hasil->where('hasil', DoorprizePemenang::GUGUR)->values(),
            'kandidat' => $this->kandidatId === null ? null : Peserta::query()->with('anggota')->find($this->kandidatId),
            'bolehUndi' => $rapat->status === Rapat::BERLANGSUNG
                || ($rapat->status === Rapat::VERIFIKASI && $rapat->presensi_dibuka_at !== null),
            'jumlahKandidat' => (new Doorprize)->kandidat($rapat)->count(),
        ];
    }

    public function undi(): void
    {
        $rapat = Rapat::query()->latest('id')->first();
        $hadiah = $rapat?->doorprizeHadiah()->find($this->hadiahId);
        if ($rapat === null) {
            return;
        }
        if ($hadiah === null) {
            $this->addError('hadiah', 'Pilih hadiah yang masih tersedia.');

            return;
        }

        $kandidat = (new Doorprize)->undi($rapat, $hadiah);
        if ($kandidat === null) {
            $this->pesan = 'Tidak ada peserta hadir yang bisa diundi.';

            return;
        }

        $this->kandidatId = $kandidat->id;
        $this->kandidatHadiahId = $hadiah->id;
        $this->pesan = '';
        $this->resetValidation();
    }

    public function putuskan(bool $masihAda): void
    {
        $rapat = Rapat::query()->latest('id')->first();
        $kandidat = $this->kandidatId === null ? null : Peserta::query()->with('anggota')->find($this->kandidatId);
        $hadiah = $rapat?->doorprizeHadiah()->find($this->kandidatHadiahId);
        if ($rapat === null || $kandidat === null || $hadiah === null) {
            $this->batal();

            return;
        }

        (new Doorprize)->putuskan($rapat, $kandidat, $hadiah, $masihAda);
        $this->pesan = $masihAda
            ? $kandidat->anggota?->nama.' menang '.$hadiah->nama.'.'
            : $kandidat->anggota?->nama.' gugur (sudah pulang).';
        $this->kandidatId = null;
        $this->kandidatHadiahId = null;
    }

    public function batal(): void
    {
        $this->kandidatId = null;
        $this->kandidatHadiahId = null;
        $this->pesan = '';
    }
};
?>

<div>
    @if ($rapat === null)
        <div class="alert alert-warning mb-0">Belum ada rapat. Admin perlu menyiapkan rapat lebih dulu.</div>
    @else
        @unless ($bolehUndi)
            <div class="alert alert-warning">Undian hanya tersedia saat Verifikasi setelah presensi dibuka atau saat rapat Berlangsung.</div>
        @endunless
        @error('doorprize')<div class="alert alert-danger">{{ $message }}</div>@enderror
        @error('hadiah')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <div class="row g-4 align-items-start">
            <section class="col-lg-4">
                <div class="dp-panel p-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom border-secondary-subtle pb-3 mb-3">
                        <div class="small fw-bold text-uppercase"><i class="ti ti-users me-1"></i>Kesiapan Undian</div>
                        <span class="badge bg-dark text-success">{{ $jumlahKandidat }} peserta</span>
                    </div>

                    <label class="form-label small" for="pilih-hadiah">Hadiah yang diundi</label>
                    <select id="pilih-hadiah" class="form-select bg-dark border-secondary-subtle" wire:model="hadiahId" @disabled($kandidat !== null)>
                        <option value="">Pilih hadiah</option>
                        @foreach ($aktif as $hadiah)
                            <option value="{{ $hadiah->id }}">{{ $hadiah->nama }} — sisa {{ $hadiah->jumlah - $hadiah->terisi }}</option>
                        @endforeach
                    </select>

                    <div class="mt-4 d-grid gap-2">
                        @forelse ($aktif as $hadiah)
                            <div class="dp-list-item p-3 d-flex justify-content-between gap-3">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate">{{ $hadiah->nama }}</div>
                                    <div class="small dp-muted">{{ $hadiah->keterangan ?: 'Tanpa keterangan' }}</div>
                                </div>
                                <span class="badge bg-success-lt text-success align-self-start">{{ $hadiah->jumlah - $hadiah->terisi }} tersisa</span>
                            </div>
                        @empty
                            <div class="text-center dp-muted py-4"><i class="ti ti-gift-off fs-2 d-block mb-2"></i>Tidak ada hadiah yang masih tersedia.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="col-lg-5 text-center">
                <div class="dp-stage p-4 p-md-5 d-flex flex-column align-items-center justify-content-between position-relative overflow-hidden">
                    <span class="dp-badge d-inline-flex align-items-center gap-2 px-3 py-2 small fw-bold text-uppercase">
                        <span class="dp-dot"></span>{{ $kandidat === null ? 'Siap Mengundi' : 'Kandidat Terpilih' }}
                    </span>
                    <div class="my-auto py-5 px-2">
                        <div class="dp-stage-name fw-black text-white">
                            {{ $kandidat?->anggota?->nama ?? '—' }}
                        </div>
                        @if ($kandidat !== null)
                            <div class="mt-3 dp-muted">{{ $kandidat->anggota?->nbaTampil() }}</div>
                        @else
                            <div class="mt-3 dp-muted">Pilih hadiah, lalu mulai pengundian.</div>
                        @endif
                    </div>
                    <div class="small dp-muted">Pemenang dipilih acak oleh server dari peserta hadir.</div>
                </div>

                <button type="button" class="btn dp-primary btn-lg w-75 mt-4 py-3" wire:click="undi" wire:loading.attr="disabled"
                        @disabled(! $bolehUndi || $aktif->isEmpty() || $kandidat !== null || $jumlahKandidat === 0)>
                    <i class="ti ti-bolt me-1"></i>UNDI SEKARANG
                </button>

                @if ($pesan !== '')
                    <div class="alert alert-info mt-3 mb-0" role="status">{{ $pesan }}</div>
                @endif
            </section>

            <section class="col-lg-3">
                <div class="dp-panel p-4">
                    <div class="d-flex align-items-center justify-content-between border-bottom border-secondary-subtle pb-3 mb-3">
                        <div class="small fw-bold text-uppercase"><i class="ti ti-trophy me-1"></i>Riwayat Pemenang</div>
                        <span class="badge bg-dark">{{ $menang->count() }}</span>
                    </div>
                    <div class="dp-list d-grid gap-2">
                        @forelse ($menang->reverse() as $baris)
                            <div class="dp-list-item p-3">
                                <div class="fw-semibold">{{ $baris->peserta?->anggota?->nama }}</div>
                                <div class="small text-success">{{ $baris->nama_hadiah_snapshot }}</div>
                                <div class="small dp-muted">{{ $baris->peserta?->anggota?->nbaTampil() }}</div>
                            </div>
                        @empty
                            <div class="text-center dp-muted py-5"><i class="ti ti-crown fs-2 d-block mb-2"></i>Belum ada pemenang.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-6">
                <div class="dp-panel p-4 h-100">
                    <div class="small fw-bold text-uppercase mb-3">Hadiah Habis</div>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse ($habis as $hadiah)
                            <span class="badge bg-dark border border-secondary-subtle p-2">{{ $hadiah->nama }} · {{ $hadiah->jumlah }} pemenang</span>
                        @empty
                            <span class="dp-muted small">Belum ada hadiah habis.</span>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="dp-panel p-4 h-100">
                    <div class="small fw-bold text-uppercase mb-3">Gugur (sudah pulang)</div>
                    <div class="d-flex flex-wrap gap-2">
                        @forelse ($gugur as $baris)
                            <span class="badge bg-dark border border-secondary-subtle p-2">{{ $baris->peserta?->anggota?->nama }}</span>
                        @empty
                            <span class="dp-muted small">Tidak ada peserta gugur.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if ($kandidat !== null)
            <div class="modal modal-blur fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="judul-konfirmasi-doorprize">
                <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                    <div class="modal-header"><h2 class="modal-title" id="judul-konfirmasi-doorprize">Konfirmasi kehadiran</h2></div>
                    <div class="modal-body text-center">
                        <div class="h1 mb-1">{{ $kandidat->anggota?->nama }}</div>
                        <div class="text-secondary mb-3">{{ $kandidat->anggota?->nbaTampil() }}</div>
                        <div class="fw-bold">Apakah peserta masih ada di ruangan?</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link me-auto" wire:click="batal">Batal</button>
                        <button type="button" class="btn btn-outline-danger" wire:click="putuskan(false)">Sudah pulang</button>
                        <button type="button" class="btn btn-success" wire:click="putuskan(true)">Masih ada</button>
                    </div>
                </div></div>
            </div>
            <div class="modal-backdrop fade show"></div>
        @endif
    @endif
</div>
