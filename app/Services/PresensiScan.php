<?php

namespace App\Services;

use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\TransportPembayaran;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * BR-006: gerbang scan = QR rapat ini, peserta Verified, presensi dibuka (D-021),
 * anggota masih Aktif, belum pernah presensi, dan kas Scanner cukup (D-015).
 *
 * D-023: foto peserta dari kamera perangkat + tanda tangan layar (PNG) wajib,
 * disimpan di disk privat dan tidak pernah dihapus.
 *
 * D-034: Mode Presensi 2x (Datang–Pulang). Kedatangan hanya mencatat dan mengunci
 * nominal transport; uang diserahkan saat pulang oleh Scanner yang mencatat pulang,
 * dipotong dari kas Scanner itu. Pulang dan pembayaran berhasil bersama atau tidak sama sekali.
 */
final class PresensiScan
{
    private const TTE_PREFIX = 'data:image/png;base64,';

    private const TTE_MAKS_BYTE = 2_097_152;

    /**
     * Kedatangan. Mode 1x langsung membayar; Mode 2x hanya mencatat dan mengunci nominal.
     *
     * @return array{ok: bool, pesan: string, peserta: Peserta|null, presensi: Presensi|null, nominal: float}
     */
    public function catat(
        Rapat $rapat,
        string $token,
        User $scanner,
        ?UploadedFile $foto = null,
        ?string $tandaTangan = null,
    ): array {
        if (in_array($rapat->status, [Rapat::SELESAI, Rapat::DIBATALKAN], true)) {
            return $this->gagal("Rapat sudah {$rapat->labelStatus()}; presensi ditutup.");
        }

        if ($rapat->presensi_dibuka_at === null) {
            return $this->gagal('Presensi belum dibuka Admin.');
        }

        $peserta = Peserta::query()
            ->where('rapat_id', $rapat->id)
            ->where('qr_token_hash', hash('sha256', $token))
            ->with('anggota')
            ->first();

        if ($peserta === null) {
            return $this->gagal('QR tidak dikenali untuk rapat ini.');
        }

        if ($peserta->status_verifikasi !== Peserta::VERIFIED) {
            return $this->gagal('Peserta belum Verified; presensi ditolak.');
        }

        if ($peserta->anggota === null || $peserta->anggota->status_anggota !== 'aktif') {
            return $this->gagal('Anggota sudah Nonaktif; presensi ditolak.');
        }

        $mode2x = $rapat->mode_presensi === '2x';

        if (Presensi::query()->where('peserta_id', $peserta->id)->exists()) {
            return $this->gagal($mode2x
                ? 'Peserta sudah tercatat datang. Gunakan tab Pulang untuk mencatat kepulangan.'
                : 'Peserta ini sudah tercatat presensi.');
        }

        if ($foto === null || $tandaTangan === null) {
            return $this->gagal('Foto dan tanda tangan peserta wajib diambil sebelum presensi dicatat.');
        }

        $kas = null;

        if (! $mode2x) {
            [$kas, $galat] = $this->kasSiap($rapat, $scanner);

            if ($galat !== null) {
                return $this->gagal($galat);
            }
        }

        $datang = now();
        $hitung = PenghitungTransport::hitung($rapat, $datang);

        // Pemeriksaan cepat agar bukti tidak ditulis sia-sia; keputusan final tetap di dalam transaksi.
        if ($kas !== null && $hitung['nominal'] > 0 && $kas->sisa() < $hitung['nominal']) {
            return $this->gagal('Kas Anda tidak cukup untuk transport ini. Arahkan anggota ke Scanner lain.');
        }

        $folder = "presensi/{$rapat->id}/{$peserta->id}";
        $fotoPath = $foto->storeAs($folder, 'foto.'.($foto->guessExtension() ?: 'jpg'), 'local');
        $ttePath = $this->simpanTandaTangan($folder, $tandaTangan);

        if ($fotoPath === false || $ttePath === null) {
            $this->hapusBukti([$fotoPath, $ttePath]);

            return $this->gagal('Bukti foto/tanda tangan gagal disimpan; presensi dibatalkan.');
        }

        try {
            $presensi = DB::transaction(function () use ($peserta, $rapat, $scanner, $kas, $hitung, $datang, $fotoPath, $ttePath, $mode2x): Presensi {
                $resi = null;

                // D-034: Mode 2x belum mengeluarkan uang, jadi kas tidak disentuh saat datang.
                if (! $mode2x) {
                    // Kunci baris kas agar dua scan bersamaan tidak membuat saldo minus.
                    $kasTerkunci = KasScanner::query()->whereKey($kas->id)->lockForUpdate()->first();

                    if ($kasTerkunci === null || $kasTerkunci->status !== KasScanner::AKTIF) {
                        throw new LogicException('Kas Anda sudah ditutup; presensi tidak dapat dibayar.');
                    }

                    if ($hitung['nominal'] > 0 && $kasTerkunci->sisa() < $hitung['nominal']) {
                        throw new LogicException('Kas Anda tidak cukup untuk transport ini. Arahkan anggota ke Scanner lain.');
                    }

                    $resi = $hitung['nominal'] > 0 ? $this->resi() : null;
                }

                $presensi = Presensi::create([
                    'peserta_id' => $peserta->id,
                    'datang_at' => $datang,
                    'dicatat_oleh' => $scanner->id,
                    'foto_path' => $fotoPath,
                    'tte_path' => $ttePath,
                    'tier_id' => $hitung['tier']?->id,
                    'nominal_transport_final' => $hitung['nominal'],
                    'kode_resi' => $resi,
                    'status_sinkron' => Presensi::TERSINKRON,
                ]);

                // D-022: nominal nol berarti hangus, bukan utang; tidak ada baris pembayaran.
                if ($resi !== null) {
                    TransportPembayaran::create([
                        'peserta_id' => $peserta->id,
                        'kas_scanner_id' => $kas->id,
                        'dibayar_oleh' => $scanner->id,
                        'nominal' => $hitung['nominal'],
                        'dibayar_pada' => $datang,
                        'kode_resi' => $resi,
                    ]);
                }

                LogAudit::catat('presensi', 'presensi', $presensi->id, null, [
                    'rapat_id' => $rapat->id,
                    'peserta_id' => $peserta->id,
                    'nominal' => $hitung['nominal'],
                    'scanner_user_id' => $scanner->id,
                ]);

                return $presensi;
            });
        } catch (LogicException $e) {
            $this->hapusBukti([$fotoPath, $ttePath]);

            return $this->gagal($e->getMessage());
        } catch (UniqueConstraintViolationException) {
            $this->hapusBukti([$fotoPath, $ttePath]);

            return $this->gagal($mode2x
                ? 'Peserta sudah tercatat datang. Gunakan tab Pulang untuk mencatat kepulangan.'
                : 'Peserta ini sudah tercatat presensi.');
        }

        $nama = $peserta->anggota->nama;

        return [
            'ok' => true,
            'pesan' => $this->pesanDatang($nama, (float) $hitung['nominal'], $mode2x),
            'peserta' => $peserta,
            'presensi' => $presensi,
            'nominal' => $hitung['nominal'],
        ];
    }

    /**
     * D-034: kepulangan Mode 2x. Membayar nominal yang sudah dikunci saat datang,
     * memakai kas Scanner yang mencatat pulang (boleh Scanner berbeda dari pencatat datang).
     *
     * @return array{ok: bool, pesan: string, peserta: Peserta|null, presensi: Presensi|null, nominal: float}
     */
    public function pulang(Rapat $rapat, string $token, User $scanner): array
    {
        if (in_array($rapat->status, [Rapat::SELESAI, Rapat::DIBATALKAN], true)) {
            return $this->gagal("Rapat sudah {$rapat->labelStatus()}; presensi ditutup.");
        }

        if ($rapat->presensi_dibuka_at === null) {
            return $this->gagal('Presensi belum dibuka Admin.');
        }

        if ($rapat->mode_presensi !== '2x') {
            return $this->gagal('Rapat memakai Mode Presensi 1x; tidak ada presensi pulang.');
        }

        $peserta = Peserta::query()
            ->where('rapat_id', $rapat->id)
            ->where('qr_token_hash', hash('sha256', $token))
            ->with('anggota')
            ->first();

        if ($peserta === null) {
            return $this->gagal('QR tidak dikenali untuk rapat ini.');
        }

        if ($peserta->status_verifikasi !== Peserta::VERIFIED) {
            return $this->gagal('Peserta belum Verified; presensi ditolak.');
        }

        if ($peserta->anggota === null || $peserta->anggota->status_anggota !== 'aktif') {
            return $this->gagal('Anggota sudah Nonaktif; presensi ditolak.');
        }

        $presensi = Presensi::query()->where('peserta_id', $peserta->id)->first();

        if ($presensi === null) {
            return $this->gagal('Peserta belum tercatat datang; catat kedatangan lebih dulu.');
        }

        if ($presensi->pulang_at !== null) {
            return $this->gagal('Peserta ini sudah tercatat pulang.');
        }

        [$kas, $galat] = $this->kasSiap($rapat, $scanner);

        if ($galat !== null) {
            return $this->gagal($galat);
        }

        $nominal = (float) $presensi->nominal_transport_final;

        if ($nominal > 0 && $kas->sisa() < $nominal) {
            return $this->gagal('Kas Anda tidak cukup untuk transport ini. Arahkan anggota ke Scanner lain.');
        }

        try {
            DB::transaction(function () use ($presensi, $peserta, $rapat, $scanner, $kas, $nominal): void {
                // Kunci baris presensi agar dua Scanner tidak membayar peserta yang sama.
                $presensiTerkunci = Presensi::query()->whereKey($presensi->id)->lockForUpdate()->first();

                if ($presensiTerkunci === null || $presensiTerkunci->pulang_at !== null) {
                    throw new LogicException('Peserta ini sudah tercatat pulang.');
                }

                $kasTerkunci = KasScanner::query()->whereKey($kas->id)->lockForUpdate()->first();

                if ($kasTerkunci === null || $kasTerkunci->status !== KasScanner::AKTIF) {
                    throw new LogicException('Kas Anda sudah ditutup; pembayaran tidak dapat dilakukan.');
                }

                if ($nominal > 0 && $kasTerkunci->sisa() < $nominal) {
                    throw new LogicException('Kas Anda tidak cukup untuk transport ini. Arahkan anggota ke Scanner lain.');
                }

                $pulang = now();
                $resi = $nominal > 0 ? $this->resi() : null;

                $presensiTerkunci->update([
                    'pulang_at' => $pulang,
                    'kode_resi' => $resi,
                ]);

                if ($resi !== null) {
                    TransportPembayaran::create([
                        'peserta_id' => $peserta->id,
                        'kas_scanner_id' => $kasTerkunci->id,
                        'dibayar_oleh' => $scanner->id,
                        'nominal' => $nominal,
                        'dibayar_pada' => $pulang,
                        'kode_resi' => $resi,
                    ]);
                }

                LogAudit::catat('pulang', 'presensi', $presensiTerkunci->id, null, [
                    'rapat_id' => $rapat->id,
                    'peserta_id' => $peserta->id,
                    'nominal' => $nominal,
                    'scanner_user_id' => $scanner->id,
                ]);
            });
        } catch (LogicException $e) {
            return $this->gagal($e->getMessage());
        } catch (UniqueConstraintViolationException) {
            return $this->gagal('Peserta ini sudah tercatat pulang.');
        }

        $nama = $peserta->anggota->nama;

        return [
            'ok' => true,
            'pesan' => $nominal > 0
                ? "Kepulangan {$nama} dicatat. Transport Rp".number_format($nominal, 0, ',', '.').' diserahkan dari kas Anda.'
                : "Kepulangan {$nama} dicatat. Transport hangus (Rp0); tidak ada uang yang diserahkan.",
            'peserta' => $peserta,
            'presensi' => $presensi->fresh(),
            'nominal' => $nominal,
        ];
    }

    /**
     * @return array{0: KasScanner|null, 1: string|null}
     */
    private function kasSiap(Rapat $rapat, User $scanner): array
    {
        $kas = KasScanner::query()
            ->where('rapat_id', $rapat->id)
            ->where('scanner_user_id', $scanner->id)
            ->first();

        if ($kas === null) {
            return [null, 'Kas Anda belum dialokasikan Keuangan; pembayaran transport tidak dapat dilakukan.'];
        }

        if ($kas->status === KasScanner::MENUNGGU) {
            return [null, 'Konfirmasi "Saya sudah menerima kas" lebih dulu sebelum memindai.'];
        }

        if ($kas->status !== KasScanner::AKTIF) {
            return [null, 'Kas Anda sudah ditutup; presensi tidak dapat dibayar.'];
        }

        return [$kas, null];
    }

    private function pesanDatang(string $nama, float $nominal, bool $mode2x): string
    {
        if ($mode2x) {
            return $nominal > 0
                ? "Kedatangan {$nama} dicatat. Transport Rp".number_format($nominal, 0, ',', '.').' akan diserahkan saat pulang.'
                : "Kedatangan {$nama} dicatat. Transport hangus (Rp0).";
        }

        return $nominal > 0
            ? "Presensi {$nama} dicatat. Transport Rp".number_format($nominal, 0, ',', '.').' diserahkan dari kas Anda.'
            : "Presensi {$nama} dicatat. Transport hangus (Rp0); tidak ada uang yang diserahkan.";
    }

    /**
     * Tanda tangan datang sebagai data URL PNG dari canvas; hanya PNG wajar yang diterima.
     */
    private function simpanTandaTangan(string $folder, string $data): ?string
    {
        if (! str_starts_with($data, self::TTE_PREFIX)) {
            return null;
        }

        $biner = base64_decode(substr($data, strlen(self::TTE_PREFIX)), true);

        if ($biner === false || strlen($biner) > self::TTE_MAKS_BYTE || ! str_starts_with($biner, "\x89PNG")) {
            return null;
        }

        $path = $folder.'/tte.png';

        return Storage::disk('local')->put($path, $biner) ? $path : null;
    }

    /**
     * @param  array<int, string|false|null>  $paths
     */
    private function hapusBukti(array $paths): void
    {
        // shortcut: file ditulis sebelum transaksi; bila proses mati di tengah, sisa file tanpa baris DB (tidak dirujuk).
        Storage::disk('local')->delete(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * @return array{ok: false, pesan: string, peserta: null, presensi: null, nominal: float}
     */
    private function gagal(string $pesan): array
    {
        return ['ok' => false, 'pesan' => $pesan, 'peserta' => null, 'presensi' => null, 'nominal' => 0.0];
    }

    private function resi(): string
    {
        do {
            $kode = 'TRX'.now()->format('ymd').Str::upper(Str::random(6));
        } while (TransportPembayaran::query()->where('kode_resi', $kode)->exists());

        return $kode;
    }
}
