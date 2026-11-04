<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\LogAudit;
use App\Models\Shu;
use App\Models\ShuPublik;
use App\Services\ImportShu;
use App\Support\Nba;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BR-011: SHU dikelola hanya Admin; cek publik dibuka lewat tombol Admin per tahun buku.
 */
class ShuController extends Controller
{
    private const BATAS_NOMINAL = 9999999999999.99;

    public function index(Request $request): View
    {
        $tahun = (int) $request->query('tahun_buku', (string) now()->year);

        $daftar = Shu::query()
            ->join('anggota', 'anggota.id', '=', 'shu.anggota_id')
            ->where('shu.tahun_buku', $tahun)
            ->orderBy('anggota.nama')
            ->select('shu.*')
            ->paginate(50)
            ->withQueryString();

        $total = Shu::query()
            ->where('tahun_buku', $tahun)
            ->selectRaw('COALESCE(SUM(balas_jasa_saham), 0) as saham, COALESCE(SUM(balas_jasa_pinjaman), 0) as pinjaman')
            ->first();

        return view('admin.shu', [
            'tahun' => $tahun,
            'daftar' => $daftar,
            'total' => $total,
            'publik' => ShuPublik::untuk($tahun),
        ]);
    }

    public function impor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun_buku' => ['required', 'integer', 'min:2000', 'max:2100'],
            'berkas' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
        ], [
            'tahun_buku.required' => 'Tahun buku wajib diisi.',
            'berkas.required' => 'Pilih berkas XLSX terlebih dahulu.',
            'berkas.extensions' => 'Berkas impor harus berformat .xlsx.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        try {
            $hasil = (new ImportShu)->jalankan($request->file('berkas')->getRealPath(), (int) $data['tahun_buku']);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['berkas' => 'Berkas tidak dapat dibaca sebagai XLSX.']);
        }

        return redirect()
            ->route('shu.index', ['tahun_buku' => (int) $data['tahun_buku']])
            ->with('impor', $hasil);
    }

    public function simpanManual(Request $request): RedirectResponse
    {
        $data = $this->validasiNominal($request, true);
        $nba = Nba::normalize($data['no_anggota']);

        if ($nba === null) {
            throw ValidationException::withMessages(['no_anggota' => 'NBA harus tepat 15 digit. Titik pemisah boleh digunakan.']);
        }

        $anggota = Anggota::query()->where('no_anggota', $nba)->first();

        if ($anggota === null) {
            throw ValidationException::withMessages(['no_anggota' => 'NBA belum ada di master anggota.']);
        }

        if (Shu::query()->where('anggota_id', $anggota->id)->where('tahun_buku', $data['tahun_buku'])->exists()) {
            throw ValidationException::withMessages(['no_anggota' => 'SHU anggota untuk tahun buku ini sudah ada. Gunakan tombol Koreksi.']);
        }

        $shu = DB::transaction(function () use ($anggota, $data): Shu {
            $shu = Shu::create([
                'anggota_id' => $anggota->id,
                'tahun_buku' => $data['tahun_buku'],
                'balas_jasa_saham' => $data['balas_jasa_saham'],
                'balas_jasa_pinjaman' => $data['balas_jasa_pinjaman'],
                'diubah_oleh' => auth()->id(),
            ]);

            LogAudit::catat('buat', 'shu', $shu->id, null, $shu->only([
                'anggota_id', 'tahun_buku', 'balas_jasa_saham', 'balas_jasa_pinjaman',
            ]), 'input manual');

            return $shu;
        });

        return redirect()
            ->route('shu.index', ['tahun_buku' => $shu->tahun_buku])
            ->with('status', 'Data SHU manual disimpan.');
    }

    public function edit(Shu $shu): View
    {
        return view('admin.shu-ubah', ['shu' => $shu->load('anggota')]);
    }

    public function update(Request $request, Shu $shu): RedirectResponse
    {
        $data = $this->validasiNominal($request);
        $sebelum = $shu->only(['balas_jasa_saham', 'balas_jasa_pinjaman']);

        $shu->fill([
            'balas_jasa_saham' => $data['balas_jasa_saham'],
            'balas_jasa_pinjaman' => $data['balas_jasa_pinjaman'],
        ]);

        if (! $shu->isDirty()) {
            return back()->withErrors(['shu' => 'Tidak ada nominal yang berubah.']);
        }

        DB::transaction(function () use ($shu, $sebelum, $data): void {
            $shu->diubah_oleh = auth()->id();
            $shu->save();

            LogAudit::catat('ubah', 'shu', $shu->id, $sebelum, $shu->only([
                'balas_jasa_saham', 'balas_jasa_pinjaman',
            ]), $data['alasan']);
        });

        return redirect()
            ->route('shu.index', ['tahun_buku' => $shu->tahun_buku])
            ->with('status', 'Data SHU dikoreksi dan dicatat di Audit.');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SHU');
        $sheet->fromArray(ImportShu::KOLOM, null, 'A1');
        $sheet->fromArray(['020006000010339', 150000, 75000], null, 'A2');

        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->getCell('A2')->setValueExplicit('020006000010339', DataType::TYPE_STRING);

        foreach (['A', 'B', 'C'] as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, 'template-shu.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function bukaPublik(Request $request): RedirectResponse
    {
        $tahun = $this->tahun($request);

        if (! Shu::query()->where('tahun_buku', $tahun)->exists()) {
            return back()->withErrors(['publik' => "Belum ada data SHU tahun buku {$tahun} untuk dibuka."]);
        }

        $publik = ShuPublik::untuk($tahun);
        $sebelum = $publik->exists ? $publik->only(['aktif', 'dibuka_oleh', 'dibuka_at', 'ditutup_at']) : null;

        $publik->fill([
            'aktif' => true,
            'dibuka_oleh' => $request->user()->id,
            'dibuka_at' => now(),
            'ditutup_at' => null,
        ]);
        $publik->save();

        LogAudit::catat('buka', 'shu_publik', $publik->id, $sebelum, $publik->only(['aktif', 'dibuka_oleh', 'dibuka_at', 'ditutup_at']));

        return back()->with('status', "Cek SHU publik tahun buku {$tahun} dibuka.");
    }

    public function tutupPublik(Request $request): RedirectResponse
    {
        $tahun = $this->tahun($request);
        $publik = ShuPublik::untuk($tahun);

        if (! $publik->exists || ! $publik->aktif) {
            return back()->withErrors(['publik' => "Cek SHU publik tahun buku {$tahun} memang belum dibuka."]);
        }

        $sebelum = $publik->only(['aktif', 'dibuka_oleh', 'dibuka_at', 'ditutup_at']);

        $publik->fill(['aktif' => false, 'ditutup_at' => now()]);
        $publik->save();

        LogAudit::catat('tutup', 'shu_publik', $publik->id, $sebelum, $publik->only(['aktif', 'dibuka_oleh', 'dibuka_at', 'ditutup_at']));

        return back()->with('status', "Cek SHU publik tahun buku {$tahun} ditutup.");
    }

    private function tahun(Request $request): int
    {
        return (int) $request->validate([
            'tahun_buku' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [
            'tahun_buku.required' => 'Tahun buku wajib diisi.',
        ])['tahun_buku'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validasiNominal(Request $request, bool $baru = false): array
    {
        return $request->validate([
            'no_anggota' => [$baru ? 'required' : 'nullable', 'string', 'max:18'],
            'tahun_buku' => [$baru ? 'required' : 'nullable', 'integer', 'min:2000', 'max:2100'],
            'balas_jasa_saham' => ['required', 'numeric', 'min:0', 'max:'.self::BATAS_NOMINAL],
            'balas_jasa_pinjaman' => ['required', 'numeric', 'min:0', 'max:'.self::BATAS_NOMINAL],
            'alasan' => [$baru ? 'nullable' : 'required', 'string', 'min:10', 'max:1000'],
        ], [
            'balas_jasa_saham.required' => 'Balas Jasa Saham wajib diisi.',
            'balas_jasa_pinjaman.required' => 'Balas Jasa Pinjaman wajib diisi.',
            'alasan.required' => 'Alasan koreksi wajib diisi.',
            'alasan.min' => 'Alasan koreksi minimal 10 karakter.',
        ]);
    }
}
