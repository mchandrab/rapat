<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-005: riwayat kirim WhatsApp dan cetak undangan.
 */
class LogUndangan extends Model
{
    public const METODE_WA = 'wa';

    public const METODE_CETAK = 'cetak';

    public const DICETAK = 'dicetak';

    public const DITERIMA_API = 'diterima_api';

    public const TERKIRIM = 'terkirim';

    public const GAGAL = 'gagal';

    protected $table = 'log_undangan';

    protected $fillable = [
        'rapat_id',
        'peserta_id',
        'petugas_user_id',
        'metode',
        'status',
        'waktu_aksi',
        'provider_message_id',
        'detail_error',
    ];

    protected function casts(): array
    {
        return [
            'waktu_aksi' => 'datetime',
        ];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class);
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_user_id');
    }

    public function labelMetode(): string
    {
        return $this->metode === self::METODE_WA ? 'WhatsApp' : 'Cetak';
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::DICETAK => 'Dicetak',
            self::DITERIMA_API => 'Diterima API',
            self::TERKIRIM => 'Terkirim',
            self::GAGAL => 'Gagal',
            default => ucfirst($this->status),
        };
    }
}
