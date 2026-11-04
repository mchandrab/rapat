<?php

namespace App\Http\Controllers;

use App\Models\TransportPembayaran;
use Illuminate\View\View;

/**
 * Resi transport. Boleh dilihat Scanner yang menyerahkan uang, Admin, dan Keuangan
 * (ROLE_PERMISSION_MATRIX: kas & transport untuk audit). Helpdesk tidak berhak.
 */
class ResiController extends Controller
{
    public function show(string $kode): View
    {
        $bayar = TransportPembayaran::query()
            ->where('kode_resi', $kode)
            ->with(['peserta.anggota', 'peserta.rapat', 'pembayar'])
            ->firstOrFail();

        $user = auth()->user();

        $boleh = in_array($user->role, ['admin', 'keuangan'], true)
            || ($user->role === 'scanner' && $bayar->dibayar_oleh === $user->id);

        abort_unless($boleh, 403);

        return view('resi.show', ['bayar' => $bayar]);
    }
}
