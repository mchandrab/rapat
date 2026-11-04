<?php

namespace App\Models;

use App\Support\Nba;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    protected $table = 'anggota';

    protected $fillable = [
        'no_anggota',
        'nama',
        'no_hp',
        'email',
        'alamat',
        'jenis_anggota',
        'status_anggota',
        'tgl_ja',
        'tgl_keluar',
        'simpanan_wajib',
        'kolektibilitas',
        'pinjaman_lancar',
    ];

    protected function casts(): array
    {
        return [
            'tgl_ja' => 'date',
            'tgl_keluar' => 'date',
            'simpanan_wajib' => 'decimal:2',
            'kolektibilitas' => 'integer',
            'pinjaman_lancar' => 'boolean',
        ];
    }

    /**
     * BR-001: pencarian menerima NBA dengan maupun tanpa titik.
     */
    public function scopeCari(Builder $query, ?string $kata): void
    {
        $kata = trim((string) $kata);

        if ($kata === '') {
            return;
        }

        $digit = preg_replace('/[.\s]/', '', $kata);

        $query->where(function (Builder $q) use ($kata, $digit): void {
            $q->where('nama', 'like', "%{$kata}%");

            if ($digit !== '') {
                $q->orWhere('no_anggota', 'like', "%{$digit}%");
            }
        });
    }

    public function nbaTampil(): string
    {
        return Nba::format($this->no_anggota);
    }
}
