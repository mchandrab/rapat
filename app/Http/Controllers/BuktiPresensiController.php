<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * D-023: bukti foto/TTE disimpan di disk privat dan hanya disajikan lewat rute
 * ber-otorisasi. Hak akses dicek middleware `role:admin,keuangan` pada route.
 */
class BuktiPresensiController extends Controller
{
    public function show(Presensi $presensi, string $jenis): StreamedResponse
    {
        $path = match ($jenis) {
            'foto' => $presensi->foto_path,
            'tte' => $presensi->tte_path,
            default => null,
        };

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
