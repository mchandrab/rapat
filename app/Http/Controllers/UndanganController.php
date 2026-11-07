<?php

namespace App\Http\Controllers;

use App\Models\Peserta;
use App\Services\VerifikasiPeserta;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\View\View;

/**
 * BR-005: undangan cetak berisi QR rapat. Token yang sama dipakai ulang setiap
 * cetak ulang; tidak ada token baru (D-005).
 */
class UndanganController extends Controller
{
    public function show(Peserta $peserta): View
    {
        return $this->tampilkan($peserta, false);
    }

    /**
     * Undangan digital dari tautan WhatsApp. Token acak menjadi satu-satunya kunci,
     * jadi halaman ini tidak memerlukan login dan hanya membuka satu peserta.
     */
    public function publik(string $token): View
    {
        $peserta = Peserta::query()
            ->where('qr_token_hash', hash('sha256', $token))
            ->firstOrFail();

        return $this->tampilkan($peserta, true);
    }

    private function tampilkan(Peserta $peserta, bool $publik): View
    {
        $token = (new VerifikasiPeserta)->token($peserta);

        abort_if($token === null, 404);

        $qr = new QrCode(
            data: $token,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 8,
        );

        return view('undangan.cetak', [
            'peserta' => $peserta->load('anggota', 'rapat'),
            'token' => $token,
            'qr' => (new SvgWriter)->write($qr)->getString(),
            'publik' => $publik,
        ]);
    }
}
