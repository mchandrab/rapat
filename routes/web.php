<?php

use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\BuktiPresensiController;
use App\Http\Controllers\CekShuController;
use App\Http\Controllers\IntegrasiController;
use App\Http\Controllers\KasController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PengaturanAplikasiController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PesertaController;
use App\Http\Controllers\RapatController;
use App\Http\Controllers\ResiController;
use App\Http\Controllers\ShuController;
use App\Http\Controllers\UndanganController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/cek-shu', [CekShuController::class, 'index'])->name('cek-shu');
Route::post('/cek-shu', [CekShuController::class, 'cari'])->name('cek-shu.cari');
Route::get('/papan-kuorum', fn () => view('public.kuorum'))->name('kuorum.public');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');

    Route::post('/login', function (Request $request) {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $credentials['active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Username atau kata sandi salah.'])->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    })->middleware('throttle:5,1');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Resi transport: hak akses dicek di controller (Scanner pembayar, Admin, Keuangan).
    Route::get('/resi/{kode}', [ResiController::class, 'show'])->name('resi.show');

    Route::middleware('role:admin')->group(function () {
        Route::get('/rapat', [RapatController::class, 'edit'])->name('rapat.index');
        Route::put('/rapat/{rapat}', [RapatController::class, 'update'])->name('rapat.update');
        Route::post('/rapat/{rapat}/status', [RapatController::class, 'transisi'])->name('rapat.transisi');
        Route::post('/rapat/{rapat}/presensi', [RapatController::class, 'bukaPresensi'])->name('rapat.presensi');
        Route::post('/rapat/{rapat}/peserta', [PesertaController::class, 'siapkan'])->name('peserta.siapkan');
        Route::get('/shu', [ShuController::class, 'index'])->name('shu.index');
        Route::post('/shu/manual', [ShuController::class, 'simpanManual'])->name('shu.manual.simpan');
        Route::get('/shu/{shu}/ubah', [ShuController::class, 'edit'])->name('shu.edit');
        Route::put('/shu/{shu}', [ShuController::class, 'update'])->name('shu.update');
        Route::post('/shu/impor', [ShuController::class, 'impor'])->name('shu.impor');
        Route::get('/shu/template', [ShuController::class, 'template'])->name('shu.template');
        Route::post('/shu/publik/buka', [ShuController::class, 'bukaPublik'])->name('shu.publik.buka');
        Route::post('/shu/publik/tutup', [ShuController::class, 'tutupPublik'])->name('shu.publik.tutup');
        Route::get('/doorprize', fn () => view('admin.doorprize'))->name('doorprize.index');
        Route::get('/audit', fn () => view('admin.audit'))->name('audit.index');
        Route::delete('/pengaturan/pengguna/{user}', [PenggunaController::class, 'destroy'])->name('pengguna.destroy');
        Route::patch('/pengaturan/pengguna/{user}/status', [PenggunaController::class, 'status'])->name('pengguna.status');
        Route::put('/pengaturan/pengguna/{user}', [PenggunaController::class, 'update'])->name('pengguna.update');
        Route::get('/pengaturan/pengguna/{user}/ubah', [PenggunaController::class, 'edit'])->name('pengguna.edit');
        Route::post('/pengaturan/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::get('/pengaturan/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengaturan/aplikasi', [PengaturanAplikasiController::class, 'index'])->name('pengaturan.aplikasi');
        Route::post('/pengaturan/aplikasi/reset', [PengaturanAplikasiController::class, 'reset'])->name('pengaturan.aplikasi.reset');
        Route::get('/pengaturan/integrasi', [IntegrasiController::class, 'index'])->name('integrasi.index');
        Route::put('/pengaturan/integrasi/captcha', [IntegrasiController::class, 'simpanCaptcha'])->name('integrasi.captcha.simpan');
        Route::post('/pengaturan/integrasi/captcha/uji', [IntegrasiController::class, 'ujiCaptcha'])
            ->middleware('throttle:10,1')->name('integrasi.captcha.uji');
        Route::post('/pengaturan/integrasi/captcha/nonaktif', [IntegrasiController::class, 'nonaktifCaptcha'])->name('integrasi.captcha.nonaktif');
        Route::put('/pengaturan/integrasi/fonnte', [IntegrasiController::class, 'simpanFonnte'])->name('integrasi.fonnte.simpan');
        Route::post('/pengaturan/integrasi/fonnte/uji', [IntegrasiController::class, 'ujiFonnte'])
            ->middleware('throttle:5,1')->name('integrasi.fonnte.uji');
        Route::post('/pengaturan/integrasi/fonnte/nonaktif', [IntegrasiController::class, 'nonaktifFonnte'])->name('integrasi.fonnte.nonaktif');

        Route::post('/anggota', [AnggotaController::class, 'store'])->name('anggota.store');
        Route::put('/anggota/{anggota}', [AnggotaController::class, 'update'])->name('anggota.update');
        Route::post('/anggota/impor', [AnggotaController::class, 'impor'])->name('anggota.impor');
        Route::get('/anggota/template', [AnggotaController::class, 'template'])->name('anggota.template');
    });

    Route::middleware('role:admin,helpdesk')->group(function () {
        Route::get('/anggota', fn () => view('admin.anggota'))->name('anggota.index');
        Route::get('/verifikasi', fn () => view('admin.anggota'))->name('verifikasi.index');
        Route::get('/undangan', fn () => view('undangan.index'))->name('undangan.index');
        Route::get('/undangan/{peserta}', [UndanganController::class, 'show'])->name('undangan.cetak');
    });

    // FR-011: laporan per rapat; bagian yang tampil dibatasi role di controller.
    Route::middleware('role:admin,helpdesk,keuangan')->group(function () {
        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/ekspor', [LaporanController::class, 'ekspor'])->name('laporan.ekspor');
    });

    Route::middleware('role:admin,keuangan')->group(function () {
        Route::get('/kas', fn () => view('kas.index'))->name('kas.index');

        // D-023: bukti foto/TTE hanya dari disk privat, hanya Admin & Keuangan.
        Route::get('/presensi', fn () => view('presensi.index'))->name('presensi.index');
        Route::get('/presensi/{presensi}/bukti/{jenis}', [BuktiPresensiController::class, 'show'])->name('presensi.bukti');

        // BR-010: kuorum hanya anggota biasa; Admin dan Keuangan boleh memantau.
        Route::get('/kuorum', fn () => view('kuorum.index'))->name('kuorum.index');
    });

    Route::middleware('role:keuangan')->group(function () {
        Route::post('/kas', [KasController::class, 'store'])->name('kas.store');
        Route::post('/kas/{kas}/koreksi', [KasController::class, 'koreksi'])->name('kas.koreksi');
        Route::post('/rekonsiliasi/{kas}', [KasController::class, 'tutup'])->name('kas.tutup');
        Route::get('/rekonsiliasi', fn () => view('keuangan.rekonsiliasi'))->name('rekonsiliasi');
        Route::get('/transport', fn () => view('keuangan.transport'))->name('transport');
    });

    Route::middleware('role:scanner')->group(function () {
        Route::get('/kas-saya', fn () => view('scanner.kas-saya'))->name('kas-saya');
        Route::post('/kas-saya/terima', [KasController::class, 'terima'])->name('kas.terima');
        Route::get('/sinkron', fn () => view('scanner.sinkron'))->name('sinkron');
    });
});
