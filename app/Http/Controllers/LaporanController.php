<?php

namespace App\Http\Controllers;

use App\Models\Rapat;
use App\Services\Laporan;
use App\Support\LaporanXlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FR-011: laporan rekap per rapat. Bagian yang tampil mengikuti role; ekspor
 * XLSX memakai angka yang sama dari `App\Services\Laporan`.
 */
class LaporanController extends Controller
{
    public function index(Request $request, Laporan $laporan): View
    {
        $rapat = $this->rapat($request);
        $bagian = $laporan->bagian($request->user()->role);

        return view('laporan.index', [
            'rapat' => $rapat,
            'daftarRapat' => Rapat::query()->orderByDesc('id')->get(),
            'rekap' => $rapat === null ? [] : $laporan->rekap($rapat, $bagian),
        ]);
    }

    public function ekspor(Request $request, Laporan $laporan): StreamedResponse
    {
        $rapat = $this->rapat($request);

        abort_if($rapat === null, 404, 'Belum ada rapat untuk dilaporkan.');

        $rekap = $laporan->rekap($rapat, $laporan->bagian($request->user()->role));
        $writer = new Xlsx((new LaporanXlsx)->susun($rapat, $rekap));

        $namaRapat = Str::slug($rapat->judul) ?: 'rapat-'.$rapat->id;

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'laporan-'.$namaRapat.'-'.$rapat->tgl_rapat->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function rapat(Request $request): ?Rapat
    {
        $id = $request->integer('rapat');

        return $id > 0 ? Rapat::query()->find($id) : Rapat::query()->latest('id')->first();
    }
}
