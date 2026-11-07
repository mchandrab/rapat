<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\Rapat;
use App\Services\PenyiapanPeserta;
use Illuminate\Http\RedirectResponse;

class PesertaController extends Controller
{
    public function siapkan(Rapat $rapat): RedirectResponse
    {
        if ($rapat->terkunci()) {
            return back()->withErrors([
                'peserta' => "Peserta tidak dapat disiapkan karena rapat sudah {$rapat->labelStatus()}.",
            ]);
        }

        $hasil = (new PenyiapanPeserta)->siapkan($rapat);

        LogAudit::catat('siapkan', 'peserta', $rapat->id, null, $hasil);

        return back()->with('status', "Peserta disiapkan: {$hasil['dibuat']} baru, {$hasil['diperbarui']} Pending dihitung ulang, {$hasil['dilewati']} sudah diputuskan dan dilewati.");
    }
}
