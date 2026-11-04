<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-006/BR-008: kehadiran peserta beserta nominal transport yang berlaku saat itu.
 */
class Presensi extends Model
{
    public const TERSINKRON = 'tersinkron';

    protected $table = 'presensi';

    protected $fillable = [
        'peserta_id',
        'datang_at',
        'pulang_at',
        'dicatat_oleh',
        'foto_path',
        'tte_path',
        'tier_id',
        'nominal_transport_final',
        'kode_resi',
        'status_sinkron',
    ];

    protected $hidden = ['foto_path', 'tte_path'];

    protected function casts(): array
    {
        return [
            'datang_at' => 'datetime',
            'pulang_at' => 'datetime',
            'nominal_transport_final' => 'decimal:2',
        ];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(RapatTransportTier::class, 'tier_id');
    }
}
