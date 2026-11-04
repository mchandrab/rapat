<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnggotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
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
            'no_hp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'jenis_anggota' => ['required', Rule::in(['biasa', 'luar_biasa'])],
            'status_anggota' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'tgl_ja' => ['required', 'date'],
            'tgl_keluar' => ['nullable', 'date', 'after_or_equal:tgl_ja'],
            'simpanan_wajib' => ['required', 'numeric', 'min:0'],
            'kolektibilitas' => ['required', 'integer', 'between:1,5'],
            'pinjaman_lancar' => ['required', 'boolean'],
        ];
    }

    public function rules(): array
    {
        return self::aturan($this->route('anggota')?->id);
    }

    public function messages(): array
    {
        return [
            'no_anggota.regex' => 'NBA harus tepat 15 digit angka tanpa titik.',
            'no_anggota.unique' => 'NBA sudah terdaftar pada master anggota.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'no_anggota' => preg_replace('/[.\s]/', '', (string) $this->input('no_anggota')),
            'jenis_anggota' => str_replace(' ', '_', strtolower(trim((string) $this->input('jenis_anggota')))),
            'status_anggota' => strtolower(trim((string) $this->input('status_anggota'))),
            'pinjaman_lancar' => filter_var($this->input('pinjaman_lancar'), FILTER_VALIDATE_BOOLEAN),
            'tgl_keluar' => $this->input('tgl_keluar') ?: null,
        ]);
    }
}
