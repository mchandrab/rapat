<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BR-SHU-LOG-01: setiap percobaan cek SHU publik dicatat tanpa nominal SHU.
 * Retensi 14 hari lewat `cek-shu:purge`; tabel ini terpisah dari `shu` dan `log_audit`.
 */
class LogCekShu extends Model
{
    public const DITEMUKAN = 'ditemukan';

    public const TIDAK_DITEMUKAN = 'tidak_ditemukan';

    public const DITOLAK_CAPTCHA = 'ditolak_captcha';

    public const DIBATASI = 'dibatasi';

    public const TIDAK_VALID = 'tidak_valid';

    public $timestamps = false;

    protected $table = 'log_cek_shu';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function catat(?string $nba, ?int $tahun, ?string $ip, string $hasil): self
    {
        return static::create([
            'nba_dicari' => $nba,
            'tahun_buku' => $tahun,
            'ip_address' => $ip,
            'hasil' => $hasil,
            'created_at' => now(),
        ]);
    }
}
