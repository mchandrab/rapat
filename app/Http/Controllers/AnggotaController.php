<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnggotaRequest;
use App\Models\Anggota;
use App\Models\LogAudit;
use App\Services\ImportAnggota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnggotaController extends Controller
{
    public function store(AnggotaRequest $request): RedirectResponse
    {
        $anggota = Anggota::create($request->validated());
        LogAudit::catat('buat', 'anggota', $anggota->id, null, $anggota->only(array_keys($request->validated())));

        return back()->with('status', "Anggota {$anggota->nama} ditambahkan.");
    }

    public function update(AnggotaRequest $request, Anggota $anggota): RedirectResponse
    {
        $kolom = array_keys($request->validated());
        $sebelum = $anggota->only($kolom);

        $anggota->update($request->validated());

        if ($anggota->wasChanged()) {
            LogAudit::catat('ubah', 'anggota', $anggota->id, $sebelum, $anggota->only($kolom));
        }

        return back()->with('status', "Data anggota {$anggota->nbaTampil()} diperbarui.");
    }

    public function impor(Request $request): RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
        ], [
            'berkas.required' => 'Pilih berkas XLSX terlebih dahulu.',
            'berkas.extensions' => 'Berkas impor harus berformat .xlsx.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        try {
            $hasil = (new ImportAnggota)->jalankan($request->file('berkas')->getRealPath());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['berkas' => 'Berkas tidak dapat dibaca sebagai XLSX.']);
        }

        return back()->with('impor', $hasil);
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Anggota');
        $sheet->fromArray(ImportAnggota::KOLOM, null, 'A1');

        $contoh = ['020006000010339', 'Budi Santoso', '081234567890', 'budi@example.test', 'Jl. Contoh 1',
            'biasa', 'aktif', '15/11/2026', null, 50000, 1, 'ya'];
        $sheet->fromArray($contoh, null, 'A2');

        // O-007: kolom NBA harus Teks agar nol awal tidak hilang saat dibuka di Excel.
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->getCell('A2')->setValueExplicit('020006000010339', DataType::TYPE_STRING);

        foreach (range('A', 'L') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'template-anggota.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
