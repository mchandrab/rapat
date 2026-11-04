<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-009 / D-014: kas terikat pada akun Scanner, satu alokasi awal per rapat.
 */
class KasScanner extends Model
{
    public const MENUNGGU = 'menunggu';

    public const AKTIF = 'aktif';

    public const DITUTUP = 'ditutup';

    protected $table = 'kas_scanner';

    protected $fillable = [
        'rapat_id',
        'scanner_user_id',
        'kas_awal',
        'diserahkan_oleh',
        'diserahkan_pada',
        'diterima_pada',
        'sisa_fisik',
        'catatan_selisih',
        'status',
        'ditutup_oleh',
        'ditutup_pada',
    ];

    protected function casts(): array
    {
        return [
            'kas_awal' => 'decimal:2',
            'sisa_fisik' => 'decimal:2',
            'diserahkan_pada' => 'datetime',
            'diterima_pada' => 'datetime',
            'ditutup_pada' => 'datetime',
        ];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanner_user_id');
    }

    public function penyerah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diserahkan_oleh');
    }

    public function penutup(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(TransportPembayaran::class);
    }

    public function terbayar(): float
    {
        return (float) $this->pembayaran()->sum('nominal');
    }

    /**
     * BR-009: saldo tersedia = kas awal − pembayaran sukses.
     * D-034: Mode 2x tidak memesan uang saat datang; kas baru berkurang saat pulang dibayar.
     */
    public function sisa(): float
    {
        return (float) $this->kas_awal - $this->terbayar();
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::MENUNGGU => 'Menunggu',
            self::DITUTUP => 'Ditutup',
            default => 'Aktif',
        };
    }
}
