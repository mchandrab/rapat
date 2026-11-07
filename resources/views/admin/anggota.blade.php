@extends('layouts.app')

@section('title', 'Anggota')
@section('pretitle', 'Pelayanan Anggota')

@section('actions')
    @if (auth()->user()->role === 'admin')
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalImpor">
            <i class="ti ti-upload me-1"></i>Impor XLSX
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
            <i class="ti ti-plus me-1"></i>Tambah Anggota
        </button>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if (session('impor'))
        @php($impor = session('impor'))
        <div class="alert {{ $impor['galat'] === [] ? 'alert-success' : 'alert-warning' }}" role="status">
            <div class="fw-bold">
                Impor selesai: {{ $impor['dibuat'] }} anggota baru, {{ $impor['diperbarui'] }} diperbarui,
                {{ count($impor['galat']) }} baris ditolak.
            </div>
            @if ($impor['galat'] !== [])
                <ul class="mb-0 mt-2">
                    @foreach (array_slice($impor['galat'], 0, 50) as $galat)
                        <li>Baris {{ $galat['baris'] }}: {{ $galat['pesan'] }}</li>
                    @endforeach
                </ul>
                @if (count($impor['galat']) > 50)
                    <div class="mt-2">Menampilkan 50 kesalahan pertama dari {{ count($impor['galat']) }} baris yang ditolak.</div>
                @endif
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

    <div class="text-secondary mb-3">
        Master anggota dan status verifikasi undangan dalam satu halaman. NBA disimpan 15 digit dan ditampilkan 6.3.3.3.
        Impor tahun berikutnya memperbarui anggota dengan NBA yang sama tanpa menghapus data lain.
    </div>

    <livewire:peserta-verifikasi />

    <p class="text-secondary small mt-2">
        Kelayakan dan status verifikasi berasal dari peserta rapat aktif. Status keanggotaan Aktif/Nonaktif selalu dibaca dari
        master terbaru dan anggota Nonaktif ditolak saat presensi.
    </p>

    @if (auth()->user()->role === 'admin')
        <div class="modal fade" id="modalImpor" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <form id="formImporAnggota" method="POST" action="{{ route('anggota.impor') }}" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 id="judulImporAnggota" class="modal-title">Impor Anggota (XLSX)</h2>
                        <button id="tutupImporAnggota" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div id="prosesImporAnggota" class="alert alert-primary d-none" role="status" aria-live="polite">
                            <div class="d-flex align-items-start gap-3">
                                <span class="spinner-border spinner-border-sm mt-1" aria-hidden="true"></span>
                                <div class="flex-fill">
                                    <div class="fw-bold">Data sedang diunggah dan diproses</div>
                                    <div id="statusImporAnggota" class="small mt-1">Menyiapkan berkas...</div>
                                    <div class="progress progress-sm mt-3" aria-hidden="true">
                                        <div class="progress-bar progress-bar-indeterminate"></div>
                                    </div>
                                    <div class="small mt-2">
                                        Berkas sekitar 11.000 baris dapat memerlukan beberapa menit. Jangan tutup halaman atau mengirim ulang berkas.
                                        Ringkasan berhasil dan ditolak akan muncul setelah proses selesai.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3 mb-4">
                            <span class="avatar bg-primary-lt text-primary rounded">
                                <i class="ti ti-file-spreadsheet fs-2" aria-hidden="true"></i>
                            </span>
                            <div>
                                <div class="fw-bold">Gunakan template terbaru</div>
                                <div class="text-secondary small mb-2">
                                    Aturan setiap kolom, contoh pengisian, dan dropdown tersedia di sheet <b>Petunjuk</b>.
                                </div>
                                <a href="{{ route('anggota.template') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-download me-1" aria-hidden="true"></i>Unduh Template XLSX
                                </a>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="berkas">Pilih berkas yang sudah diisi</label>
                            <input id="berkas" name="berkas" type="file" accept=".xlsx" class="form-control" required>
                            <div class="form-hint">Format XLSX, maksimal 5 MB. Data dengan NBA yang sama akan diperbarui, bukan digandakan.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button id="batalImporAnggota" type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button id="tombolImporAnggota" type="submit" class="btn btn-primary">
                            <span class="teks-siap"><i class="ti ti-upload me-1" aria-hidden="true"></i>Impor Data</span>
                            <span class="teks-proses d-none"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="{{ route('anggota.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title">Tambah Anggota</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @include('admin.partials.anggota-form', ['anggota' => null, 'awalan' => 'baru-'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Anggota</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        (() => {
            const form = document.getElementById('formImporAnggota');

            if (! form) {
                return;
            }

            const modal = document.getElementById('modalImpor');
            const berkas = document.getElementById('berkas');
            const proses = document.getElementById('prosesImporAnggota');
            const status = document.getElementById('statusImporAnggota');
            const judul = document.getElementById('judulImporAnggota');
            const tombol = document.getElementById('tombolImporAnggota');
            const batal = document.getElementById('batalImporAnggota');
            const tutup = document.getElementById('tutupImporAnggota');
            let berjalan = false;

            modal?.addEventListener('hide.bs.modal', (event) => {
                if (berjalan) {
                    event.preventDefault();
                }
            });

            form.addEventListener('submit', (event) => {
                if (berjalan) {
                    event.preventDefault();

                    return;
                }

                berjalan = true;
                form.setAttribute('aria-busy', 'true');
                proses?.classList.remove('d-none');
                tombol.disabled = true;
                batal.disabled = true;
                tutup.classList.add('d-none');
                berkas.classList.add('pe-none');
                berkas.setAttribute('aria-disabled', 'true');
                judul.textContent = 'Memproses Impor Anggota';
                tombol.querySelector('.teks-siap')?.classList.add('d-none');
                tombol.querySelector('.teks-proses')?.classList.remove('d-none');

                if (status && berkas.files[0]) {
                    status.textContent = `Memproses ${berkas.files[0].name}. Validasi dilakukan untuk setiap baris.`;
                }

                proses?.scrollIntoView({ block: 'nearest' });
            });
        })();
    </script>
@endsection
