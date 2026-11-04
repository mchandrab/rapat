<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BR-011: saklar cek SHU publik per tahun buku, dibuka Admin kapan saja.
 */
class ShuPublik extends Model
{
    protected $table = 'shu_publik';

    protected $fillable = [
        'tahun_buku',
        'aktif',
        'dibuka_oleh',
        'dibuka_at',
        'ditutup_at',
    ];

    protected function casts(): array
    {
        return [
            'tahun_buku' => 'integer',
            'aktif' => 'boolean',
            'dibuka_at' => 'datetime',
            'ditutup_at' => 'datetime',
        ];
    }

    public static function untuk(int $tahunBuku): self
    {
        return static::query()->firstOrNew(['tahun_buku' => $tahunBuku]);
    }
}
