@extends('layouts.app')

@section('title', 'Integrasi')
@section('pretitle', 'Pengaturan Sistem')

@section('content')
    @include('partials.pengaturan-nav')

    <div class="alert alert-info">
        <i class="ti ti-lock me-1"></i>
        Hanya Admin yang dapat melihat dan mengubah halaman ini. Secret disimpan terenkripsi dan tidak pernah ditampilkan kembali —
        termasuk kepada Admin. Menyimpan form tidak mengirim pesan apa pun.
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">CAPTCHA</h3>
                    <div class="card-actions">
                        <span class="badge {{ $captcha?->siap() ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                            {{ $captcha?->siap() ? 'Aktif' : 'Belum aktif' }}
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('integrasi.captcha.simpan') }}">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="aktif" value="0">
                            <input class="form-check-input" type="checkbox" id="captchaAktif" name="aktif" value="1"
                                   @checked(old('aktif', $captcha?->aktif))>
                            <label class="form-check-label" for="captchaAktif">Aktifkan CAPTCHA pada Cek SHU publik</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="captchaProvider">Provider</label>
                            <select id="captchaProvider" name="provider" class="form-select @error('provider') is-invalid @enderror">
                                <option value="turnstile" @selected(old('provider', $captcha?->provider ?? 'turnstile') === 'turnstile')>
                                    Cloudflare Turnstile
                                </option>
                            </select>
                            @error('provider')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="siteKey">Site key (publik)</label>
                            <input id="siteKey" name="site_key" class="form-control @error('site_key') is-invalid @enderror"
                                   value="{{ old('site_key', $captcha?->site_key) }}" placeholder="0x4AAAAAAA...">
                            @error('site_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="secretCaptcha">Secret key baru</label>
                            <input id="secretCaptcha" name="secret" type="password" autocomplete="new-password"
                                   class="form-control @error('secret') is-invalid @enderror"
                                   placeholder="{{ $captcha?->terkonfigurasi() ? 'Tersimpan — kosongkan untuk mempertahankan' : 'Belum ada secret' }}">
                            @error('secret')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Rotasi atau hapus secret memerlukan aksi terpisah dengan konfirmasi.</div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>

                <div class="card-body border-top">
                    <div class="text-secondary small mb-2">
                        Status uji terakhir:
                        <span class="badge bg-secondary-lt text-secondary">{{ $captcha?->labelStatusUji() ?? 'Belum pernah diuji' }}</span>
                        @if ($captcha?->status_uji_pada)
                            <span class="ms-1">{{ $captcha->status_uji_pada->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    @if ($captcha?->status_uji_pesan)
                        <div class="text-secondary small mb-2">{{ $captcha->status_uji_pesan }}</div>
                    @endif

                    <form method="POST" action="{{ route('integrasi.captcha.uji') }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="tokenCaptcha">Token challenge untuk diuji</label>
                            <input id="tokenCaptcha" name="token" class="form-control @error('token') is-invalid @enderror"
                                   placeholder="Tempel token dari widget CAPTCHA">
                            @error('token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">Uji memanggil verifikasi vendor sungguhan, bukan menilai format key.</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary">Uji Verifikasi CAPTCHA</button>
                        </div>
                    </form>
                </div>

                <div class="card-footer d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('integrasi.captcha.nonaktif') }}"
                          onsubmit="return confirm('Nonaktifkan CAPTCHA dan hapus secret tersimpan?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Nonaktifkan</button>
                    </form>
                </div>
                <div class="card-footer text-secondary small">
                    Jika CAPTCHA tidak aktif atau gagal diverifikasi, Cek SHU menolak pencarian (fail closed) — tidak ada bypass.
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Fonnte WhatsApp</h3>
                    <div class="card-actions">
                        <span class="badge {{ $fonnte?->siap() ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                            {{ $fonnte?->siap() ? 'Aktif' : 'Belum aktif' }}
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('integrasi.fonnte.simpan') }}">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="mb-3 form-check form-switch">
                            <input type="hidden" name="aktif" value="0">
                            <input class="form-check-input" type="checkbox" id="fonnteAktif" name="aktif" value="1"
                                   @checked(old('aktif', $fonnte?->aktif))>
                            <label class="form-check-label" for="fonnteAktif">Aktifkan pengiriman undangan WhatsApp</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="fonnteLabel">Label akun (opsional)</label>
                            <input id="fonnteLabel" name="label" class="form-control @error('label') is-invalid @enderror"
                                   value="{{ old('label', $fonnte?->label) }}" placeholder="Contoh: Koperasi Kredit Rukun">
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="fonnteToken">Token API baru</label>
                            <input id="fonnteToken" name="token" type="password" autocomplete="new-password"
                                   class="form-control @error('token') is-invalid @enderror"
                                   placeholder="{{ $fonnte?->terkonfigurasi() ? 'Tersimpan — kosongkan untuk mempertahankan' : 'Belum ada token' }}">
                            @error('token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>

                <div class="card-body border-top">
                    <div class="text-secondary small mb-2">
                        Status uji terakhir:
                        <span class="badge bg-secondary-lt text-secondary">{{ $fonnte?->labelStatusUji() ?? 'Belum pernah diuji' }}</span>
                        @if ($fonnte?->status_uji_pada)
                            <span class="ms-1">{{ $fonnte->status_uji_pada->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    @if ($fonnte?->status_uji_pesan)
                        <div class="text-secondary small mb-2">{{ $fonnte->status_uji_pesan }}</div>
                    @endif

                    <form method="POST" action="{{ route('integrasi.fonnte.uji') }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label class="form-label" for="ujiNomor">Nomor penguji</label>
                            <input id="ujiNomor" name="nomor" class="form-control @error('nomor') is-invalid @enderror"
                                   value="{{ old('nomor') }}" placeholder="08xxxxxxxxxx">
                            @error('nomor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="konfirmasi" value="1" @checked(old('konfirmasi'))>
                                <span class="form-check-label">Saya mengirim satu pesan uji ke nomor ini.</span>
                            </label>
                            @error('konfirmasi')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-primary">Uji Kirim</button>
                        </div>
                    </form>
                </div>

                <div class="card-footer d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('integrasi.fonnte.nonaktif') }}"
                          onsubmit="return confirm('Nonaktifkan Fonnte dan hapus token tersimpan?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Nonaktifkan</button>
                    </form>
                </div>
                <div class="card-footer text-secondary small">
                    Kegagalan kirim undangan tidak mengubah status Verified; upaya gagal tetap tercatat.
                </div>
            </div>
        </div>
    </div>
@endsection
