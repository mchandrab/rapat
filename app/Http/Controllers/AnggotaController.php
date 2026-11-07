<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnggotaRequest;
use App\Models\Anggota;
use App\Models\LogAudit;
use App\Services\ImportAnggota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
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

        $contoh = ['020006000010339', 'Budi Santoso', '081234567890', 'budi@example.com', 'Jl. Contoh 1',
            'biasa', 'aktif', '15/11/2026', null, 50000, 1, 'ya'];
        $sheet->fromArray($contoh, null, 'A2');

        // O-007: kolom NBA harus Teks agar nol awal tidak hilang saat dibuka di Excel.
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode('@');
        $sheet->getCell('A2')->setValueExplicit('020006000010339', DataType::TYPE_STRING);
        $sheet->getCell('C2')->setValueExplicit('081234567890', DataType::TYPE_STRING);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:L1');
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        foreach ([
            'F2:F20000' => '"biasa,luar_biasa"',
            'G2:G20000' => '"aktif,nonaktif"',
            'K2:K20000' => '"0,1,2,3,4,5"',
            'L2:L20000' => '"ya,tidak"',
        ] as $rentang => $pilihan) {
            $kolom = substr($rentang, 0, 1);
            $sheet->getCell($kolom.'2')->getDataValidation()
                ->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(false)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Nilai tidak valid')
                ->setError('Pilih salah satu nilai yang tersedia.')
                ->setFormula1($pilihan)
                ->setSqref($rentang);
        }

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->fromArray(['Kolom', 'Wajib', 'Format / nilai yang diterima', 'Contoh', 'Catatan'], null, 'A1');
        $petunjuk->fromArray([
            ['no_anggota', 'Ya', 'Teks tepat 15 digit tanpa titik', '020006000010339', 'Format sel sebagai Teks agar nol awal tidak hilang.'],
            ['nama', 'Ya', 'Teks, maksimal 100 karakter', 'Budi Santoso', ''],
            ['no_hp', 'Tidak', 'Teks; hasil akhir 9–15 digit', '0812-3456-7890', 'Spasi, tanda baca, dan huruf dibuang saat impor. Format sel sebagai Teks agar nol awal tidak hilang.'],
            ['email', 'Tidak', 'Alamat email valid', 'budi@example.com', 'Kosongkan jika tidak ada; jangan isi tanda minus atau spasi.'],
            ['alamat', 'Tidak', 'Teks, maksimal 255 karakter', 'Jl. Contoh No. 1', 'Kosongkan jika tidak ada.'],
            ['jenis_anggota', 'Tidak', 'biasa atau luar_biasa', 'biasa', 'Jika kosong, sistem memakai biasa.'],
            ['status_anggota', 'Tidak', 'aktif atau nonaktif', 'aktif', 'Jika kosong, sistem memakai aktif.'],
            ['tgl_ja', 'Ya', 'dd/mm/yyyy, yyyy-mm-dd, atau tanggal Excel', '15/11/2026', 'Tanggal mulai menjadi anggota.'],
            ['tgl_keluar', 'Tidak', 'Format tanggal; harus ≥ tgl_ja', '30/11/2026', 'Kosongkan jika anggota belum keluar.'],
            ['simpanan_wajib', 'Ya', 'Angka ≥ 0 tanpa Rp atau pemisah ribuan', '50000', 'Saldo pada Tanggal Acuan Saldo rapat.'],
            ['kolektibilitas', 'Ya', 'Bilangan bulat 0 sampai 5', '0', 'Isi 0 hanya untuk anggota tanpa pinjaman; gunakan 1–5 sesuai kolektibilitas pinjaman.'],
            ['pinjaman_lancar', 'Ya', 'ya atau tidak', 'ya', 'Isi ya untuk pinjaman lancar atau tidak memiliki pinjaman; isi tidak hanya untuk pinjaman bermasalah.'],
        ], null, 'A2');
        $petunjuk->freezePane('A2');
        $petunjuk->getStyle('A1:E1')->getFont()->setBold(true);
        $petunjuk->getStyle('A:A')->getNumberFormat()->setFormatCode('@');

        foreach (range('A', 'L') as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        foreach (range('A', 'E') as $kolom) {
            $petunjuk->getColumnDimension($kolom)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'template-anggota.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
