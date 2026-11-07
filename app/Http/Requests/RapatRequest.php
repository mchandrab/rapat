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
            'tanggal_acuan_saldo' => ['required', 'date', 'before_or_equal:tgl_rapat'],
            'tgl_rapat' => ['required', 'date'],
            'jam_mulai_presensi' => ['required', 'date_format:H:i'],
            'jam_mulai_rat' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai_rat'],
            'lokasi' => ['required', 'string', 'max:150'],
            'alamat_lokasi' => ['required', 'string', 'max:500'],
            'tautan_google_maps' => [
                'nullable',
                'url:http,https',
                'starts_with:https://',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                    $path = strtolower((string) parse_url((string) $value, PHP_URL_PATH));
                    $tautanMaps = in_array($host, ['maps.app.goo.gl', 'maps.google.com'], true)
                        || ($host === 'goo.gl' && str_starts_with($path, '/maps'))
                        || (in_array($host, ['www.google.com', 'google.com'], true) && str_starts_with($path, '/maps'));

                    if (! $tautanMaps) {
                        $fail('Tautan lokasi harus berasal dari Google Maps.');
                    }
                },
            ],
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
            'jam_mulai_presensi.date_format' => 'Jam mulai presensi harus menggunakan format jam dan menit.',
            'jam_mulai_rat.date_format' => 'Jam mulai acara harus menggunakan format jam dan menit.',
            'jam_selesai.date_format' => 'Jam selesai harus menggunakan format jam dan menit.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai acara.',
            'tanggal_acuan_saldo.before_or_equal' => 'Tanggal acuan saldo tidak boleh setelah tanggal rapat.',
            'tautan_google_maps.url' => 'Tautan Google Maps harus berupa URL yang valid.',
            'tautan_google_maps.starts_with' => 'Tautan Google Maps harus menggunakan HTTPS.',
            'tier.*.menit_akhir.gte' => 'Menit akhir tier harus lebih besar atau sama dengan menit mulai.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $jam = static function (mixed $value): mixed {
            $value = is_string($value) ? trim($value) : $value;

            return is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)
                ? substr($value, 0, 5)
                : $value;
        };

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
            'jam_mulai_presensi' => $jam($this->input('jam_mulai_presensi')),
            'jam_mulai_rat' => $jam($this->input('jam_mulai_rat')),
            'jam_selesai' => $this->input('jam_selesai') ? $jam($this->input('jam_selesai')) : null,
            'tautan_google_maps' => $this->input('tautan_google_maps') ?: null,
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
