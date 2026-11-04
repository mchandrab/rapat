<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RapatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', Rule::in(['rat', 'biasa'])],
            'judul' => ['required', 'string', 'max:150'],
            'tahun_acuan' => ['required', 'integer', 'between:2000,2100'],
            'tgl_rapat' => ['required', 'date'],
            'jam_mulai_presensi' => ['required', 'date_format:H:i'],
            'jam_mulai_rat' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai_rat'],
            'lokasi' => ['required', 'string', 'max:150'],
            'alamat_lokasi' => ['required', 'string', 'max:500'],
            // D-034: Mode 2x (Datang–Pulang) dipakai untuk agenda yang membayar transport saat pulang.
            'mode_presensi' => ['required', Rule::in(['1x', '2x'])],
            'mode_kelayakan' => ['required', Rule::in(['A', 'B'])],
            'minimal_simpanan_tahunan' => ['required', 'numeric', 'min:0'],
            'simpanan_per_bulan' => ['required', 'numeric', 'min:0'],
            'gunakan_proporsional' => ['required', 'boolean'],
            'batas_kolektibilitas' => ['required', 'integer', 'between:1,5'],
            'wajib_pinjaman_lancar' => ['required', 'boolean'],
            'basis_kuorum' => ['required', Rule::in(['semua_biasa', 'terverifikasi'])],
            'persen_kuorum' => ['required', 'integer', 'between:1,100'],
            'tambahan_kuorum' => ['required', 'integer', 'min:0'],
            'jumlah_pemenang_doorprize' => ['required', 'integer', 'between:1,100'],
            'transport_dasar' => ['required', 'numeric', 'min:0'],
            'batas_peringatan_kas' => ['nullable', 'numeric', 'min:0'],
            'toleransi_menit' => ['required', 'integer', 'min:0'],
            'tier' => ['nullable', 'array'],
            'tier.*.menit_mulai' => ['required', 'integer', 'min:1'],
            'tier.*.menit_akhir' => ['required', 'integer', 'gte:tier.*.menit_mulai'],
            'tier.*.potongan' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai acara.',
            'tier.*.menit_akhir.gte' => 'Menit akhir tier harus lebih besar atau sama dengan menit mulai.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $tier = collect($this->input('tier', []))
            ->filter(fn ($baris) => filled($baris['menit_mulai'] ?? null)
                || filled($baris['menit_akhir'] ?? null)
                || filled($baris['potongan'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'gunakan_proporsional' => $this->boolean('gunakan_proporsional'),
            'wajib_pinjaman_lancar' => $this->boolean('wajib_pinjaman_lancar'),
            'mode_presensi' => strtolower(trim((string) $this->input('mode_presensi'))),
            'mode_kelayakan' => strtoupper(trim((string) $this->input('mode_kelayakan'))),
            'jam_selesai' => $this->input('jam_selesai') ?: null,
            'tier' => $tier,
        ]);
    }

    /**
     * DATABASE_SCHEMA: rentang tier transport tidak boleh tumpang tindih.
     * BR-008: tier juga harus bersambung mulai menit ke-1 setelah toleransi agar tidak ada celah
     * yang membuat orang sangat telat tetap mendapat transport penuh.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rentang = collect($this->input('tier', []))
                ->map(fn ($baris) => [(int) ($baris['menit_mulai'] ?? 0), (int) ($baris['menit_akhir'] ?? 0)])
                ->sortBy(0)
                ->values();

            $harapanMulai = 1;

            foreach ($rentang as [$mulai, $akhir]) {
                if ($mulai !== $harapanMulai) {
                    $validator->errors()->add('tier', 'Tier potongan harus bersambung mulai menit ke-1 tanpa celah dan tanpa tumpang tindih.');

                    return;
                }

                $harapanMulai = $akhir + 1;
            }
        });
    }
}
