<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-009: uang transport yang benar-benar diserahkan Scanner ke peserta.
 * Hanya dibuat bila nominal > 0 (D-022).
 */
class TransportPembayaran extends Model
{
    protected $table = 'transport_pembayaran';

    protected $fillable = [
        'peserta_id',
        'kas_scanner_id',
        'dibayar_oleh',
        'nominal',
        'dibayar_pada',
        'kode_resi',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'dibayar_pada' => 'datetime',
        ];
    }

    public function peserta(): BelongsTo
    {
        return $this->belongsTo(Peserta::class);
    }

    public function kas(): BelongsTo
    {
        return $this->belongsTo(KasScanner::class, 'kas_scanner_id');
    }

    public function pembayar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibayar_oleh');
    }
}
