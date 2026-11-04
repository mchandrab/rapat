@extends('layouts.app')

@use('App\Support\Demo')

@section('title', 'Koreksi SHU')
@section('pretitle', 'Pengelolaan Admin')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Koreksi SHU {{ $shu->anggota?->nama }}</h3>
        </div>
        <div class="card-body">
            <div class="datagrid mb-4">
                <div class="datagrid-item">
                    <div class="datagrid-title">NBA</div>
                    <div class="datagrid-content">{{ $shu->anggota?->nbaTampil() }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Tahun buku</div>
                    <div class="datagrid-content">{{ $shu->tahun_buku }}</div>
                </div>
                <div class="datagrid-item">
                    <div class="datagrid-title">Total sekarang</div>
                    <div class="datagrid-content">{{ Demo::rupiah($shu->total()) }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('shu.update', $shu) }}">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="balas_jasa_saham">Balas Jasa Saham</label>
                        <input id="balas_jasa_saham" name="balas_jasa_saham" type="number" min="0" step="0.01"
                               class="form-control" value="{{ old('balas_jasa_saham', $shu->balas_jasa_saham) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="balas_jasa_pinjaman">Balas Jasa Pinjaman</label>
                        <input id="balas_jasa_pinjaman" name="balas_jasa_pinjaman" type="number" min="0" step="0.01"
                               class="form-control" value="{{ old('balas_jasa_pinjaman', $shu->balas_jasa_pinjaman) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="alasan">Alasan koreksi</label>
                        <textarea id="alasan" name="alasan" class="form-control" rows="3" minlength="10" required>{{ old('alasan') }}</textarea>
                        <div class="form-hint">Minimal 10 karakter. Nilai sebelum, sesudah, dan alasan disimpan di Audit.</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Simpan koreksi</button>
                <a href="{{ route('shu.index', ['tahun_buku' => $shu->tahun_buku]) }}" class="btn btn-link mt-3">Batal</a>
            </form>
        </div>
    </div>
@endsection
