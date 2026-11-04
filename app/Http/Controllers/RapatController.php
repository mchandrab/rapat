<?php

namespace App\Http\Controllers;

use App\Http\Requests\RapatRequest;
use App\Models\LogAudit;
use App\Models\Rapat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RapatController extends Controller
{
    public function edit(): View
    {
        $rapat = Rapat::query()->latest('id')->first() ?? Rapat::create(Rapat::bawaan());

        return view('admin.rapat', ['rapat' => $rapat]);
    }

    public function update(RapatRequest $request, Rapat $rapat): RedirectResponse
    {
        if (! $rapat->bolehDiubah()) {
            return back()->withErrors([
                'rapat' => "Parameter rapat terkunci karena rapat sudah {$rapat->labelStatus()}.",
            ]);
        }

        $data = $request->validated();
        $tier = $data['tier'] ?? [];
        unset($data['tier']);

        // D-043: acuan saldo selalu 31 Desember Tahun Acuan, bukan isian bebas.
        $data['tanggal_acuan_saldo'] = $data['tahun_acuan'].'-12-31';

        $sebelum = $rapat->only(array_keys($data));
        $sebelum['tier'] = $this->tierTersimpan($rapat);

        DB::transaction(function () use ($rapat, $data, $tier): void {
            $rapat->update($data);
            $rapat->tier()->delete();

            foreach (array_values($tier) as $urutan => $baris) {
                $rapat->tier()->create([
                    'menit_mulai' => $baris['menit_mulai'],
                    'menit_akhir' => $baris['menit_akhir'],
                    'potongan' => $baris['potongan'],
                    'urutan' => $urutan + 1,
                ]);
            }
        });

        $sesudah = $rapat->fresh()->only(array_keys($data));
        $sesudah['tier'] = $this->tierTersimpan($rapat);

        LogAudit::catat('ubah', 'rapat', $rapat->id, $sebelum, $sesudah);

        return back()->with('status', 'Pengaturan rapat disimpan.');
    }

    /**
     * D-021: presensi dibuka Admin saat rapat Verifikasi; status rapat tidak berubah.
     */
    public function bukaPresensi(Request $request, Rapat $rapat): RedirectResponse
    {
        if (! $rapat->bukaPresensi($request->user())) {
            return back()->withErrors([
                'rapat' => 'Presensi hanya dapat dibuka sekali, saat rapat berstatus Verifikasi.',
            ]);
        }

        LogAudit::catat('buka_presensi', 'rapat', $rapat->id, ['presensi_dibuka_at' => null], [
            'presensi_dibuka_at' => $rapat->presensi_dibuka_at?->toDateTimeString(),
        ]);

        return back()->with('status', 'Presensi dibuka. Scanner dapat memindai QR peserta Verified.');
    }

    public function transisi(Request $request, Rapat $rapat): RedirectResponse
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['verifikasi', 'mulai', 'selesai', 'batal'])],
        ]);

        $sebelum = $rapat->status;

        if (! $rapat->transisi($data['aksi'])) {
            return back()->withErrors([
                'rapat' => "Perubahan status dari {$rapat->labelStatus()} tidak diizinkan.",
            ]);
        }

        LogAudit::catat('ubah_status', 'rapat', $rapat->id, ['status' => $sebelum], ['status' => $rapat->status]);

        return back()->with('status', "Status rapat sekarang {$rapat->labelStatus()}.");
    }

    private function tierTersimpan(Rapat $rapat): array
    {
        return $rapat->tier()
            ->get(['menit_mulai', 'menit_akhir', 'potongan'])
            ->map(fn ($baris) => [
                'menit_mulai' => $baris->menit_mulai,
                'menit_akhir' => $baris->menit_akhir,
                'potongan' => (float) $baris->potongan,
            ])
            ->all();
    }
}
