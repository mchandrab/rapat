<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-004: status calon peserta per rapat, termasuk snapshot penilaian.
 */
class Peserta extends Model
{
    public const LAYAK = 'layak';

    public const TIDAK_LAYAK = 'tidak_layak';

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const REJECTED = 'rejected';

    protected $table = 'peserta';

    protected $fillable = [
        'rapat_id',
        'anggota_id',
        'hasil_kelayakan',
        'status_verifikasi',
        'alasan_override',
        'alasan_penolakan',
        'verified_by',
        'verified_at',
        'snapshot_simpanan_wajib',
        'snapshot_tgl_ja',
        'snapshot_minimum_proporsional',
        'snapshot_kolektibilitas',
        'snapshot_pinjaman_lancar',
        'snapshot_konfigurasi',
        'qr_token_hash',
        'qr_token_ciphertext',
        'qr_dibuat_at',
    ];

    protected $hidden = ['qr_token_hash', 'qr_token_ciphertext'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'qr_dibuat_at' => 'datetime',
            'snapshot_tgl_ja' => 'date',
            'snapshot_simpanan_wajib' => 'decimal:2',
            'snapshot_minimum_proporsional' => 'decimal:2',
            'snapshot_kolektibilitas' => 'integer',
            'snapshot_pinjaman_lancar' => 'boolean',
            'snapshot_konfigurasi' => 'array',
        ];
    }

    public function rapat(): BelongsTo
    {
        return $this->belongsTo(Rapat::class);
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Anggota::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function logUndangan(): HasMany
    {
        return $this->hasMany(LogUndangan::class);
    }

    public function labelHasil(): string
    {
        return $this->hasil_kelayakan === self::LAYAK ? 'Layak' : 'Tidak Layak';
    }

    public function labelStatus(): string
    {
        return match ($this->status_verifikasi) {
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
            default => 'Pending',
        };
    }
}
