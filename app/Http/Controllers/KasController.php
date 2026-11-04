<?php

namespace App\Http\Controllers;

use App\Models\KasScanner;
use App\Models\Rapat;
use App\Models\User;
use App\Services\AlokasiKas;
use App\Services\TutupKas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LogicException;

class KasController extends Controller
{
    /**
     * D-013: hanya Keuangan yang menyerahkan kas awal; Admin memantau.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scanner_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'scanner')
                    ->where('active', true)),
            ],
            'kas_awal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
        ], [
            'scanner_user_id.required' => 'Pilih akun Scanner penerima kas.',
            'scanner_user_id.exists' => 'Pilih akun Scanner yang aktif.',
            'kas_awal.required' => 'Kas awal wajib diisi.',
            'kas_awal.numeric' => 'Kas awal harus berupa angka.',
            'kas_awal.min' => 'Kas awal harus lebih dari nol.',
        ]);

        $rapat = Rapat::query()->latest('id')->first();

        if ($rapat === null) {
            return back()->withErrors(['kas' => 'Belum ada rapat. Lengkapi Manajemen Rapat terlebih dahulu.']);
        }

        try {
            $kas = (new AlokasiKas)->serahkan(
                $rapat,
                User::query()->findOrFail($data['scanner_user_id']),
                $request->user(),
                (float) $data['kas_awal'],
            );
        } catch (LogicException $e) {
            return back()->withErrors(['kas' => $e->getMessage()]);
        }

        return back()->with('status', "Kas awal {$kas->scanner->name} dicatat dan diaudit.");
    }

    /**
     * D-026: Scanner mengonfirmasi uang kas sudah diterima sebelum boleh memindai.
     */
    public function terima(Request $request): RedirectResponse
    {
        $rapat = Rapat::query()->latest('id')->first();

        $kas = $rapat === null
            ? null
            : $rapat->kas()->where('scanner_user_id', $request->user()->id)->first();

        if ($kas === null) {
            return back()->withErrors(['kas' => 'Kas Anda belum dialokasikan Keuangan.']);
        }

        try {
            (new AlokasiKas)->terima($kas, $request->user());
        } catch (LogicException $e) {
            return back()->withErrors(['kas' => $e->getMessage()]);
        }

        return back()->with('status', 'Penerimaan kas dikonfirmasi. Anda sudah boleh memindai.');
    }

    /**
     * BR-009: tutup kas per Scanner. Selisih bukan nol wajib beralasan dan diaudit.
     */
    public function tutup(Request $request, KasScanner $kas): RedirectResponse
    {
        $data = $request->validate([
            'sisa_fisik' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'sisa_fisik.required' => 'Sisa uang fisik wajib diisi.',
            'sisa_fisik.numeric' => 'Sisa uang fisik harus berupa angka.',
            'sisa_fisik.min' => 'Sisa uang fisik tidak boleh negatif.',
        ]);

        try {
            (new TutupKas)->tutup($kas, $request->user(), (float) $data['sisa_fisik'], $data['catatan'] ?? null);
        } catch (LogicException $e) {
            return back()->withErrors(['kas' => $e->getMessage()]);
        }

        return back()->with('status', "Kas {$kas->scanner?->name} ditutup dan direkonsiliasi.");
    }

    /**
     * Koreksi nominal salah input: hanya Keuangan, hanya sebelum kas dipakai membayar.
     */
    public function koreksi(Request $request, KasScanner $kas): RedirectResponse
    {
        $data = $request->validate([
            'kas_awal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'alasan' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'kas_awal.required' => 'Kas awal baru wajib diisi.',
            'kas_awal.numeric' => 'Kas awal baru harus berupa angka.',
            'kas_awal.min' => 'Kas awal baru harus lebih dari nol.',
            'alasan.required' => 'Alasan koreksi wajib diisi agar perubahan uang dapat diaudit.',
            'alasan.min' => 'Alasan koreksi minimal 10 karakter.',
        ]);

        try {
            (new AlokasiKas)->koreksi($kas, (float) $data['kas_awal'], $data['alasan']);
        } catch (LogicException $e) {
            return back()->withErrors(['kas' => $e->getMessage()]);
        }

        return back()->with('status', 'Kas awal dikoreksi; nominal sebelum dan sesudah tercatat di audit.');
    }
}
