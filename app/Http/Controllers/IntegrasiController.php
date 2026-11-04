<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\PengaturanIntegrasi;
use App\Services\Integrasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * BR-INT-01 / D-019: Admin mengelola CAPTCHA dan Fonnte dari aplikasi.
 * Menyimpan form tidak pernah mengirim pesan atau memanggil vendor.
 */
class IntegrasiController extends Controller
{
    public function index(Integrasi $integrasi): View
    {
        return view('admin.integrasi', [
            'captcha' => $integrasi->pengaturan(PengaturanIntegrasi::CAPTCHA),
            'fonnte' => $integrasi->pengaturan(PengaturanIntegrasi::FONNTE),
        ]);
    }

    public function simpanCaptcha(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['turnstile'])],
            'site_key' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
        ], [
            'provider.required' => 'Pilih provider CAPTCHA.',
            'provider.in' => 'Provider CAPTCHA belum didukung.',
        ]);

        $integrasi->simpan(PengaturanIntegrasi::CAPTCHA, [
            'aktif' => $request->boolean('aktif'),
            'provider' => $data['provider'],
            'site_key' => $data['site_key'] ?? null,
        ], $data['secret'] ?? null, $request->user());

        return back()->with('status', 'Pengaturan CAPTCHA disimpan. Tidak ada verifikasi yang dijalankan.');
    }

    public function ujiCaptcha(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ], [
            'token.required' => 'Tempel token challenge CAPTCHA yang ingin diuji.',
        ]);

        $row = $integrasi->pengaturan(PengaturanIntegrasi::CAPTCHA);

        if ($row === null || ! $row->terkonfigurasi()) {
            return back()->withErrors(['token' => 'Secret CAPTCHA belum tersimpan. Simpan secret lebih dulu.']);
        }

        $lolos = $integrasi->verifikasiCaptcha($data['token'], $request->ip());

        $integrasi->catatUji(
            $row,
            $lolos ? PengaturanIntegrasi::DITERIMA_API : PengaturanIntegrasi::GAGAL,
            $lolos
                ? 'Token challenge lolos verifikasi vendor.'
                : 'Token challenge ditolak atau vendor tidak dapat dihubungi.',
        );

        LogAudit::catat('integrasi.uji', 'pengaturan_integrasi', $row->id, null, [
            'layanan' => PengaturanIntegrasi::CAPTCHA,
            'hasil' => $lolos ? 'lolos' : 'gagal',
        ]);

        return $lolos
            ? back()->with('status', 'CAPTCHA lolos verifikasi vendor.')
            : back()->withErrors(['token' => 'CAPTCHA gagal diverifikasi. Periksa secret, site key, dan token challenge.']);
    }

    public function nonaktifCaptcha(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $integrasi->nonaktifkan(PengaturanIntegrasi::CAPTCHA, $request->user());

        return back()->with('status', 'CAPTCHA dinonaktifkan dan secret dihapus.');
    }

    public function simpanFonnte(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        $integrasi->simpan(PengaturanIntegrasi::FONNTE, [
            'aktif' => $request->boolean('aktif'),
            'label' => $data['label'] ?? null,
        ], $data['token'] ?? null, $request->user());

        return back()->with('status', 'Pengaturan Fonnte disimpan. Tidak ada pesan yang dikirim.');
    }

    public function ujiFonnte(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $data = $request->validate([
            'nomor' => ['required', 'string', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'konfirmasi' => ['accepted'],
        ], [
            'nomor.required' => 'Nomor penguji wajib diisi.',
            'nomor.regex' => 'Nomor penguji harus berupa angka, 8–20 karakter.',
            'konfirmasi.accepted' => 'Centang konfirmasi bahwa satu pesan uji akan dikirim.',
        ]);

        $row = $integrasi->pengaturan(PengaturanIntegrasi::FONNTE);

        if ($row === null || ! $row->siap()) {
            return back()->withErrors(['nomor' => 'Fonnte belum aktif atau token belum tersimpan.']);
        }

        $hasil = $integrasi->kirimWhatsapp(
            preg_replace('/[\s\-]/', '', $data['nomor']),
            'Pesan uji eRAT Koperasi Kredit Rukun '.now()->format('d/m/Y H:i').'. Tidak perlu dibalas.',
        );

        $integrasi->catatUji($row, $hasil['status'], $hasil['pesan']);

        LogAudit::catat('integrasi.uji', 'pengaturan_integrasi', $row->id, null, [
            'layanan' => PengaturanIntegrasi::FONNTE,
            'hasil' => $hasil['status'],
        ]);

        return $hasil['status'] === PengaturanIntegrasi::GAGAL
            ? back()->withErrors(['nomor' => $hasil['pesan']])
            : back()->with('status', 'Fonnte menerima pesan uji: '.$hasil['pesan']);
    }

    public function nonaktifFonnte(Request $request, Integrasi $integrasi): RedirectResponse
    {
        $integrasi->nonaktifkan(PengaturanIntegrasi::FONNTE, $request->user());

        return back()->with('status', 'Fonnte dinonaktifkan dan token dihapus.');
    }
}
