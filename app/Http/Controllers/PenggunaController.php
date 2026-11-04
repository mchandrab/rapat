<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * D-035: Admin mengelola akun petugas. Role tetap empat; anggota bukan akun login.
 * Akun Admin tidak dapat menghapus atau menurunkan dirinya sendiri.
 */
class PenggunaController extends Controller
{
    private const ROLE = ['admin', 'helpdesk', 'scanner', 'keuangan'];

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

    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors(['pengguna' => 'Tidak dapat menghapus akun sendiri.']);
        }

        if ($user->role === 'admin' && User::query()->where('role', 'admin')->count() === 1) {
            return back()->withErrors(['pengguna' => 'Minimal satu akun Admin harus tetap ada.']);
        }

        LogAudit::catat('hapus', 'user', $user->id, $user->only(['name', 'username', 'role']), null);
        $user->delete();

        return back()->with('status', "Akun {$user->name} dihapus.");
    }
}
