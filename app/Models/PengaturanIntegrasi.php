<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-INT-01 / D-019: pengaturan integrasi dikelola Admin dari aplikasi.
 * Secret memakai encrypted cast dan tidak pernah ditampilkan kembali.
 */
class PengaturanIntegrasi extends Model
{
    public const CAPTCHA = 'captcha';

    public const FONNTE = 'fonnte';

    public const DIMINTA = 'diminta';

    public const DITERIMA_API = 'diterima_api';

    public const TERKIRIM = 'terkirim';

    public const GAGAL = 'gagal';

    protected $table = 'pengaturan_integrasi';

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'secret' => 'encrypted',
            'status_uji_pada' => 'datetime',
        ];
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /** Dicek tanpa dekripsi agar halaman Admin tidak membuka secret. */
    public function terkonfigurasi(): bool
    {
        return $this->getRawOriginal('secret') !== null;
    }

    public function siap(): bool
    {
        return $this->aktif && $this->terkonfigurasi();
    }

    public function labelStatusUji(): string
    {
        return match ($this->status_uji) {
            self::DIMINTA => 'Diminta',
            self::DITERIMA_API => 'Diterima API',
            self::TERKIRIM => 'Terkirim',
            self::GAGAL => 'Gagal',
            default => 'Belum pernah diuji',
        };
    }
}
