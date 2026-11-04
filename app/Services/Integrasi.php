<?php

namespace App\Services;

use App\Models\LogAudit;
use App\Models\PengaturanIntegrasi;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BR-INT-01 / D-019: satu pintu baca/tulis pengaturan integrasi. Secret hanya
 * didekripsi saat benar-benar dipakai memanggil vendor, tidak untuk rendering.
 */
final class Integrasi
{
    public function pengaturan(string $layanan): ?PengaturanIntegrasi
    {
        return PengaturanIntegrasi::query()->where('layanan', $layanan)->first();
    }

    /**
     * Secret kosong berarti mempertahankan secret lama; penghapusan hanya lewat nonaktifkan().
     */
    public function simpan(string $layanan, array $data, ?string $secret, User $admin): PengaturanIntegrasi
    {
        $row = $this->pengaturan($layanan) ?? new PengaturanIntegrasi(['layanan' => $layanan]);

        $row->fill($data);

        if ($secret !== null && $secret !== '') {
            $row->secret = $secret;
            $row->secret_versi = (int) $row->secret_versi + 1;
        }

        $row->diubah_oleh = $admin->id;
        $row->save();

        LogAudit::catat('integrasi.simpan', 'pengaturan_integrasi', $row->id, null, [
            'layanan' => $layanan,
            'aktif' => $row->aktif,
            'secret_versi' => (int) $row->secret_versi,
        ]);

        return $row;
    }

    /** Hapus secret adalah aksi eksplisit: nonaktifkan sekaligus buang credential. */
    public function nonaktifkan(string $layanan, User $admin): PengaturanIntegrasi
    {
        $row = $this->pengaturan($layanan) ?? new PengaturanIntegrasi(['layanan' => $layanan]);

        $row->forceFill([
            'aktif' => false,
            'secret' => null,
            'secret_versi' => (int) $row->secret_versi + 1,
            'diubah_oleh' => $admin->id,
        ])->save();

        LogAudit::catat('integrasi.nonaktifkan', 'pengaturan_integrasi', $row->id, null, [
            'layanan' => $layanan,
            'secret_versi' => (int) $row->secret_versi,
        ]);

        return $row;
    }

    public function catatUji(PengaturanIntegrasi $row, string $status, string $pesan): void
    {
        $row->forceFill([
            'status_uji' => $status,
            'status_uji_pesan' => mb_substr($pesan, 0, 500),
            'status_uji_pada' => now(),
        ])->save();
    }

    /**
     * Cloudflare Turnstile. Gagal-tertutup: konfigurasi tidak siap, jaringan gagal,
     * atau vendor menolak semuanya dianggap tidak lolos.
     */
    public function verifikasiCaptcha(string $token, ?string $ip = null): bool
    {
        $row = $this->pengaturan(PengaturanIntegrasi::CAPTCHA);

        if ($row === null || ! $row->siap() || $token === '') {
            return false;
        }

        try {
            $respon = Http::asForm()->timeout(10)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                array_filter([
                    'secret' => $row->secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]),
            );
        } catch (Throwable) {
            return false;
        }

        return $respon->ok() && $respon->json('success') === true;
    }

    /**
     * Fonnte: status "terkirim" hanya dipakai bila vendor memberi bukti; respons
     * yang sekadar diterima API dicatat sebagai diterima_api.
     *
     * @return array{status: string, pesan: string, id: ?string}
     */
    public function kirimWhatsapp(string $nomor, string $pesan): array
    {
        $row = $this->pengaturan(PengaturanIntegrasi::FONNTE);

        if ($row === null || ! $row->siap()) {
            return [
                'status' => PengaturanIntegrasi::GAGAL,
                'pesan' => 'Integrasi Fonnte belum aktif atau token belum diisi.',
                'id' => null,
            ];
        }

        try {
            $respon = Http::withHeaders(['Authorization' => $row->secret])
                ->asForm()
                ->timeout(15)
                ->post('https://api.fonnte.com/send', [
                    'target' => $nomor,
                    'message' => $pesan,
                ]);
        } catch (Throwable) {
            return [
                'status' => PengaturanIntegrasi::GAGAL,
                'pesan' => 'Gagal menghubungi Fonnte.',
                'id' => null,
            ];
        }

        $json = (array) $respon->json();
        $id = isset($json['id'][0]) ? (string) $json['id'][0] : null;

        if ($respon->ok() && ($json['status'] ?? false) === true) {
            return [
                'status' => PengaturanIntegrasi::DITERIMA_API,
                'pesan' => (string) ($json['detail'] ?? 'Permintaan diterima API Fonnte.'),
                'id' => $id,
            ];
        }

        return [
            'status' => PengaturanIntegrasi::GAGAL,
            'pesan' => (string) ($json['reason'] ?? $json['detail'] ?? 'Fonnte menolak pengiriman.'),
            'id' => $id,
        ];
    }
}
