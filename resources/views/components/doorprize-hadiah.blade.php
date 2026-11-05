<?php

use App\Models\DoorprizeHadiah;
use App\Models\DoorprizePemenang;
use App\Models\Rapat;
use App\Services\Doorprize;
use Livewire\Component;

new class extends Component
{
    public Rapat $rapat;

    public ?int $editId = null;

    public string $nama = '';

    public int|string $jumlah = 1;

    public string $keterangan = '';

    public function with(): array
    {
        return [
            'daftarHadiah' => $this->rapat->doorprizeHadiah()
                ->withCount(['hasil as terisi' => fn ($query) => $query->where('hasil', DoorprizePemenang::MENANG)])
                ->orderBy('id')
                ->get(),
        ];
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'nama' => ['required', 'string', 'max:150'],
            'jumlah' => ['required', 'integer', 'between:1,10000'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['keterangan'] = filled($data['keterangan']) ? trim($data['keterangan']) : null;

        $layanan = new Doorprize;
        if ($this->editId === null) {
            $layanan->tambahHadiah($this->rapat, $data);
        } else {
            $hadiah = $this->rapat->doorprizeHadiah()->findOrFail($this->editId);
            $layanan->ubahHadiah($hadiah, $data);
        }

        $this->batal();
    }

    public function ubah(int $id): void
    {
        $hadiah = $this->rapat->doorprizeHadiah()->findOrFail($id);
        $this->editId = $hadiah->id;
        $this->nama = $hadiah->nama;
        $this->jumlah = $hadiah->jumlah;
        $this->keterangan = $hadiah->keterangan ?? '';
        $this->resetValidation();
    }

    public function hapus(int $id): void
    {
        $hadiah = $this->rapat->doorprizeHadiah()->findOrFail($id);
        (new Doorprize)->hapusHadiah($hadiah);

        if ($this->editId === $id) {
            $this->batal();
        }
    }

    public function batal(): void
    {
        $this->reset(['editId', 'nama', 'keterangan']);
        $this->jumlah = 1;
        $this->resetValidation();
    }
};
?>

<div wire:keydown.enter.prevent="simpan">
    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <label class="form-label" for="hadiah-nama">Nama Hadiah</label>
            <input id="hadiah-nama" type="text" class="form-control @error('nama') is-invalid @enderror" wire:model="nama" maxlength="150">
            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="hadiah-jumlah">Jumlah</label>
            <input id="hadiah-jumlah" type="number" min="1" max="10000" class="form-control @error('jumlah') is-invalid @enderror" wire:model="jumlah">
            @error('jumlah')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="form-label" for="hadiah-keterangan">Keterangan (opsional)</label>
            <input id="hadiah-keterangan" type="text" class="form-control @error('keterangan') is-invalid @enderror" wire:model="keterangan" maxlength="1000">
            @error('keterangan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @error('hadiah')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <div class="btn-list mb-3">
        <button type="button" class="btn btn-sm btn-primary" wire:click="simpan">
            <i class="ti ti-device-floppy me-1"></i>{{ $editId === null ? 'Tambah Hadiah' : 'Simpan Hadiah' }}
        </button>
        @if ($editId !== null)
            <button type="button" class="btn btn-sm btn-link" wire:click="batal">Batal ubah</button>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter mb-0">
            <thead><tr><th>Nama</th><th>Jumlah</th><th>Terisi</th><th>Sisa</th><th>Keterangan</th><th aria-label="Tindakan"></th></tr></thead>
            <tbody>
            @forelse ($daftarHadiah as $hadiah)
                <tr wire:key="hadiah-{{ $hadiah->id }}">
                    <td class="fw-bold">{{ $hadiah->nama }}</td>
                    <td>{{ $hadiah->jumlah }}</td>
                    <td>{{ $hadiah->terisi }}</td>
                    <td>{{ max(0, $hadiah->jumlah - $hadiah->terisi) }}</td>
                    <td class="text-secondary">{{ $hadiah->keterangan ?: '—' }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="ubah({{ $hadiah->id }})">Ubah</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="hapus({{ $hadiah->id }})" wire:confirm="Hapus hadiah ini?">Hapus</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-3">Belum ada hadiah.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
