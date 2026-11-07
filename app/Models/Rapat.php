<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BR-002: parameter rapat berlaku untuk seluruh modul dan dikunci saat Berlangsung.
 */
class Rapat extends Model
{
    public const DRAF = 'draf';

    public const VERIFIKASI = 'verifikasi';

    public const BERLANGSUNG = 'berlangsung';

    public const SELESAI = 'selesai';

    public const DIBATALKAN = 'dibatalkan';

    /** Status tujuan per aksi. */
    private const TUJUAN = [
        'verifikasi' => self::VERIFIKASI,
        'mulai' => self::BERLANGSUNG,
        'selesai' => self::SELESAI,
        'batal' => self::DIBATALKAN,
    ];

    /** Status asal yang diizinkan per aksi. */
    private const DARI = [
        'verifikasi' => [self::DRAF],
        'mulai' => [self::VERIFIKASI],
        'selesai' => [self::BERLANGSUNG],
        'batal' => [self::DRAF, self::VERIFIKASI],
    ];

    protected $table = 'rapat';

    protected $fillable = [
        'jenis',
        'judul',
        'tanggal_acuan_saldo',
        'tgl_rapat',
        'jam_mulai_presensi',
        'jam_mulai_rat',
        'jam_selesai',
        'lokasi',
        'alamat_lokasi',
        'tautan_google_maps',
        'status',
        'presensi_dibuka_at',
        'dibuka_oleh',
        'mode_presensi',
        'mode_kelayakan',
        'minimal_simpanan_tahunan',
        'simpanan_per_bulan',
        'gunakan_proporsional',
        'batas_kolektibilitas',
        'wajib_pinjaman_lancar',
        'basis_kuorum',
        'persen_kuorum',
        'tambahan_kuorum',
        'transport_dasar',
        'toleransi_menit',
        'batas_hangus_menit',
        'batas_peringatan_kas',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_acuan_saldo' => 'date',
            'tgl_rapat' => 'date',
            'presensi_dibuka_at' => 'datetime',
            'locked_at' => 'datetime',
            'gunakan_proporsional' => 'boolean',
            'wajib_pinjaman_lancar' => 'boolean',
            'batas_kolektibilitas' => 'integer',
            'persen_kuorum' => 'integer',
            'tambahan_kuorum' => 'integer',
            'toleransi_menit' => 'integer',
            'minimal_simpanan_tahunan' => 'decimal:2',
            'simpanan_per_bulan' => 'decimal:2',
            'transport_dasar' => 'decimal:2',
            'batas_peringatan_kas' => 'decimal:2',
        ];
    }

    /**
     * Nilai awal saat belum ada rapat tersimpan. Admin menyesuaikan sebelum dipakai.
     */
    public static function bawaan(): array
    {
        $tanggalAcuan = now()->subMonthNoOverflow()->endOfMonth();

        return [
            'jenis' => 'rat',
            'judul' => 'Rapat Anggota Tahunan '.$tanggalAcuan->year,
            'tanggal_acuan_saldo' => $tanggalAcuan->toDateString(),
            'tgl_rapat' => now()->addDays(14)->toDateString(),
            'jam_mulai_presensi' => '07:00',
            'jam_mulai_rat' => '08:00',
            'jam_selesai' => null,
            'lokasi' => 'Aula Koperasi Kredit Rukun',
            'alamat_lokasi' => null,
            'tautan_google_maps' => null,
            'status' => self::DRAF,
            'mode_presensi' => '1x',
            'mode_kelayakan' => 'A',
            'minimal_simpanan_tahunan' => 300000,
            'simpanan_per_bulan' => 25000,
            'gunakan_proporsional' => true,
            'batas_kolektibilitas' => 2,
            'wajib_pinjaman_lancar' => false,
            'basis_kuorum' => 'semua_biasa',
            'persen_kuorum' => 50,
            'tambahan_kuorum' => 1,
            'transport_dasar' => 100000,
            'toleransi_menit' => 60,
        ];
    }

    public function tier(): HasMany
    {
        return $this->hasMany(RapatTransportTier::class)->orderBy('urutan');
    }

    /**
     * D-025: ambang peringatan kas menipis; bawaan 5 × nominal dasar transport.
     */
    public function batasPeringatan(): float
    {
        return (float) ($this->batas_peringatan_kas ?? 5 * (float) $this->transport_dasar);
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(Peserta::class);
    }

    public function doorprizeHadiah(): HasMany
    {
        return $this->hasMany(DoorprizeHadiah::class);
    }

    public function kas(): HasMany
    {
        return $this->hasMany(KasScanner::class);
    }

    public function pembuka(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    /**
     * D-021: Admin membuka presensi lebih awal tanpa menambah status baru; status tetap Verifikasi.
     * Hanya sekali dan hanya dari status Verifikasi.
     */
    public function bukaPresensi(User $admin): bool
    {
        if ($this->status !== self::VERIFIKASI) {
            return false;
        }

        // Update bersyarat: dua klik bersamaan tidak membuka dua kali.
        $berubah = static::query()
            ->whereKey($this->id)
            ->whereNull('presensi_dibuka_at')
            ->update([
                'presensi_dibuka_at' => now(),
                'dibuka_oleh' => $admin->id,
            ]);

        if ($berubah === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    /**
     * D-021: gerbang scan = presensi sudah dibuka dan rapat belum Selesai/Dibatalkan.
     */
    public function presensiDibuka(): bool
    {
        return $this->presensi_dibuka_at !== null
            && ! in_array($this->status, [self::SELESAI, self::DIBATALKAN], true);
    }

    /**
     * BR-002: parameter tidak boleh berubah sejak rapat Berlangsung.
     */
    public function terkunci(): bool
    {
        return $this->locked_at !== null
            || in_array($this->status, [self::BERLANGSUNG, self::SELESAI, self::DIBATALKAN], true);
    }

    public function bolehDiubah(): bool
    {
        return ! $this->terkunci();
    }

    /**
     * BR-002: Draf -> Verifikasi -> Berlangsung -> Selesai, dengan Dibatalkan sebagai alternatif
     * sebelum rapat berlangsung. Mengembalikan false bila aksi tidak sah dari status saat ini.
     */
    public function transisi(string $aksi): bool
    {
        $tujuan = self::TUJUAN[$aksi] ?? null;

        if ($tujuan === null || ! in_array($this->status, self::DARI[$aksi], true)) {
            return false;
        }

        $this->status = $tujuan;

        if ($tujuan === self::BERLANGSUNG) {
            $this->locked_at = now();
        }

        return $this->save();
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::DRAF => 'Draf',
            self::VERIFIKASI => 'Verifikasi',
            self::BERLANGSUNG => 'Berlangsung',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
            default => ucfirst((string) $this->status),
        };
    }

    public function labelModePresensi(): string
    {
        return $this->mode_presensi === '2x'
            ? 'Mode Presensi 2x (Datang–Pulang)'
            : 'Mode Presensi 1x';
    }

    public function labelModeKelayakan(): string
    {
        return $this->mode_kelayakan === 'B'
            ? 'Mode B — Simpanan Wajib & Pinjaman Lancar'
            : 'Mode A — Simpanan Wajib & Kolektibilitas';
    }
}
