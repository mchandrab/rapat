@extends('layouts.app')

@section('title', 'Pengguna')
@section('pretitle', 'Pengaturan')

@section('content')
    @include('partials.pengaturan-nav')

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Akun petugas</h3>
            <div class="card-actions text-secondary small">{{ $daftar->count() }} akun</div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($daftar as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="text-secondary font-monospace">{{ $user->username }}</td>
                        <td><span class="badge bg-secondary-lt text-secondary">{{ $user->role }}</span></td>
                        <td>
                            <span class="badge {{ $user->active ? 'bg-success-lt text-success' : 'bg-secondary-lt text-secondary' }}">
                                {{ $user->active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('pengguna.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                    Ubah
                                </a>
                                @if (! $user->is(auth()->user()))
                                    <form method="POST" action="{{ route('pengguna.status', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="active" value="{{ $user->active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-sm {{ $user->active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                            {{ $user->active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('pengguna.destroy', $user) }}"
                                      onsubmit="return confirm('Hapus akun {{ $user->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Tambah pengguna</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengguna.store') }}" autocomplete="off">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="name">Nama</label>
                        <input id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" name="username" class="form-control" value="{{ old('username') }}"
                               minlength="3" maxlength="50" pattern="[a-zA-Z0-9._-]+" autocomplete="off" required>
                        <div class="form-hint">Huruf, angka, titik, garis bawah, atau tanda minus.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-select" required>
                            @foreach ($role as $pilihan)
                                <option value="{{ $pilihan }}" @selected(old('role') === $pilihan)>{{ $pilihan }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="password">Sandi awal</label>
                        <input id="password" type="password" name="password" class="form-control" required minlength="8">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Tambah pengguna</button>
                <span class="text-secondary small ms-2">Anggota koperasi tidak dibuatkan akun.</span>
            </form>
        </div>
    </div>
@endsection
