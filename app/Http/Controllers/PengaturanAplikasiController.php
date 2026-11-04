<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\KasScanner;
use App\Models\LogAudit;
use App\Models\Peserta;
use App\Models\Presensi;
use App\Models\Rapat;
use App\Models\Shu;
use App\Models\TransportPembayaran;
use App\Models\User;
use App\Services\ResetAplikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Reset total aplikasi. Hanya Admin; butuh frasa konfirmasi dan mengeluarkan
 * pengguna karena akun petugas ikut dibuat ulang.
 */
class PengaturanAplikasiController extends Controller
{
    public function index(): View
    {
        return view('admin.pengaturan-aplikasi', [
            'jumlah' => [
                'Rapat' => Rapat::query()->count(),
                'Peserta' => Peserta::query()->count(),
                'Presensi' => Presensi::query()->count(),
                'Pembayaran transport' => TransportPembayaran::query()->count(),
                'Kas Scanner' => KasScanner::query()->count(),
                'Anggota' => Anggota::query()->count(),
                'Data SHU' => Shu::query()->count(),
                'Akun petugas' => User::query()->count(),
                'Log audit' => LogAudit::query()->count(),
            ],
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'konfirmasi' => ['required', 'in:HAPUS SEMUA DATA'],
        ], [
            'konfirmasi.required' => 'Ketik HAPUS SEMUA DATA untuk melanjutkan.',
            'konfirmasi.in' => 'Frasa konfirmasi harus tepat: HAPUS SEMUA DATA (huruf besar).',
        ]);

        (new ResetAplikasi)->jalankan();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Semua data sudah dihapus. Akun Admin dibuat ulang dengan sandi password.');
    }
}
