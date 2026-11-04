@extends('layouts.app')

@use('App\Support\Demo')

@section('title', 'Data SHU')
@section('pretitle', 'Pengelolaan Admin')

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ route('shu.template') }}">
        <i class="ti ti-download me-1"></i>Template XLSX
    </a>
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalManualShu">
        <i class="ti ti-plus me-1"></i>Input Manual
    </button>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalImporShu">
        <i class="ti ti-upload me-1"></i>Impor Data SHU
    </button>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if (session('impor'))
        @php($impor = session('impor'))
        <div class="alert {{ $impor['galat'] === [] ? 'alert-success' : 'alert-warning' }}" role="status">
            <div class="fw-bold">
                Impor selesai: {{ $impor['dibuat'] }} baris baru, {{ $impor['diperbarui'] }} diperbarui,
                {{ count($impor['galat']) }} baris ditolak.
            </div>
            @if ($impor['galat'] !== [])
                <ul class="mb-0 mt-2">
                    @foreach ($impor['galat'] as $galat)
                        <li>Baris {{ $galat['baris'] }}: {{ $galat['pesan'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold">Periksa kembali isian:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info">
        <i class="ti ti-info-circle me-1"></i>
        SHU hanya dikelola Admin. Impor ulang tahun buku yang sama memperbarui baris yang sudah ada dan tidak
        menghapus tahun buku lain. Setiap impor dan perubahan tercatat di log audit.
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <form method="GET" action="{{ route('shu.index') }}" class="card">
                <div class="card-body">
                    <label class="form-label" for="tahun_buku">Tahun buku</label>
                    <div class="input-group">
                        <input id="tahun_buku" name="tahun_buku" type="number" min="2000" max="2100"
                               class="form-control" value="{{ $tahun }}" required>
                        <button type="submit" class="btn btn-outline-secondary">Tampilkan</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-bold">Cek SHU publik tahun buku {{ $tahun }}</div>
                        <span class="badge {{ $publik->aktif ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">
                            {{ $publik->aktif ? 'Terbuka' : 'Tertutup' }}
                        </span>
                    </div>

                    @if ($publik->aktif)
                        <div class="text-secondary small mb-3">
                            Dibuka {{ $publik->dibuka_at?->format('d/m/Y H:i') }}.
                            Anggota dapat mengecek SHU dengan NBA + CAPTCHA.
                        </div>
                        <form method="POST" action="{{ route('shu.publik.tutup') }}">
                            @csrf
                            <input type="hidden" name="tahun_buku" value="{{ $tahun }}">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="ti ti-lock me-1"></i>Tutup cek publik
                            </button>
                        </form>
                    @else
                        <div class="text-secondary small mb-3">
                            Cek publik tertutup. Buka hanya setelah data SHU final; tidak terikat status rapat.
                        </div>
                        <form method="POST" action="{{ route('shu.publik.buka') }}"
                              onsubmit="return confirm('Buka cek SHU publik tahun buku {{ $tahun }}? Anggota bisa mencari SHU dengan NBA.')">
                            @csrf
                            <input type="hidden" name="tahun_buku" value="{{ $tahun }}">
                            <button type="submit" class="btn btn-primary btn-sm" @disabled($daftar->total() === 0)>
                                <i class="ti ti-world me-1"></i>Buka cek publik
                            </button>
                            @if ($daftar->total() === 0)
                                <span class="text-secondary small ms-2">Impor data SHU lebih dulu.</span>
                            @endif
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">SHU Tahun Buku {{ $tahun }}</h3>
            <div class="card-actions text-secondary small">{{ $daftar->total() }} baris</div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter mb-0">
                <thead>
                <tr>
                    <th>NBA</th>
                    <th>Nama</th>
                    <th class="text-end">Balas Jasa Saham</th>
                    <th class="text-end">Balas Jasa Pinjaman</th>
                    <th class="text-end">Total</th>
                    <th>Diperbarui</th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($daftar as $row)
                    <tr>
                        <td class="text-nowrap">{{ $row->anggota?->nbaTampil() }}</td>
                        <td>{{ $row->anggota?->nama }}</td>
                        <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->balas_jasa_saham) }}</td>
                        <td class="text-end text-nowrap">{{ Demo::rupiah((float) $row->balas_jasa_pinjaman) }}</td>
                        <td class="text-end text-nowrap fw-bold">{{ Demo::rupiah($row->total()) }}</td>
                        <td class="text-secondary small text-nowrap">{{ $row->updated_at?->format('d/m/Y H:i') }}</td>
                        <td><a href="{{ route('shu.edit', $row) }}" class="btn btn-sm btn-outline-secondary">Koreksi</a></td>
                    </tr>
                @empty
                    <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                            Belum ada data SHU untuk tahun buku {{ $tahun }}. Impor data SHU untuk memulai.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if ($daftar->total() > 0)
                    <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="text-end text-nowrap">{{ Demo::rupiah((float) $total->saham) }}</th>
                        <th class="text-end text-nowrap">{{ Demo::rupiah((float) $total->pinjaman) }}</th>
                        <th class="text-end text-nowrap">{{ Demo::rupiah((float) $total->saham + (float) $total->pinjaman) }}</th>
                        <th></th>
                        <th></th>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="card-footer text-secondary small">
            SHU dan log audit perubahan Admin tidak terkena retensi 14 hari; retensi itu hanya untuk log pencarian publik.
        </div>
    </div>

    <div class="mt-3">{{ $daftar->links('pagination::bootstrap-5') }}</div>

    <div class="modal fade" id="modalManualShu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('shu.manual.simpan') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title">Input SHU Manual</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="manual_tahun_buku">Tahun buku</label>
                        <input id="manual_tahun_buku" name="tahun_buku" type="number" min="2000" max="2100"
                               class="form-control" value="{{ old('tahun_buku', $tahun) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="manual_no_anggota">NBA</label>
                        <input id="manual_no_anggota" name="no_anggota" class="form-control"
                               value="{{ old('no_anggota') }}" placeholder="020006000010339" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="manual_saham">Balas Jasa Saham</label>
                            <input id="manual_saham" name="balas_jasa_saham" type="number" min="0" step="0.01"
                                   class="form-control" value="{{ old('balas_jasa_saham', 0) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="manual_pinjaman">Balas Jasa Pinjaman</label>
                            <input id="manual_pinjaman" name="balas_jasa_pinjaman" type="number" min="0" step="0.01"
                                   class="form-control" value="{{ old('balas_jasa_pinjaman', 0) }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalImporShu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('shu.impor') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title">Impor Data SHU (XLSX)</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="impor_tahun_buku">Tahun buku</label>
                        <input id="impor_tahun_buku" name="tahun_buku" type="number" min="2000" max="2100"
                               class="form-control" value="{{ $tahun }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="berkas_shu">Berkas .xlsx</label>
                        <input id="berkas_shu" name="berkas" type="file" accept=".xlsx" class="form-control" required>
                    </div>
                    <div class="alert alert-info mb-0 small">
                        Kolom: <b>no_anggota</b>, <b>balas_jasa_saham</b>, <b>balas_jasa_pinjaman</b>.
                        NBA wajib diformat Teks 15 digit dan sudah ada di master anggota. Nominal harus angka;
                        sel kosong dianggap 0.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Impor</button>
                </div>
            </form>
        </div>
    </div>
@endsection
