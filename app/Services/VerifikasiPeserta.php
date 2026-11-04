<?php

namespace App\Services;

use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BR-004: keputusan petugas disimpan, dan Verified menerbitkan tepat satu token QR acak
 * per (rapat, anggota). Cetak ulang memakai token yang sama.
 */
final class VerifikasiPeserta
{
    private const KOLOM = [
        'status_verifikasi',
        'alasan_override',
        'alasan_penolakan',
        'verified_by',
        'verified_at',
        'qr_token_hash',
        'qr_token_ciphertext',
        'qr_dibuat_at',
    ];

    public function verifikasi(Peserta $peserta, User $petugas, ?string $alasan = null): void
    {
        if ($peserta->status_verifikasi === Peserta::VERIFIED && $peserta->qr_token_hash !== null) {
            return;
        }

        DB::transaction(function () use ($peserta, $petugas, $alasan): void {
            $sebelum = $peserta->only(self::KOLOM);

            if ($peserta->qr_token_hash === null) {
                $token = bin2hex(random_bytes(32));
                $peserta->qr_token_hash = hash('sha256', $token);
                $peserta->qr_token_ciphertext = Crypt::encryptString($token);
                $peserta->qr_dibuat_at = now();
            }

            $peserta->status_verifikasi = Peserta::VERIFIED;
            $peserta->alasan_override = $alasan;
            $peserta->alasan_penolakan = null;
            $peserta->verified_by = $petugas->id;
            $peserta->verified_at = now();
            $peserta->save();

            LogAudit::catat('verifikasi', 'peserta', $peserta->id, $sebelum, $peserta->only(self::KOLOM));
        });
    }

    public function tolak(Peserta $peserta, User $petugas, ?string $alasan = null): void
    {
        if ($peserta->status_verifikasi === Peserta::REJECTED) {
            return;
        }

        DB::transaction(function () use ($peserta, $petugas, $alasan): void {
            $sebelum = $peserta->only(self::KOLOM);

            $peserta->status_verifikasi = Peserta::REJECTED;
            $peserta->alasan_override = null;
            $peserta->alasan_penolakan = $alasan;
            $peserta->verified_by = $petugas->id;
            $peserta->verified_at = now();
            $peserta->save();

            LogAudit::catat('tolak', 'peserta', $peserta->id, $sebelum, $peserta->only(self::KOLOM));
        });
    }

    /**
     * Token mentah hanya dibuka saat dibutuhkan (cetak/scan), tidak pernah disimpan plaintext.
     */
    public function token(Peserta $peserta): ?string
    {
        if ($peserta->qr_token_ciphertext === null) {
            return null;
        }

        try {
            return Crypt::decryptString($peserta->qr_token_ciphertext);
        } catch (RuntimeException) {
            return null;
        }
    }
}
