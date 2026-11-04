<?php

namespace App\Support;

use App\Models\Rapat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * FR-011: satu lembar XLSX berisi bagian laporan yang boleh dibaca role.
 * Angka diambil dari `App\Services\Laporan`, bukan dihitung ulang di sini.
 */
final class LaporanXlsx
{
    /**
     * @param  array<string, mixed>  $rekap
     */
    public function susun(Rapat $rapat, array $rekap): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan');

        $baris = 1;
        $this->judul($sheet, $baris, 'Laporan '.$rapat->judul);
        $sheet->setCellValue("A{$baris}", 'Tanggal RAT '.$rapat->tgl_rapat->format('d/m/Y').' - status '.$rapat->labelStatus());
        $baris += 2;

        if (isset($rekap['kuorum'])) {
            $kuorum = $rekap['kuorum'];
            $this->judul($sheet, $baris, 'Kuorum');
            $this->tabel($sheet, $baris, ['Keterangan', 'Nilai'], [
                ['Populasi dasar', $kuorum['populasi']],
                ['Target kuorum', $kuorum['target']],
                ['Hadir anggota biasa', $kuorum['hadir']],
                ['Hadir luar biasa', $kuorum['hadir_luar_biasa']],
                ['Persentase hadir (%)', $kuorum['persen']],
                ['Status', $kuorum['tercapai'] ? 'Tercapai - rapat sah' : 'Belum tercapai'],
                ['Jam tercapai', $kuorum['jam_tercapai']?->format('d/m/Y H:i') ?? '-'],
            ]);
        }

        if (isset($rekap['peserta'])) {
            $peserta = $rekap['peserta'];
            $this->judul($sheet, $baris, 'Peserta & verifikasi');
            $this->tabel($sheet, $baris, ['Keterangan', 'Jumlah'], [
                ['Total peserta', $peserta['total']],
                ['Hasil layak', $peserta['layak']],
                ['Hasil tidak layak', $peserta['tidak_layak']],
                ['Verified', $peserta['verified']],
                ['Rejected', $peserta['rejected']],
                ['Pending', $peserta['pending']],
            ]);
        }

        if (isset($rekap['undangan'])) {
            $undangan = $rekap['undangan'];
            $this->judul($sheet, $baris, 'Undangan per petugas');
            $this->tabel($sheet, $baris, ['Petugas', 'WhatsApp', 'Cetak', 'Total'], array_merge(
                array_map(fn ($barisData) => [$barisData['petugas'], $barisData['wa'], $barisData['cetak'], $barisData['total']], $undangan['per_petugas']),
                [['TOTAL', '', '', $undangan['total']]],
            ));
        }

        if (isset($rekap['presensi'])) {
            $presensi = $rekap['presensi'];
            $this->judul($sheet, $baris, 'Presensi & transport per petugas');
            $this->tabel($sheet, $baris, ['Petugas', 'Presensi', 'Transport'], array_merge(
                array_map(fn ($barisData) => [$barisData['petugas'], $barisData['total'], $barisData['nominal']], $presensi['per_petugas']),
                [['TOTAL', $presensi['total'], $presensi['nominal']]],
            ), ['C']);
        }

        if (isset($rekap['kas'])) {
            $this->judul($sheet, $baris, 'Kas per Scanner');
            $this->tabel($sheet, $baris, ['Scanner', 'Kas awal', 'Terbayar', 'Sisa seharusnya', 'Sisa fisik', 'Selisih', 'Status'], array_map(fn ($barisData) => [
                $barisData['scanner'],
                $barisData['kas_awal'],
                $barisData['terbayar'],
                $barisData['sisa'],
                $barisData['sisa_fisik'] ?? '-',
                $barisData['selisih'] ?? '-',
                $barisData['status'],
            ], $rekap['kas']), ['B', 'C', 'D', 'E', 'F']);
        }

        if (isset($rekap['shu'])) {
            $this->judul($sheet, $baris, 'SHU per tahun buku');
            $this->tabel($sheet, $baris, ['Tahun buku', 'Anggota', 'Jasa saham', 'Jasa pinjaman', 'Total'], array_map(fn ($barisData) => [
                $barisData['tahun_buku'],
                $barisData['jumlah'],
                $barisData['saham'],
                $barisData['pinjaman'],
                $barisData['total'],
            ], $rekap['shu']), ['C', 'D', 'E']);
        }

        if (isset($rekap['doorprize'])) {
            $doorprize = $rekap['doorprize'];
            $this->judul($sheet, $baris, 'Doorprize');
            $sheet->setCellValue("A{$baris}", 'Target pemenang '.$doorprize['target'].', menang '.$doorprize['menang'].', gugur '.$doorprize['gugur']);
            $baris += 2;
            $this->tabel($sheet, $baris, ['Urutan', 'Nama pemenang', 'NBA'], array_map(
                fn ($barisData) => [$barisData['urutan'], $barisData['nama'], $barisData['nba']],
                $doorprize['pemenang'],
            ));
        }

        foreach (['A' => 24, 'B' => 20, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 14, 'G' => 12] as $kolom => $lebar) {
            $sheet->getColumnDimension($kolom)->setWidth($lebar);
        }

        return $spreadsheet;
    }

    private function judul(Worksheet $sheet, int &$baris, string $teks): void
    {
        $sheet->setCellValue("A{$baris}", $teks);
        $sheet->getStyle("A{$baris}")->getFont()->setBold(true);
        $baris++;
    }

    /**
     * @param  array<int, string>  $kepala
     * @param  array<int, array<int, mixed>>  $data
     * @param  array<int, string>  $kolomUang
     */
    private function tabel(Worksheet $sheet, int &$baris, array $kepala, array $data, array $kolomUang = []): void
    {
        $sheet->fromArray($kepala, null, "A{$baris}");
        $sheet->getStyle("A{$baris}:".chr(64 + count($kepala))."{$baris}")->getFont()->setBold(true);
        $baris++;

        foreach ($data as $barisData) {
            $sheet->fromArray($barisData, null, "A{$baris}");

            foreach ($kolomUang as $kolom) {
                if (is_numeric($sheet->getCell("{$kolom}{$baris}")->getValue())) {
                    $sheet->getStyle("{$kolom}{$baris}")->getNumberFormat()->setFormatCode('#,##0');
                }
            }

            $baris++;
        }

        $baris++;
    }
}
