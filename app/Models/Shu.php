<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-011: SHU satu anggota untuk satu tahun buku; total = jasa saham + jasa pinjaman.
 */
class Shu extends Model
{
    protected $table = 'shu';

    protected $fillable = [
        'anggota_id',
        'tahun_buku',
        'balas_jasa_saham',
        'balas_jasa_pinjaman',
        'diubah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tahun_buku' => 'integer',
            'balas_jasa_saham' => 'decimal:2',
            'balas_jasa_pinjaman' => 'decimal:2',
        ];
    }

    public function anggota(): BelongsTo
    {
        return $this->belongsTo(Anggota::class);
    }

    public function total(): float
    {
        return (float) $this->balas_jasa_saham + (float) $this->balas_jasa_pinjaman;
    }
}
