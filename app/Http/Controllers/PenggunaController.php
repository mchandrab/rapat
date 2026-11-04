<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * D-035: Admin mengelola akun petugas. Role tetap empat; anggota bukan akun login.
 * Akun Admin tidak dapat menghapus atau menurunkan dirinya sendiri.
 */
class PenggunaController extends Controller
{
    private const ROLE = ['admin', 'helpdesk', 'scanner', 'keuangan'];

    private const OPERATIONAL_REFERENCES = [
        'log_audit' => ['aktor_user_id'],
        'rapat' => ['dibuka_oleh'],
        'peserta' => ['verified_by'],
        'log_undangan' => ['petugas_user_id'],
        'kas_scanner' => ['scanner_user_id', 'diserahkan_oleh', 'ditutup_oleh'],
        'presensi' => ['dicatat_oleh'],
        'transport_pembayaran' => ['dibayar_oleh'],
        'pengaturan_integrasi' => ['diubah_oleh'],
        'shu' => ['diubah_oleh'],
        'shu_publik' => ['dibuka_oleh'],
        'doorprize_pemenang' => ['dicatat_oleh'],
    ];

    public function index(): View
    {
        return view('admin.pengguna', [
            'daftar' => User::query()->orderBy('role')->orderBy('name')->get(),
            'role' => self::ROLE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'role' => ['required', Rule::in(self::ROLE)],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'username.unique' => 'Username ini sudah dipakai akun lain.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda minus.',
            'password.min' => 'Sandi minimal 8 karakter.',
        ]);

        $user = User::create($data);
        LogAudit::catat('buat', 'user', $user->id, null, $user->only(['name', 'username', 'role']));

        return back()->with('status', "Akun {$user->name} dibuat.");
    }

    public function edit(User $user): View
    {
        return view('admin.pengguna-ubah', [
            'user' => $user,
            'role' => self::ROLE,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLE)],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'username.unique' => 'Username ini sudah dipakai akun lain.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda minus.',
            'password.min' => 'Sandi minimal 8 karakter.',
        ]);

        if ($user->is(auth()->user()) && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Tidak dapat menurunkan role akun sendiri.']);
        }

        if ($user->active && $user->role === 'admin' && $data['role'] !== 'admin' && $this->activeAdminCount() === 1) {
            return back()->withErrors(['role' => 'Minimal satu akun Admin aktif harus tetap ada.']);
        }

        $sebelum = $user->only(['name', 'username', 'role']);
        $sandibaru = filled($data['password'] ?? null);
        unset($data['password']);

        $user->fill($data);
        if ($sandibaru) {
            $user->password = $request->input('password');
        }
        $user->save();
        LogAudit::catat('ubah', 'user', $user->id, $sebelum, $user->only(['name', 'username', 'role']));

        return redirect()
            ->route('pengguna.index')
            ->with('status', $sandibaru ? "Akun {$user->name} diperbarui dan sandinya diganti." : "Akun {$user->name} diperbarui.");
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $active = (bool) $data['active'];

        if (! $active && $user->is(auth()->user())) {
            return back()->withErrors(['pengguna' => 'Tidak dapat menonaktifkan akun sendiri.']);
        }

        if (! $active && $user->active && $user->role === 'admin' && $this->activeAdminCount() === 1) {
            return back()->withErrors(['pengguna' => 'Minimal satu akun Admin aktif harus tetap ada.']);
        }

        if ($user->active === $active) {
            return back();
        }

        $sebelum = $user->only(['name', 'username', 'role', 'active']);
        $user->forceFill([
            'active' => $active,
            'remember_token' => $active ? $user->remember_token : null,
        ])->save();
        LogAudit::catat($active ? 'aktifkan' : 'nonaktifkan', 'user', $user->id, $sebelum, $user->only(['name', 'username', 'role', 'active']));

        return back()->with('status', "Akun {$user->name} ".($active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['pengguna' => 'Tidak dapat menghapus akun sendiri.']);
        }

        if ($user->active && $user->role === 'admin' && $this->activeAdminCount() === 1) {
            return back()->withErrors(['pengguna' => 'Minimal satu akun Admin aktif harus tetap ada.']);
        }

        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($this->hasOperationalHistory($user)) {
                throw ValidationException::withMessages([
                    'pengguna' => 'Akun tidak dapat dihapus karena memiliki riwayat operasional. Riwayat dan identitas petugas harus tetap tersimpan.',
                ]);
            }

            $sebelum = $user->only(['name', 'username', 'role']);
            $user->delete();
            LogAudit::catat('hapus', 'user', $user->id, $sebelum, null);
        });

        return back()->with('status', "Akun {$user->name} dihapus.");
    }

    private function hasOperationalHistory(User $user): bool
    {
        foreach (self::OPERATIONAL_REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                if (DB::table($table)->where($column, $user->id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function activeAdminCount(): int
    {
        return User::query()->where('role', 'admin')->where('active', true)->count();
    }
}
