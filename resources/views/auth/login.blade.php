@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <div class="card card-md erat-auth-card">
        <div class="card-body">
            <div class="text-center mb-4">
                <span class="erat-login-mark text-white d-inline-flex align-items-center justify-content-center fw-bold mb-3">eR</span>
                <h2 class="h2 mb-1">Masuk eRAT</h2>
                <div class="text-secondary">Manajemen Rapat Anggota Tahunan</div>
            </div>

            @if (session('status'))
                <div class="alert alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}"
                           class="form-control @error('username') is-invalid @enderror"
                           autocomplete="username" maxlength="50" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Kata sandi</label>
                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                </div>
                <div class="mb-3">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="remember" value="1">
                        <span class="form-check-label">Ingat saya di perangkat ini</span>
                    </label>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary w-100">Masuk</button>
                </div>
            </form>
        </div>
    </div>
@endsection
