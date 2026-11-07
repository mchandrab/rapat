<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnggotaRequest extends FormRequest
{
    public const KOLOM_HELPDESK = [
        'no_hp',
        'email',
        'jenis_anggota',
        'status_anggota',
        'alamat',
    ];

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin'
            || ($this->user()?->role === 'helpdesk' && $this->routeIs('anggota.update'));
    }

    /**
     * Aturan dipakai bersama oleh form dan impor XLSX agar keduanya setara.
     */
    public static function aturan(?int $abaikanId = null, bool $izinkanNbaAda = false): array
    {
        $nba = ['required', 'string', 'regex:/^[0-9]{15}$/'];

        if (! $izinkanNbaAda) {
            $nba[] = Rule::unique('anggota', 'no_anggota')->ignore($abaikanId);
        }

        return [
            'no_anggota' => $nba,
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['nullable', 'string', 'regex:/^[0-9]{9,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'jenis_anggota' => ['required', Rule::in(['biasa', 'luar_biasa'])],
            'status_anggota' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'tgl_ja' => ['required', 'date'],
            'tgl_keluar' => ['nullable', 'date', 'after_or_equal:tgl_ja'],
            'simpanan_wajib' => ['required', 'numeric', 'min:0'],
            'kolektibilitas' => ['required', 'integer', 'between:0,5'],
            'pinjaman_lancar' => ['required', 'boolean'],
        ];
    }

    public function rules(): array
    {
        $aturan = self::aturan($this->route('anggota')?->id);

        if ($this->user()?->role === 'helpdesk') {
            return array_intersect_key($aturan, array_flip(self::KOLOM_HELPDESK));
        }

        return $aturan;
    }

    public function messages(): array
    {
        return self::pesan();
    }

    public static function pesan(): array
    {
        return [
            'no_anggota.required' => 'Kolom no_anggota wajib diisi.',
            'no_anggota.regex' => 'NBA harus tepat 15 digit angka tanpa titik.',
            'no_anggota.unique' => 'NBA sudah terdaftar pada master anggota.',
            'nama.required' => 'Kolom nama wajib diisi.',
            'no_hp.regex' => 'Kolom no_hp harus menghasilkan 9 sampai 15 digit setelah karakter selain angka dibersihkan.',
            'email.email' => 'Kolom email harus berupa alamat email yang valid; kosongkan jika tidak ada dan jangan isi dengan tanda minus.',
            'jenis_anggota.in' => 'Kolom jenis_anggota hanya boleh berisi biasa atau luar_biasa.',
            'status_anggota.in' => 'Kolom status_anggota hanya boleh berisi aktif atau nonaktif.',
            'tgl_ja.required' => 'Kolom tgl_ja wajib diisi.',
            'tgl_ja.date' => 'Kolom tgl_ja harus berupa tanggal yang valid.',
            'tgl_keluar.date' => 'Kolom tgl_keluar harus berupa tanggal yang valid atau dikosongkan.',
            'tgl_keluar.after_or_equal' => 'Kolom tgl_keluar tidak boleh lebih awal dari tgl_ja.',
            'simpanan_wajib.required' => 'Kolom simpanan_wajib wajib diisi.',
            'simpanan_wajib.numeric' => 'Kolom simpanan_wajib harus berupa angka tanpa simbol Rp.',
            'simpanan_wajib.min' => 'Kolom simpanan_wajib tidak boleh negatif.',
            'kolektibilitas.required' => 'Kolom kolektibilitas wajib diisi dengan angka 0 sampai 5.',
            'kolektibilitas.integer' => 'Kolom kolektibilitas harus berupa bilangan bulat 0 sampai 5.',
            'kolektibilitas.between' => 'Kolom kolektibilitas harus berada antara 0 sampai 5; gunakan 0 hanya untuk anggota tanpa pinjaman.',
            'pinjaman_lancar.required' => 'Kolom pinjaman_lancar wajib diisi dengan ya atau tidak.',
            'pinjaman_lancar.boolean' => 'Kolom pinjaman_lancar wajib diisi dengan ya atau tidak.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'no_anggota' => preg_replace('/[.\s]/', '', (string) $this->input('no_anggota')),
            'no_hp' => self::normalisasiNomorHp($this->input('no_hp')),
            'jenis_anggota' => str_replace(' ', '_', strtolower(trim((string) $this->input('jenis_anggota')))),
            'status_anggota' => strtolower(trim((string) $this->input('status_anggota'))),
            'pinjaman_lancar' => filter_var($this->input('pinjaman_lancar'), FILTER_VALIDATE_BOOLEAN),
            'tgl_keluar' => $this->input('tgl_keluar') ?: null,
        ]);
    }

    public static function normalisasiNomorHp(mixed $nilai): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nilai);

        return $angka === '' ? null : $angka;
    }
}
