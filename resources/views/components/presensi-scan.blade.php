<?php

use App\Models\KasScanner;
use App\Models\Rapat;
use App\Services\PresensiScan;
use App\Support\Demo;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $token = '';

    public $foto = null;

    public string $tandaTangan = '';

    public string $pesan = '';

    public bool $berhasil = false;

    public ?string $resiTerakhir = null;

    /** D-034: hanya dipakai pada Mode Presensi 2x. */
    public string $mode = 'datang';

    private function aturanDatang(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'foto' => ['required', 'image', 'max:5120'],
            'tandaTangan' => ['required', 'string'],
        ];
    }

    private function aturanPulang(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'token.required' => 'Tempel atau ketik token QR peserta lebih dulu.',
            'foto.required' => 'Foto peserta wajib diambil dari kamera.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.max' => 'Foto maksimal 5 MB.',
            'tandaTangan.required' => 'Tanda tangan peserta wajib digambar di layar.',
        ];
    }

    public function scan(): void
    {
        abort_unless(auth()->user()->role === 'scanner', 403);

        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            $this->pesan = 'Belum ada rapat yang disiapkan Admin.';
            $this->berhasil = false;

            return;
        }

        $pulang = $rapat->mode_presensi === '2x' && $this->mode === 'pulang';

        $this->validate($pulang ? $this->aturanPulang() : $this->aturanDatang());

        if (! $pulang && ! str_starts_with($this->tandaTangan, 'data:image/png;base64,')) {
            $this->addError('tandaTangan', 'Tanda tangan belum digambar.');

            return;
        }

        $hasil = $pulang
            ? (new PresensiScan)->pulang($rapat, trim($this->token), auth()->user())
            : (new PresensiScan)->catat($rapat, trim($this->token), auth()->user(), $this->foto, $this->tandaTangan);

        $this->pesan = $hasil['pesan'];
        $this->berhasil = $hasil['ok'];
        $this->resiTerakhir = $hasil['presensi']?->kode_resi;

        if ($hasil['ok']) {
            $this->reset('foto', 'tandaTangan', 'token');
            $this->dispatch('presensi-dicatat');
        }
    }

    public function with(): array
    {
        $rapat = Rapat::query()->latest('id')->first();
        $kas = $rapat === null ? null : $rapat->kas()->where('scanner_user_id', auth()->id())->first();

        return [
            'rapat' => $rapat,
            'kas' => $kas,
            'pembayaran' => $kas === null
                ? collect()
                : $kas->pembayaran()->with('peserta.anggota')->latest('id')->get(),
        ];
    }
};
?>

<div class="row row-cards">
    <div class="col-12">
        <div id="statusKoneksi" class="alert alert-danger d-none mb-0" role="status" wire:ignore>
            <i class="ti ti-wifi-off me-1"></i>
            <b>Koneksi terputus.</b> Hentikan pemindaian dan arahkan peserta ke Scanner lain yang online.
            Tidak ada presensi atau pembayaran yang boleh dicatat manual saat offline (D-032).
        </div>
    </div>

    <div class="col-lg-4 order-2 order-lg-1">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Saldo kas saya</h3></div>
            <div class="card-body">
                <div class="text-secondary small">Kas awal (satu kali per rapat)</div>
                <div class="h2">{{ Demo::rupiah($kas === null ? 0 : (float) $kas->kas_awal) }}</div>
                <div class="text-secondary small">Sisa tersedia</div>
                <div class="h1 mb-3">{{ Demo::rupiah($kas === null ? 0 : $kas->sisa()) }}</div>

                @if ($kas === null)
                    <div class="alert alert-secondary mb-0">
                        <i class="ti ti-info-circle me-1"></i>Kas awal belum dialokasikan Keuangan. Kas tidak dapat ditambah atau
                        dipindahkan dari Scanner lain.
                    </div>
                @else
                    <div class="datagrid mb-3">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Status kas</div>
                            <div class="datagrid-content">{{ $kas->labelStatus() }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Diserahkan</div>
                            <div class="datagrid-content">{{ $kas->diserahkan_pada?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Rapat</div>
                            <div class="datagrid-content">{{ $kas->rapat?->judul ?? '—' }}</div>
                        </div>
                    </div>
                    @if ($kas->status === KasScanner::MENUNGGU)
                        <div class="alert alert-warning">
                            <i class="ti ti-cash me-1"></i>
                            Uang kas sudah diserahkan Keuangan. Hitung dan pastikan nominalnya, lalu konfirmasi sebelum memindai.
                            <form method="POST" action="{{ route('kas.terima') }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100">
                                    Saya sudah menerima kas {{ Demo::rupiah((float) $kas->kas_awal) }}
                                </button>
                            </form>
                        </div>
                    @endif
                    @if ($rapat !== null && $kas->status === KasScanner::AKTIF && $kas->sisa() < $rapat->batasPeringatan())
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-triangle me-1"></i>Kas menipis: sisa {{ Demo::rupiah($kas->sisa()) }},
                            cukup untuk {{ (int) floor($kas->sisa() / max((float) $rapat->transport_dasar, 1)) }} peserta lagi.
                            Arahkan anggota ke Scanner lain bila habis.
                        </div>
                    @endif
                    <div class="alert alert-info mb-0">
                        <i class="ti ti-info-circle me-1"></i>Bila kas tidak cukup, presensi yang memerlukan pembayaran ditolak.
                        Arahkan anggota ke Scanner lain.
                    </div>
                @endif
            </div>
            <div class="card-footer text-secondary small">
                Kas yang sudah ditutup Keuangan tidak dapat dipakai memindai. Penggantian Scanner memakai alokasi baru.
            </div>
        </div>
    </div>

    <div class="col-lg-8 order-1 order-lg-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pindai QR Presensi</h3>
                @if ($rapat?->presensi_dibuka_at !== null)
                    <div class="card-actions">
                        <span class="badge bg-green-lt text-green">Presensi dibuka</span>
                    </div>
                @endif
            </div>
            <div class="card-body">
                @if ($rapat === null)
                    <div class="alert alert-warning mb-0">Belum ada rapat. Admin perlu menyiapkan rapat lebih dulu.</div>
                @elseif (! $rapat->presensiDibuka())
                    <div class="alert alert-warning mb-0">
                        <i class="ti ti-lock me-1"></i>Presensi belum dibuka Admin
                        @if ($rapat->presensi_dibuka_at !== null)
                            atau rapat sudah {{ $rapat->labelStatus() }}
                        @endif
                        . Pemindaian QR belum dapat dilakukan.
                    </div>
                @else
                    @if ($rapat->mode_presensi === '2x')
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Jenis pemindaian">
                            <button type="button" class="btn {{ $mode === 'datang' ? 'btn-primary' : 'btn-outline-primary' }}"
                                    wire:click="$set('mode', 'datang')">
                                <i class="ti ti-login me-1"></i>1. Datang
                            </button>
                            <button type="button" class="btn {{ $mode === 'pulang' ? 'btn-primary' : 'btn-outline-primary' }}"
                                    wire:click="$set('mode', 'pulang')">
                                <i class="ti ti-logout me-1"></i>2. Pulang &amp; Bayar
                            </button>
                        </div>
                    @endif

                    <form wire:submit="scan" id="formPresensi" class="row g-3">
                        <div class="col-12" wire:ignore>
                            <div class="erat-kamera-aksi d-flex flex-wrap gap-2 align-items-center">
                                <button type="button" id="kameraMulai" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-camera me-1"></i>Nyalakan kamera
                                </button>
                                <button type="button" id="kameraStop" class="btn btn-outline-secondary btn-sm d-none">
                                    <i class="ti ti-camera-off me-1"></i>Matikan kamera
                                </button>
                                <span id="kameraStatus" class="text-secondary small" role="status"></span>
                            </div>
                            <div id="qrReader" class="erat-qr-reader rounded overflow-hidden mt-2"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="tokenPresensi">Token QR peserta</label>
                            <input id="tokenPresensi" wire:model="token" class="form-control @error('token') is-invalid @enderror"
                                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                                   placeholder="Tempel token dari QR undangan peserta">
                            @error('token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if ($rapat->mode_presensi === '2x' && $mode === 'pulang')
                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    <i class="ti ti-cash me-1"></i>Uang transport diambil dari kas Anda saat pulang.
                                    Foto dan tanda tangan hanya diambil saat datang.
                                </div>
                            </div>
                        @else

                        <div class="col-md-6">
                            <label class="form-label" for="fotoPeserta">Foto peserta</label>
                            <input id="fotoPeserta" type="file" wire:model="foto" accept="image/*" capture="environment"
                                   class="form-control @error('foto') is-invalid @enderror">
                            @error('foto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Diambil langsung dari kamera perangkat. Maksimal 5 MB.</div>
                            <div wire:loading wire:target="foto" class="text-secondary small" role="status">Mengunggah foto...</div>
                            @if ($foto && $foto->isPreviewable())
                                <img src="{{ $foto->temporaryUrl() }}" alt="Pratinjau foto peserta"
                                     class="img-fluid rounded mt-2" style="max-height:160px">
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="tteCanvas">Tanda tangan peserta</label>
                            <canvas id="tteCanvas" width="600" height="200"
                                    class="form-control erat-tte-canvas @error('tandaTangan') is-invalid @enderror"
                                    aria-label="Area tanda tangan"></canvas>
                            @error('tandaTangan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            <button type="button" id="tteHapus" class="btn btn-sm btn-outline-secondary mt-2">Hapus tanda tangan</button>
                        </div>
                        @endif

                        <div class="col-12 erat-scan-actions">
                            @if ($pesan !== '')
                                <div class="alert {{ $berhasil ? 'alert-success' : 'alert-danger' }} mb-2" role="status">
                                    {{ $pesan }}
                                    @if ($berhasil && $resiTerakhir)
                                        <a href="{{ route('resi.show', $resiTerakhir) }}" target="_blank" rel="noopener"
                                           class="alert-link ms-1">Buka resi</a>
                                    @endif
                                </div>
                            @endif
                            <button type="submit" id="tombolPresensi" class="btn btn-primary" wire:loading.attr="disabled" wire:target="scan,foto">
                                <span wire:loading.remove wire:target="scan">
                                    @if ($rapat->mode_presensi === '2x' && $mode === 'pulang')
                                        <i class="ti ti-logout me-1"></i>Catat Pulang &amp; Bayar
                                    @else
                                        <i class="ti ti-scan me-1"></i>Catat Presensi
                                    @endif
                                </span>
                                <span wire:loading wire:target="scan" role="status"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Memproses...</span>
                            </button>
                        </div>
                    </form>

                    <div class="text-secondary small mt-3">
                        @if ($rapat->mode_presensi === '2x')
                            Nominal transport dikunci saat datang; uangnya diserahkan saat pulang oleh Scanner yang mencatat pulang.
                        @else
                            Nominal transport dihitung server dari jam datang terhadap jam mulai acara; Scanner tidak dapat mengubahnya.
                        @endif
                        Foto dan tanda tangan disimpan privat dan tidak pernah dihapus.
                    </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Pembayaran yang saya serahkan</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead><tr><th class="d-none d-md-table-cell">Waktu</th><th>Peserta</th><th class="text-end">Nominal</th><th>Resi</th></tr></thead>
                    <tbody>
                    @forelse ($pembayaran as $bayar)
                        <tr wire:key="bayar-{{ $bayar->id }}">
                            <td class="d-none d-md-table-cell text-nowrap text-secondary">{{ $bayar->dibayar_pada?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                <div>{{ $bayar->peserta?->anggota?->nama ?? '—' }}</div>
                                <div class="text-secondary small">{{ $bayar->peserta?->anggota?->nbaTampil() ?? '—' }}</div>
                            </td>
                            <td class="text-end text-nowrap">{{ Demo::rupiah((float) $bayar->nominal) }}</td>
                            <td class="text-secondary">
                                <a href="{{ route('resi.show', $bayar->kode_resi) }}" target="_blank" rel="noopener">{{ $bayar->kode_resi }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada pembayaran.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-secondary small">
                Presensi bertransport Rp0 (hangus) tetap tercatat, tetapi tidak membuat baris pembayaran dan tidak mengurangi kas.
            </div>
        </div>
    </div>
</div>

@assets
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
@endassets

@script
<script>
    const canvas = document.getElementById('tteCanvas');
    if (canvas && ! canvas.dataset.siap) {
        canvas.dataset.siap = '1';

        const ctx = canvas.getContext('2d');
        const hapus = document.getElementById('tteHapus');
        let menggambar = false;

        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111';

        const posisi = (event) => {
            const kotak = canvas.getBoundingClientRect();

            return {
                x: (event.clientX - kotak.left) * (canvas.width / kotak.width),
                y: (event.clientY - kotak.top) * (canvas.height / kotak.height),
            };
        };

        canvas.addEventListener('pointerdown', (event) => {
            menggambar = true;
            canvas.setPointerCapture(event.pointerId);
            const titik = posisi(event);
            ctx.beginPath();
            ctx.moveTo(titik.x, titik.y);
        });

        canvas.addEventListener('pointermove', (event) => {
            if (! menggambar) {
                return;
            }

            const titik = posisi(event);
            ctx.lineTo(titik.x, titik.y);
            ctx.stroke();
        });

        const selesai = () => {
            if (! menggambar) {
                return;
            }

            menggambar = false;
            $wire.set('tandaTangan', canvas.toDataURL('image/png'));
        };

        canvas.addEventListener('pointerup', selesai);
        canvas.addEventListener('pointerleave', selesai);

        hapus?.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            $wire.set('tandaTangan', '');
        });

        $wire.on('presensi-dicatat', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        });
    }

    const kotakQr = document.getElementById('qrReader');
    if (kotakQr && ! kotakQr.dataset.siap) {
        kotakQr.dataset.siap = '1';

        const mulaiBtn = document.getElementById('kameraMulai');
        const stopBtn = document.getElementById('kameraStop');
        const status = document.getElementById('kameraStatus');
        const pemindai = new Html5Qrcode('qrReader');
        let aktif = false;

        const tulis = (teks) => {
            if (status) {
                status.textContent = teks;
            }
        };

        const henti = () => {
            if (! aktif) {
                return;
            }

            aktif = false;
            mulaiBtn?.classList.remove('d-none');
            stopBtn?.classList.add('d-none');

            pemindai.stop().then(() => pemindai.clear()).catch(() => {});
        };

        const mulai = async () => {
            if (aktif) {
                return;
            }

            tulis('Menyiapkan kamera...');

            try {
                await pemindai.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: 250 },
                    (teks) => {
                        $wire.set('token', teks);
                        tulis('QR terbaca. Kamera dimatikan.');
                        henti();
                    },
                    () => {},
                );

                aktif = true;
                mulaiBtn?.classList.add('d-none');
                stopBtn?.classList.remove('d-none');
                tulis('Arahkan kamera ke QR undangan peserta.');
            } catch (galat) {
                tulis('Kamera tidak dapat dinyalakan. Isi token manual atau periksa izin kamera.');
            }
        };

        mulaiBtn?.addEventListener('click', mulai);
        stopBtn?.addEventListener('click', henti);
        document.addEventListener('livewire:navigating', henti);

        if (! window.isSecureContext) {
            mulaiBtn?.classList.add('d-none');
            tulis('Kamera hanya aktif lewat HTTPS atau localhost. Isi token manual.');
        } else if (navigator.permissions) {
            navigator.permissions.query({ name: 'camera' })
                .then((izin) => {
                    if (izin.state === 'granted') {
                        mulai();
                    }
                })
                .catch(() => {});
        }
    }

    // D-032: koneksi putus = pemindaian berhenti, tanpa antrean offline.
    const perbaruiKoneksi = () => {
        const putus = ! navigator.onLine;

        document.getElementById('statusKoneksi')?.classList.toggle('d-none', ! putus);

        const tombol = document.getElementById('tombolPresensi');

        if (tombol) {
            tombol.disabled = putus;
        }
    };

    window.addEventListener('online', perbaruiKoneksi);
    window.addEventListener('offline', perbaruiKoneksi);
    document.addEventListener('livewire:navigated', perbaruiKoneksi);
    perbaruiKoneksi();
</script>
@endscript
