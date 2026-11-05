<?php

use App\Models\Anggota;
use App\Models\Peserta;
use App\Models\Rapat;
use App\Services\PenilaianKelayakan;
use App\Services\UndanganPeserta;
use App\Services\VerifikasiPeserta;
use App\Support\Demo;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /** Tabler memakai Bootstrap 5; tema bawaan Livewire (Tailwind) akan tampil rusak. */
    protected $paginationTheme = 'bootstrap';

    public string $cari = '';

    public string $filterStatus = 'semua';

    public string $filterKelayakan = 'semua';

    public string $filterVerifikasi = 'semua';

    public ?int $anggotaTerpilih = null;

    /** Modal Kelola dibuka sebagai tampilan informasi; form hanya setelah Admin menekan Edit Data. */
    public bool $modeEdit = false;

    public string $alasan = '';

    public string $pesan = '';

    public ?string $urlCetak = null;

    public function updated(string $properti): void
    {
        if (in_array($properti, ['cari', 'filterStatus', 'filterKelayakan', 'filterVerifikasi'], true)) {
            $this->resetPage();
        }
    }

    public function kelola(int $id): void
    {
        $this->anggotaTerpilih = $id;
        $this->modeEdit = false;
        $this->alasan = '';
        $this->pesan = '';
    }

    public function edit(): void
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $this->modeEdit = true;
    }

    public function batalEdit(): void
    {
        $this->modeEdit = false;
    }

    public function tutupKelola(): void
    {
        $this->anggotaTerpilih = null;
        $this->modeEdit = false;
        $this->alasan = '';
    }

    public function verifikasi(int $anggotaId): void
    {
        $peserta = $this->peserta($anggotaId);

        if (! $this->bolehDiputuskan($peserta)) {
            return;
        }

        // BR-004: Tidak Layak tetapi Verified wajib alasan yang bisa diaudit.
        if ($peserta->hasil_kelayakan === Peserta::TIDAK_LAYAK && trim($this->alasan) === '') {
            $this->pesan = 'Peserta Tidak Layak hanya boleh Verified dengan alasan tertulis.';

            return;
        }

        (new VerifikasiPeserta)->verifikasi($peserta, auth()->user(), trim($this->alasan) ?: null);

        $this->alasan = '';
        $this->pesan = 'Peserta ditandai Verified. Token QR rapat terbit sekali dan dipakai ulang saat cetak ulang.';
    }

    public function tolak(int $anggotaId): void
    {
        $peserta = $this->peserta($anggotaId);

        if (! $this->bolehDiputuskan($peserta)) {
            return;
        }

        (new VerifikasiPeserta)->tolak($peserta, auth()->user(), trim($this->alasan) ?: null);

        $this->alasan = '';
        $this->pesan = 'Peserta ditandai Rejected dan keputusan tercatat di log audit.';
    }

    public function kirimWhatsApp(int $anggotaId): void
    {
        $this->undangan($anggotaId, 'wa');
    }

    public function cetakUndangan(int $anggotaId): void
    {
        $this->undangan($anggotaId, 'cetak');
    }

    private function undangan(int $anggotaId, string $metode): void
    {
        $peserta = $this->peserta($anggotaId);

        if ($peserta === null || $peserta->status_verifikasi !== Peserta::VERIFIED) {
            $this->pesan = 'Undangan hanya dapat dikirim atau dicetak setelah peserta Verified.';

            return;
        }

        $log = (new UndanganPeserta)->kirim($peserta, auth()->user(), $metode);

        $this->urlCetak = $metode === 'cetak' ? route('undangan.cetak', $peserta) : null;
        $this->pesan = $log->detail_error ?? ($metode === 'cetak'
            ? 'Undangan dicetak dan dicatat pada Riwayat Undangan.'
            : 'Permintaan kirim WhatsApp diterima Fonnte dan dicatat pada Riwayat Undangan.');
    }

    /**
     * Hanya Admin/Helpdesk, hanya sebelum rapat dikunci, dan hanya bila peserta sudah disiapkan.
     */
    private function bolehDiputuskan(?Peserta $peserta): bool
    {
        abort_unless(in_array(auth()->user()->role, ['admin', 'helpdesk'], true), 403);

        if ($peserta === null) {
            $this->pesan = 'Peserta belum disiapkan untuk rapat ini. Admin perlu menekan Siapkan Peserta.';

            return false;
        }

        if ($peserta->rapat->terkunci()) {
            $this->pesan = 'Rapat sudah dikunci; keputusan verifikasi tidak dapat diubah.';

            return false;
        }

        return true;
    }

    private function rapat(): ?Rapat
    {
        return Rapat::query()->latest('id')->first();
    }

    private function peserta(int $anggotaId): ?Peserta
    {
        $rapat = $this->rapat();

        if ($rapat === null) {
            return null;
        }

        return Peserta::query()
            ->where('rapat_id', $rapat->id)
            ->where('anggota_id', $anggotaId)
            ->first();
    }

    public function with(): array
    {
        $rapat = $this->rapat();
        $terpilih = $this->anggotaTerpilih === null ? null : Anggota::find($this->anggotaTerpilih);

        $pesertaTerpilih = ($rapat !== null && $terpilih !== null)
            ? Peserta::query()->where('rapat_id', $rapat->id)->where('anggota_id', $terpilih->id)->first()
            : null;

        $daftar = Anggota::query()
            ->select('anggota.*')
            ->when($rapat !== null, fn ($q) => $q->leftJoin('peserta', function ($join) use ($rapat): void {
                $join->on('peserta.anggota_id', '=', 'anggota.id')
                    ->where('peserta.rapat_id', $rapat->id);
            })->addSelect('peserta.hasil_kelayakan', 'peserta.status_verifikasi'))
            ->cari($this->cari)
            ->when($this->filterStatus !== 'semua', fn ($q) => $q->where('anggota.status_anggota', $this->filterStatus))
            ->when($rapat !== null && $this->filterKelayakan !== 'semua', fn ($q) => $q->where('peserta.hasil_kelayakan', $this->filterKelayakan))
            ->when($rapat !== null && $this->filterVerifikasi !== 'semua', fn ($q) => $q->where('peserta.status_verifikasi', $this->filterVerifikasi))
            ->orderBy('anggota.nama')
            ->paginate(15);

        return [
            'rapat' => $rapat,
            'daftar' => $daftar,
            'terpilih' => $terpilih,
            'pesertaTerpilih' => $pesertaTerpilih,
            'minimum' => ($rapat !== null && $terpilih !== null)
                ? ($pesertaTerpilih?->snapshot_minimum_proporsional !== null
                    ? (float) $pesertaTerpilih->snapshot_minimum_proporsional
                    : PenilaianKelayakan::minimum($terpilih, $rapat))
                : null,
        ];
    }
};
?>

<div>
    @if ($pesan !== '')
        <div class="alert alert-info" role="status">
            {{ $pesan }}
            @if ($urlCetak)
                <a href="{{ $urlCetak }}" target="_blank" rel="noopener" class="alert-link ms-1">Buka &amp; cetak undangan</a>
            @endif
        </div>
    @endif

    @if ($rapat === null || $rapat->peserta()->doesntExist())
        <div class="alert alert-warning" role="status">
            <i class="ti ti-alert-triangle me-1"></i>
            Peserta rapat belum disiapkan. Admin perlu membuka <b>Manajemen Rapat</b> dan menekan <b>Siapkan Peserta</b> agar
            kelayakan dan verifikasi dapat diproses.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-lg-4">
                    <input wire:model.live.debounce.300ms="cari" class="form-control" placeholder="Cari NBA atau nama anggota, mis. 020006.000.010.339">
                </div>
                <div class="col-lg-3">
                    <select wire:model.live="filterStatus" class="form-select" aria-label="Filter status keanggotaan">
                        <option value="semua">Semua Status Keanggotaan</option>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <select wire:model.live="filterKelayakan" class="form-select" aria-label="Filter kelayakan">
                        <option value="semua">Semua Kelayakan</option>
                        <option value="layak">Layak</option>
                        <option value="tidak_layak">Tidak Layak</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <select wire:model.live="filterVerifikasi" class="form-select" aria-label="Filter status verifikasi">
                        <option value="semua">Semua Status</option>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr>
                    <th>NBA</th>
                    <th>Nama</th>
                    <th>No. WhatsApp</th>
                    <th class="text-end">Simpanan Wajib</th>
                    <th>Kolektibilitas</th>
                    <th>Kelayakan</th>
                    <th>Status Verifikasi</th>
                    <th>Tindakan</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($daftar as $row)
                    <tr wire:key="anggota-{{ $row->id }}">
                        <td class="fw-semibold text-nowrap">{{ $row->nbaTampil() }}</td>
                        <td>
                            <div>{{ $row->nama }}</div>
                            @if ($row->status_anggota === 'nonaktif')
                                <span class="badge bg-red-lt text-red">Nonaktif</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $row->no_hp ?? '—' }}</td>
                        <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->simpanan_wajib) }}</td>
                        <td>Kol {{ $row->kolektibilitas }}</td>
                        <td>
                            @if ($row->hasil_kelayakan === null)
                                <span class="badge bg-secondary-lt text-secondary">Belum disiapkan</span>
                            @else
                                <span class="badge {{ $row->hasil_kelayakan === 'layak' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' }}">
                                    {{ $row->hasil_kelayakan === 'layak' ? 'Layak' : 'Tidak Layak' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @php($status = $row->status_verifikasi ?? 'pending')
                            <span class="badge {{ match ($status) {
                                'verified' => 'bg-green-lt text-green',
                                'rejected' => 'bg-red-lt text-red',
                                default => 'bg-yellow-lt text-yellow',
                            } }}">{{ ucfirst($status) }}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" wire:click="kelola({{ $row->id }})">Kelola</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Tidak ada anggota yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($daftar->hasPages())
            <div class="card-footer">{{ $daftar->links() }}</div>
        @endif
    </div>

    @if ($terpilih)
        <div class="modal modal-blur show d-block" tabindex="-1" role="dialog" aria-modal="true" wire:key="modal-anggota-{{ $terpilih->id }}">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title">Kelola Anggota</h2>
                            <div class="text-secondary small">{{ $terpilih->nbaTampil() }} • {{ $terpilih->nama }}</div>
                        </div>
                        <button type="button" class="btn-close" wire:click="tutupKelola" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @if ($modeEdit)
                            <form method="POST" action="{{ route('anggota.update', $terpilih) }}">
                                @csrf
                                @method('PUT')
                                @include('admin.partials.anggota-form', ['anggota' => $terpilih, 'awalan' => 'ubah-'])
                                <div class="btn-list mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Simpan Perubahan</button>
                                    <button type="button" class="btn btn-link" wire:click="batalEdit">Batal</button>
                                </div>
                            </form>
                        @else
                            <div class="datagrid mb-3">
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Status keanggotaan</div>
                                    <div class="datagrid-content">{{ $terpilih->status_anggota === 'aktif' ? 'Aktif' : 'Nonaktif' }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Simpanan wajib per 31 Desember</div>
                                    <div class="datagrid-content">
                                        {{ Demo::rupiah((float) ($pesertaTerpilih?->snapshot_simpanan_wajib ?? $terpilih->simpanan_wajib)) }}
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Minimum proporsional</div>
                                    <div class="datagrid-content">{{ $minimum === null ? '—' : Demo::rupiah($minimum) }}</div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Hasil kelayakan otomatis</div>
                                    <div class="datagrid-content">
                                        @if ($pesertaTerpilih === null)
                                            <span class="text-secondary">Belum disiapkan</span>
                                        @else
                                            <span class="badge {{ $pesertaTerpilih->hasil_kelayakan === 'layak' ? 'bg-green-lt text-green' : 'bg-red-lt text-red' }}">
                                                {{ $pesertaTerpilih->labelHasil() }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="datagrid-item">
                                    <div class="datagrid-title">Status verifikasi rapat</div>
                                    <div class="datagrid-content">{{ $pesertaTerpilih?->labelStatus() ?? 'Belum disiapkan' }}</div>
                                </div>
                                @if ($pesertaTerpilih?->qr_dibuat_at)
                                    <div class="datagrid-item">
                                        <div class="datagrid-title">Token QR</div>
                                        <div class="datagrid-content">Terbit {{ $pesertaTerpilih->qr_dibuat_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                @endif
                            </div>

                            @if ($pesertaTerpilih === null)
                                <div class="alert alert-secondary">
                                    Peserta belum disiapkan untuk rapat ini. Data master di atas sudah tersimpan di database.
                                </div>
                            @elseif ($pesertaTerpilih->alasan_override)
                                <div class="alert alert-info">
                                    <b>Alasan pemberian undangan:</b> {{ $pesertaTerpilih->alasan_override }}
                                </div>
                            @elseif ($pesertaTerpilih->alasan_penolakan)
                                <div class="alert alert-warning">
                                    <b>Alasan penolakan:</b> {{ $pesertaTerpilih->alasan_penolakan }}
                                </div>
                            @endif

                            @if ($pesertaTerpilih !== null && $pesertaTerpilih->status_verifikasi !== 'verified')
                                <div class="mb-3">
                                    <label class="form-label" for="alasanPeserta">
                                        Alasan (wajib bila Tidak Layak tetap diberi undangan, opsional saat menolak)
                                    </label>
                                    <textarea id="alasanPeserta" wire:model="alasan" class="form-control" rows="2" placeholder="Tuliskan pertimbangan petugas..."></textarea>
                                </div>
                            @endif

                            <div class="btn-list mt-4">
                                @if ($pesertaTerpilih !== null)
                                    <button type="button" class="btn btn-primary" wire:click="verifikasi({{ $terpilih->id }})" wire:loading.attr="disabled" wire:target="verifikasi" @disabled($pesertaTerpilih->status_verifikasi === 'verified')>
                                        <span wire:loading.remove wire:target="verifikasi">Verifikasi</span>
                                        <span wire:loading wire:target="verifikasi" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Memverifikasi...</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" wire:click="tolak({{ $terpilih->id }})" wire:loading.attr="disabled" wire:target="tolak" @disabled($pesertaTerpilih->status_verifikasi === 'rejected')>
                                        <span wire:loading.remove wire:target="tolak">Tolak</span>
                                        <span wire:loading wire:target="tolak" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Menolak...</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" wire:click="kirimWhatsApp({{ $terpilih->id }})" wire:loading.attr="disabled" wire:target="kirimWhatsApp" @disabled($pesertaTerpilih->status_verifikasi !== 'verified')>
                                        <span wire:loading.remove wire:target="kirimWhatsApp"><i class="ti ti-brand-whatsapp me-1"></i>Kirim WhatsApp</span>
                                        <span wire:loading wire:target="kirimWhatsApp" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Mengirim...</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="cetakUndangan({{ $terpilih->id }})" wire:loading.attr="disabled" wire:target="cetakUndangan" @disabled($pesertaTerpilih->status_verifikasi !== 'verified')>
                                        <span wire:loading.remove wire:target="cetakUndangan"><i class="ti ti-printer me-1"></i>Cetak Undangan</span>
                                        <span wire:loading wire:target="cetakUndangan" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Menyiapkan...</span>
                                    </button>
                                @endif

                                @if (auth()->user()->role === 'admin')
                                    <button type="button" class="btn btn-outline-secondary" wire:click="edit" wire:loading.attr="disabled" wire:target="edit">
                                        <span wire:loading.remove wire:target="edit"><i class="ti ti-pencil me-1"></i>Edit Data</span>
                                        <span wire:loading wire:target="edit" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Membuka...</span>
                                    </button>
                                @endif
                            </div>

                            <p class="text-secondary small mt-3 mb-0">
                                QR khusus rapat dibuat setelah Verified. Kirim/cetak ulang memakai token yang sama dan semua aktivitas dicatat beserta petugas.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop show"></div>
    @endif
</div>
