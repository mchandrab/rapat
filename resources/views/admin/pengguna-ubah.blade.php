@extends('layouts.app')

@section('title', 'Ubah Pengguna')
@section('pretitle', 'Pengaturan')

@section('content')
    @include('partials.pengaturan-nav')

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ubah akun {{ $user->name }}</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('pengguna.update', $user) }}" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="name">Nama</label>
                        <input id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" name="username" class="form-control"
                               value="{{ old('username', $user->username) }}" minlength="3" maxlength="50"
                               pattern="[a-zA-Z0-9._-]+" autocomplete="off" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="role">Role</label>
                        <select id="role" name="role" class="form-select" required>
                            @foreach ($role as $pilihan)
                                <option value="{{ $pilihan }}" @selected(old('role', $user->role) === $pilihan)>
                                    {{ $pilihan }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="password">Sandi baru (opsional)</label>
                        <input id="password" type="password" name="password" class="form-control" minlength="8"
                               placeholder="Biarkan kosong bila tidak diganti">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Simpan</button>
                <a href="{{ route('pengguna.index') }}" class="btn btn-link mt-3">Batal</a>
            </form>
        </div>
    </div>
@endsection
