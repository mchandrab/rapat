<?php

namespace App\Http\Controllers;

use App\Models\LogCekShu;
use App\Models\PengaturanIntegrasi;
use App\Models\Shu;
use App\Models\ShuPublik;
use App\Services\Integrasi;
use App\Support\Nba;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * BR-011 / BR-SHU-LOG-01 / D-029: cek SHU publik dengan NBA + CAPTCHA.
 * CAPTCHA dan rate limit diperiksa sebelum query SHU; semua percobaan dicatat
 * di `log_cek_shu` tanpa nominal.
 */
class CekShuController extends Controller
{
    private const MAKS_PERCOBAAN = 10;

    private const JEDA_DETIK = 60;

    public function index(Integrasi $integrasi): View
    {
        $captcha = $integrasi->pengaturan(PengaturanIntegrasi::CAPTCHA);

        return view('public.cek-shu', [
            'tahunAktif' => ShuPublik::query()->where('aktif', true)->orderByDesc('tahun_buku')->pluck('tahun_buku'),
            'captchaSiap' => $captcha !== null && $captcha->siap(),
            'siteKey' => $captcha?->site_key,
        ]);
    }

    public function cari(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $data = $request->validate([
            'tahun_buku' => ['nullable', 'integer', 'between:2000,2100'],
            'nba' => ['nullable', 'string', 'max:30'],
        ]);

        $tahun = isset($data['tahun_buku']) ? (int) $data['tahun_buku'] : null;
        $nba = Nba::normalize($data['nba'] ?? null);
        $ip = $request->ip();
        $kunci = 'cek-shu:'.$ip;

        // Rate limit sebelum CAPTCHA dan sebelum query SHU.
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            LogCekShu::catat($nba, $tahun, $ip, LogCekShu::DIBATASI);

            return back()->withInput()->withErrors([
                'nba' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.',
            ]);
        }

        RateLimiter::hit($kunci, self::JEDA_DETIK);

        // Fail closed: CAPTCHA belum siap atau token ditolak berarti pencarian berhenti.
        if (! $integrasi->verifikasiCaptcha((string) $request->input('cf-turnstile-response'), $ip)) {
            LogCekShu::catat($nba, $tahun, $ip, LogCekShu::DITOLAK_CAPTCHA);

            return back()->withInput()->withErrors([
                'captcha' => 'Verifikasi CAPTCHA gagal atau layanan belum tersedia. Pencarian tidak dilanjutkan.',
            ]);
        }

        $terbuka = $tahun !== null && ShuPublik::query()
            ->where('tahun_buku', $tahun)
            ->where('aktif', true)
            ->exists();

        if ($nba === null || ! $terbuka) {
            LogCekShu::catat($nba, $tahun, $ip, LogCekShu::TIDAK_VALID);

            return back()->withInput()->withErrors([
                'nba' => 'NBA atau tahun buku tidak sesuai. Periksa kembali isian Anda.',
            ]);
        }

        $shu = Shu::query()
            ->where('tahun_buku', $tahun)
            ->whereHas('anggota', fn ($query) => $query->where('no_anggota', $nba))
            ->first();

        LogCekShu::catat($nba, $tahun, $ip, $shu === null ? LogCekShu::TIDAK_DITEMUKAN : LogCekShu::DITEMUKAN);

        if ($shu === null) {
            return back()->withInput()->withErrors([
                'nba' => 'Data SHU tidak ditemukan untuk NBA dan tahun buku tersebut.',
            ]);
        }

        return redirect()->route('cek-shu')->with('hasil', [
            'nba' => Nba::format($nba),
            'tahun_buku' => $tahun,
            'saham' => (float) $shu->balas_jasa_saham,
            'pinjaman' => (float) $shu->balas_jasa_pinjaman,
            'total' => $shu->total(),
        ]);
    }
}
