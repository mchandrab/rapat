@extends('layouts.guest')

@use('App\Support\Demo')

@section('title', 'Cek SHU')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h1 class="h2 text-center mb-1">Cek SHU Anggota</h1>
            <p class="text-secondary text-center mb-4">
                Masukkan NBA dan tahun buku. Nominal SHU hanya dapat dilihat selama Admin membuka cek publik.
            </p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('hasil'))
                @php($hasil = session('hasil'))
                <div class="alert alert-success" role="status">
                    <div class="fw-bold mb-2">SHU tahun buku {{ $hasil['tahun_buku'] }}</div>
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">NBA</div>
                            <div class="datagrid-content">{{ $hasil['nba'] }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Balas jasa saham</div>
                            <div class="datagrid-content">{{ Demo::rupiah($hasil['saham']) }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Balas jasa pinjaman</div>
                            <div class="datagrid-content">{{ Demo::rupiah($hasil['pinjaman']) }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Total SHU</div>
                            <div class="datagrid-content fw-bold">{{ Demo::rupiah($hasil['total']) }}</div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($tahunAktif->isEmpty())
                <div class="alert alert-secondary mb-0">
                    <i class="ti ti-lock me-1"></i>Cek SHU publik belum dibuka Admin. Silakan hubungi petugas koperasi.
                </div>
            @else
                @unless ($captchaSiap)
                    <div class="alert alert-warning">
                        <i class="ti ti-alert-triangle me-1"></i>Layanan CAPTCHA belum siap, sehingga pencarian belum dapat
                        dilayani. Hubungi Admin koperasi.
                    </div>
                @endunless

                <form method="POST" action="{{ route('cek-shu.cari') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="tahun_buku">Tahun buku</label>
                        <select id="tahun_buku" name="tahun_buku" class="form-select" required>
                            @foreach ($tahunAktif as $tahun)
                                <option value="{{ $tahun }}" @selected((int) old('tahun_buku') === (int) $tahun)>{{ $tahun }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="nba">NBA</label>
                        <input id="nba" name="nba" class="form-control" inputmode="numeric" autocomplete="off"
                               placeholder="020006000010339" value="{{ old('nba') }}" required>
                        <div class="form-hint">15 digit, boleh memakai titik.</div>
                    </div>

                    @if ($captchaSiap)
                        <div class="mb-3">
                            <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-language="id"></div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary w-100" @disabled(! $captchaSiap)>
                        <i class="ti ti-search me-1"></i>Cari SHU
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="text-secondary text-center small mt-3">
        CAPTCHA bukan bukti kepemilikan NBA. Jangan bagikan NBA Anda kepada orang lain.
    </div>

    @if ($captchaSiap)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection
