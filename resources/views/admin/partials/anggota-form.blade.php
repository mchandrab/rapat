@php
    $anggota = $anggota ?? null;
    $awalan = $awalan ?? '';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}no_anggota">NBA (15 digit)</label>
        <input id="{{ $awalan }}no_anggota" name="no_anggota" inputmode="numeric" maxlength="19" required
               class="form-control @error('no_anggota') is-invalid @enderror"
               value="{{ old('no_anggota', $anggota?->no_anggota) }}">
        <div class="form-hint">Tanpa titik. Tampilan 6.3.3.3 hanya pada daftar.</div>
        @error('no_anggota')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}nama">Nama lengkap</label>
        <input id="{{ $awalan }}nama" name="nama" maxlength="100" required
               class="form-control @error('nama') is-invalid @enderror"
               value="{{ old('nama', $anggota?->nama) }}">
        @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}no_hp">Nomor WhatsApp</label>
        <input id="{{ $awalan }}no_hp" name="no_hp" maxlength="20"
               class="form-control @error('no_hp') is-invalid @enderror"
               value="{{ old('no_hp', $anggota?->no_hp) }}">
        @error('no_hp')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}email">Email</label>
        <input id="{{ $awalan }}email" name="email" type="email" maxlength="255"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $anggota?->email) }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}tgl_ja">Tanggal jadi anggota</label>
        <input id="{{ $awalan }}tgl_ja" name="tgl_ja" type="date" required
               class="form-control @error('tgl_ja') is-invalid @enderror"
               value="{{ old('tgl_ja', $anggota?->tgl_ja?->format('Y-m-d')) }}">
        @error('tgl_ja')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}tgl_keluar">Tanggal keluar (kosongkan bila aktif)</label>
        <input id="{{ $awalan }}tgl_keluar" name="tgl_keluar" type="date"
               class="form-control @error('tgl_keluar') is-invalid @enderror"
               value="{{ old('tgl_keluar', $anggota?->tgl_keluar?->format('Y-m-d')) }}">
        @error('tgl_keluar')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}jenis_anggota">Jenis anggota</label>
        <select id="{{ $awalan }}jenis_anggota" name="jenis_anggota" class="form-select">
            @foreach (['biasa' => 'Biasa', 'luar_biasa' => 'Luar Biasa'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected(old('jenis_anggota', $anggota?->jenis_anggota ?? 'biasa') === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="{{ $awalan }}status_anggota">Status keanggotaan</label>
        <select id="{{ $awalan }}status_anggota" name="status_anggota" class="form-select">
            @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected(old('status_anggota', $anggota?->status_anggota ?? 'aktif') === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-hint">Nonaktif tidak memengaruhi Layak/Tidak Layak, tetapi memblokir presensi.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $awalan }}simpanan_wajib">Simpanan wajib (Rp)</label>
        <input id="{{ $awalan }}simpanan_wajib" name="simpanan_wajib" type="number" min="0" step="0.01" required
               class="form-control @error('simpanan_wajib') is-invalid @enderror"
               value="{{ old('simpanan_wajib', $anggota?->simpanan_wajib) }}">
        <div class="form-hint">Saldo per 31 Desember tahun buku.</div>
        @error('simpanan_wajib')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $awalan }}kolektibilitas">Kolektibilitas</label>
        <select id="{{ $awalan }}kolektibilitas" name="kolektibilitas" class="form-select">
            @for ($kol = 1; $kol <= 5; $kol++)
                <option value="{{ $kol }}" @selected((int) old('kolektibilitas', $anggota?->kolektibilitas ?? 1) === $kol)>Kol {{ $kol }}</option>
            @endfor
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="{{ $awalan }}pinjaman_lancar">Pinjaman</label>
        <select id="{{ $awalan }}pinjaman_lancar" name="pinjaman_lancar" class="form-select">
            <option value="1" @selected((bool) old('pinjaman_lancar', $anggota?->pinjaman_lancar ?? true))>Lancar</option>
            <option value="0" @selected(! (bool) old('pinjaman_lancar', $anggota?->pinjaman_lancar ?? true))>Tidak lancar</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label" for="{{ $awalan }}alamat">Alamat</label>
        <input id="{{ $awalan }}alamat" name="alamat" maxlength="255"
               class="form-control @error('alamat') is-invalid @enderror"
               value="{{ old('alamat', $anggota?->alamat) }}">
        @error('alamat')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
