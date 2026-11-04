# DATABASE SCHEMA — Struktur Data eRAT

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Konvensi
MySQL `BIGINT UNSIGNED` primary key; uang `DECIMAL(15,2)`; waktu `DATETIME/TIMESTAMP`; nama kolom snake_case. Semua tabel operasional memiliki created_at/updated_at jika relevan. FK dan UNIQUE ditegakkan di database.

## `users`
`id`, `name`, `username VARCHAR(50) UNIQUE NOT NULL`, `email NULL` (kolom warisan, tidak dipakai autentikasi), `password`, `role VARCHAR(16)` dengan tepat empat nilai aplikasi (`admin`, `helpdesk`, `scanner`, `keuangan`), remember token, timestamps. Username disimpan huruf kecil dan dibatasi karakter `[a-z0-9._-]`; login dan manajemen akun tidak memakai email (D-041). Role menggunakan kolom langsung + middleware, tanpa tabel Spatie.

## `anggota`
`id`, `no_anggota VARCHAR(15) UNIQUE NOT NULL` (15 digit ASCII), `nama`, `no_hp`, `email`, `alamat`, `jenis_anggota ENUM(biasa,luar_biasa)`, `status_anggota ENUM(aktif,nonaktif)`, `tgl_ja DATE`, `tgl_keluar DATE NULL`, `simpanan_wajib DECIMAL(15,2)` (saldo per 31 Desember tahun buku aktif yang diimpor), `kolektibilitas`, `pinjaman_lancar BOOLEAN`, `created_at`, `updated_at`. Verifikasi, QR dan cara undangan **tidak** berada di master. Jangan menghapus record anggota saat impor ulang.

## `rapat`
`id`, `jenis`, `judul`, `tahun_buku`, `tanggal_acuan_saldo` (=31 Des tahun buku), `tgl_rapat`, `jam_mulai_presensi`, `jam_mulai_rat`, `jam_selesai`, `lokasi`, `status ENUM(draf,verifikasi,berlangsung,selesai,dibatalkan)`, `presensi_dibuka_at NULL`, `dibuka_oleh NULL FK users`, `mode_presensi ENUM(1x,2x)`, `mode_kelayakan ENUM(A,B)`, `minimal_simpanan_tahunan`, `simpanan_per_bulan`, `gunakan_proporsional`, `batas_kolektibilitas`, `wajib_pinjaman_lancar`, `basis_kuorum`, `persen_kuorum`, `tambahan_kuorum`, `jumlah_pemenang_doorprize` (bawaan 10), `transport_dasar`, `toleransi_menit`, `batas_hangus_menit NULL`, `batas_peringatan_kas NULL` (bawaan 5 × `transport_dasar`, D-025), `locked_at NULL`, `created_at`, `updated_at`. Aturan kriteria fleksibel dapat ditempatkan di tabel `rapat_kriteria` jika parameter bertambah; keputusan desain detail dibuat saat perencanaan migration.

**Catatan implementasi 30 Oktober 2026:** kolom jam disimpan `CHAR(5)` berformat `HH:MM` agar sama persis dengan input `type="time"` di SQLite maupun MySQL; `tanggal_acuan_saldo` dihitung server dari `tahun_buku` (bukan isian bebas); `rapat_transport_tier` dibuat dengan UNIQUE `(rapat_id, urutan)` dan validasi rentang tidak tumpang tindih di `RapatRequest`.

**Kuorum (3 November 2026):** `basis_kuorum = semua_biasa` memakai seluruh peserta rapat yang anggotanya berjenis `biasa` dan `status_anggota = aktif`; `terverifikasi` menambah saringan `peserta.status_verifikasi = verified`. Target = `floor(persen_kuorum × populasi / 100) + tambahan_kuorum`. Tidak ada tabel atau kolom penghitung kuorum: `App\Services\Kuorum` menghitung ulang dari `peserta`, `anggota`, dan `presensi` setiap kali dibaca, dan jam tercapainya diambil dari `presensi.datang_at` ke-`target`.

## `shu` (diterapkan 3 November 2026)
`id`, `anggota_id` FK `anggota` (cascade), `tahun_buku SMALLINT UNSIGNED`, `balas_jasa_saham DECIMAL(15,2)`, `balas_jasa_pinjaman DECIMAL(15,2)`, `diubah_oleh NULL` FK `users`, `created_at`, `updated_at`; UNIQUE `(anggota_id, tahun_buku)` dan indeks `tahun_buku`. Total per anggota **tidak disimpan** sebagai kolom agar tidak bisa berbeda dari komponennya; `Shu::total()` menjumlahkan keduanya saat dibaca. Impor XLSX (`App\Services\ImportShu`) memakai `firstOrNew` pada pasangan unik tersebut sehingga impor ulang memperbarui baris yang sama dan setiap perubahan dicatat di `log_audit` dengan entitas `shu`.

## `shu_publik` (diterapkan 3 November 2026)
`id`, `tahun_buku SMALLINT UNSIGNED` UNIQUE, `aktif BOOLEAN` (bawaan false), `dibuka_oleh NULL` FK `users`, `dibuka_at NULL`, `ditutup_at NULL`, `created_at`, `updated_at`. Satu baris per tahun buku; Admin membuka dan menutupnya lewat tombol, tanpa terikat status rapat. Perubahan saklar dicatat di `log_audit` dengan entitas `shu_publik`.

## `doorprize_pemenang` (diterapkan 3 November 2026)
`id`, `rapat_id FK rapat` (cascade), `peserta_id FK peserta` (cascade), `hasil ENUM(menang,gugur)`, `urutan SMALLINT NULL` (nomor pemenang, hanya untuk `menang`), `dicatat_oleh NULL` FK `users`, `created_at`, `updated_at`; UNIQUE `(rapat_id, peserta_id)`. Baris `gugur` (peserta sudah pulang saat dikonfirmasi) tetap disimpan agar peserta itu tidak muncul lagi di undian berikutnya pada rapat yang sama. Jumlah pemenang disimpan di `rapat.jumlah_pemenang_doorprize` (bawaan 10), bukan angka di kode. Tabel `doorprize_hadiah` tidak dibuat karena tidak ada daftar hadiah per item.

## `peserta` (status per rapat)
`id`, `rapat_id FK`, `anggota_id FK`, `hasil_kelayakan ENUM(layak,tidak_layak)`, `status_verifikasi ENUM(pending,verified,rejected)`, `alasan_override NULL`, `alasan_penolakan NULL`, `verified_by NULL FK users`, `verified_at NULL`, `snapshot_simpanan_wajib`, `snapshot_tgl_ja`, `snapshot_minimum_proporsional`, `snapshot_kolektibilitas`, `snapshot_pinjaman_lancar`, `snapshot_konfigurasi JSON`, `qr_token_hash CHAR(64) UNIQUE NULL`, `qr_token_ciphertext TEXT NULL`, `qr_dibuat_at NULL`, `created_at`, `updated_at`; UNIQUE `(rapat_id,anggota_id)`; index `(rapat_id,status_verifikasi)` dan `(rapat_id,hasil_kelayakan)`.

**Keputusan token (Fase 3, 30 Oktober 2026):** token QR dibuat dari `random_bytes(32)` (64 karakter hex), bukan turunan NBA. Disimpan ganda: `qr_token_hash` SHA-256 untuk pencarian saat scan, dan `qr_token_ciphertext` (Laravel `Crypt`, kunci `APP_KEY`) untuk cetak ulang token yang sama. Token mentah tidak pernah ditulis ke log/audit. Terbit tepat sekali saat status menjadi Verified; verifikasi ulang tidak mengganti token.

## `log_undangan`
`id`, `rapat_id FK`, `peserta_id FK`, `petugas_user_id FK`, `metode ENUM(wa,cetak)`, `status`, `waktu_aksi`, `provider_message_id NULL`, `detail_error NULL`, `created_at`; index per rapat & petugas.

**Catatan Fase 3:** status yang dipakai sekarang `dicetak` (cetak undangan) dan `gagal` (WhatsApp, karena Fonnte belum aktif). Status `terkirim`/`diterima` hanya boleh ditulis setelah integrasi penyedia memberi bukti (BR-005).

## `kas_scanner`
`id`, `rapat_id FK`, `scanner_user_id FK`, `kas_awal DECIMAL(15,2)`, `diserahkan_oleh FK users`, `diserahkan_pada`, `diterima_pada NULL` (konfirmasi Scanner, D-026), `sisa_fisik NULL`, `catatan_selisih NULL`, `status ENUM(menunggu,aktif,ditutup)`, `ditutup_oleh NULL FK users`, `ditutup_pada NULL`, `created_at`, `updated_at`; UNIQUE `(rapat_id,scanner_user_id)`. Tidak ada top-up, kas meja atau transfer.

**Catatan Fase 4 (30 Oktober 2026):** alokasi dibuat dengan `status=aktif` dan `diterima_pada` tetap `NULL` karena konfirmasi penerimaan oleh Scanner baru diputuskan kemudian (D-026, dikerjakan bersama Tutup Kas di Fase 8). Saldo tersedia dihitung di satu tempat (`KasScanner::sisa()` = `kas_awal` − Σ `transport_pembayaran.nominal`). Alokasi tidak dapat diubah atau dihapus lewat aplikasi; salah input nominal dikoreksi lewat `log_audit` (`aksi=koreksi`, `sebelum`/`sesudah` + alasan) tanpa kolom tambahan, dan hanya selama belum ada pembayaran.

**Catatan Fase 8 (3 November 2026):** status berjalan `menunggu` (baru dialokasikan) → `aktif` (Scanner menekan konfirmasi terima, `diterima_pada` terisi, audit `terima_kas`) → `ditutup` (Keuangan menutup, `sisa_fisik`/`catatan_selisih`/`ditutup_oleh`/`ditutup_pada` terisi, audit `tutup_kas`). Koreksi nominal diizinkan pada `menunggu` dan `aktif` selama belum ada pembayaran. `TutupKas::selisih()` = `sisa_fisik` - `sisa()`; selisih bukan nol mewajibkan catatan (D-031).

## `presensi`
`id`, `peserta_id UNIQUE FK`, `datang_at`, `pulang_at NULL`, `dicatat_oleh FK users`, `foto_path PRIVATE`, `tte_path PRIVATE`, `tier_id NULL`, `nominal_transport_final`, `kode_resi UNIQUE NULL`, `status_sinkron`, `created_at`, `updated_at`. Bukti foto/TTE wajib ada dan disimpan di disk privat (D-023).

**Catatan Fase 5 (30 Oktober 2026):** UNIQUE `peserta_id` mencegah presensi ganda (BR-006). `nominal_transport_final` menyimpan nominal saat scan sehingga perubahan tier setelahnya tidak mengubah presensi lama. `tier_id` menunjuk tier penyebab potongan. Nominal `0` berarti hangus (D-022): presensi tetap ada, tetapi tidak ada baris `transport_pembayaran`. `pulang_at` diisi saat pemindaian pulang Mode Presensi 2x dan `kode_resi` terbit saat pulang (D-034); `status_sinkron` selalu `tersinkron` karena tidak ada antrean offline (D-032).

**Bukti foto/TTE (D-023, FINAL):** `foto_path` dan `tte_path` wajib terisi; nilainya relatif terhadap disk privat `local` (`storage/app/private`), berbentuk `presensi/{rapat_id}/{peserta_id}/foto.<ext>` dan `presensi/{rapat_id}/{peserta_id}/tte.png`. Kedua kolom disembunyikan dari serialisasi model (`$hidden`) dan tidak pernah disajikan sebagai URL publik. Berkas disimpan sebelum transaksi berjalan; bila transaksi gagal, berkas dihapus kembali sehingga tidak ada bukti tanpa baris presensi. Retensi permanen, tanpa penghapusan otomatis.

**QR undangan (Fase 5.5):** gambar QR tidak disimpan sebagai berkas. `GET /undangan/{peserta}` membuat SVG dari `qr_token_ciphertext` yang didekripsi saat itu, sehingga cetak ulang selalu memakai token yang sama. Peringatan kas menipis memakai `rapat.batas_peringatan_kas`; bila `NULL` dihitung 5 × `transport_dasar` (D-025).

Zona waktu aplikasi **Asia/Jakarta** dan koneksi MySQL `+07:00` (`config/app.php`, `config/database.php`); tanpa ini jam datang dan jam mulai RAT berbeda 7 jam sehingga potongan tidak pernah dihitung. Scan mengunci baris `kas_scanner` (`lockForUpdate`) dan memeriksa ulang saldo di dalam transaksi agar dua scan bersamaan tidak membuat saldo minus; pelanggaran UNIQUE presensi diubah menjadi pesan "sudah tercatat", bukan error 500.

## `transport_pembayaran`
`id`, `peserta_id UNIQUE FK`, `kas_scanner_id FK`, `dibayar_oleh FK users`, `nominal`, `dibayar_pada`, `kode_resi UNIQUE`, `created_at`. **Catat pembayaran hanya jika benar-benar diserahkan dan terkonfirmasi; tidak membuat status pending sukses.** Mode Presensi 2x tidak memakai reservasi: baris pembayaran hanya dibuat saat pulang dan memotong kas Scanner yang mencatat pulang (D-034).

**Catatan Fase 5 (30 Oktober 2026):** baris hanya dibuat bila `nominal > 0`; transport hangus tidak membuat baris pembayaran (D-022). UNIQUE `peserta_id` menjaga satu pembayaran per peserta. Pembayaran dibuat dalam transaksi yang sama dengan `presensi` dan `log_audit` (`aksi=presensi`).

## Tabel tambahan
- `rapat_transport_tier`: `(rapat_id, menit_mulai, menit_akhir, potongan, urutan)`, rentang tidak boleh overlap.
- `shu`: `(anggota_id,tahun_buku)` UNIQUE; komponen balas jasa saham/pinjaman; total dihitung dari komponen (lihat bagian `shu`); dikelola Admin.
- `shu_publik`: saklar cek SHU publik per tahun buku, dibuka/ditutup Admin.
- `doorprize_pemenang`: FK rapat/peserta, `hasil` menang/gugur, peserta hanya punya satu hasil per rapat (lihat bagian `doorprize_pemenang`). Tidak ada tabel hadiah.
- `users`: username unik, password ter-hash, dan kolom role langsung; tidak memakai Spatie roles/permissions.
- `pengaturan`: global key/value, bukan pengganti parameter rapat.
- `log_audit`: append-only dalam operasi normal, aktor, waktu, entity, id, sebelum/sesudah, ip, alasan bila terkait. Hanya prosedur reset total Admin boleh mengosongkannya untuk memulai aplikasi dari nol (D-037).

## Relasi utama
`anggota 1:n peserta n:1 rapat`; `peserta 1:0..1 presensi`, `peserta 1:0..1 transport_pembayaran`, `peserta 1:n log_undangan`; `rapat 1:n kas_scanner`; `users 1:n kas_scanner(scanner_user_id)`; `kas_scanner 1:n transport_pembayaran`; `anggota 1:n shu`.

## Integritas kritis
NBA regex `^[0-9]{15}$`; validasi di FormRequest dan importer; semua FK menjamin konsistensi; unique cegah duplikat, snapshot riwayat immutable; transaksi atomik untuk pembayaran + debit kas. `saldo` tidak disimpan ganda kecuali cache yang dapat direkonsiliasi.


## `log_cek_shu` (khusus pencarian publik; FINAL)
`id BIGINT UNSIGNED PK`, `nba_dicari VARCHAR(15) NULL` (normalisasi bila format valid; untuk input invalid dapat dicatat secara terbatas/aman), `tahun_buku SMALLINT UNSIGNED NULL`, `ip_address VARCHAR(45) NULL`, `hasil ENUM(ditemukan,tidak_ditemukan,ditolak_captcha,dibatasi,tidak_valid)`, `created_at TIMESTAMP NOT NULL`; indeks `INDEX(created_at)` untuk purge dan indeks seperlunya untuk inspeksi Admin. Jangan simpan nominal SHU atau token CAPTCHA. Access control hanya Admin untuk pembacaan. **Retensi:** hapus baris `created_at < now() - interval 14 day` secara bertahap dengan Laravel Scheduler setiap hari. Tabel ini terpisah dari `log_audit` append-only yang merekam perubahan SHU/Admin dan dari `shu` per tahun buku; kedua tabel terakhir **tidak** memakai retensi 14 hari.


## `pengaturan_integrasi` — Konfigurasi Provider (v2.2, diterapkan 3 November 2026)
| Kolom | Tipe | Ketentuan |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK |
| `layanan` | ENUM('captcha','fonnte') | UNIQUE; satu baris per layanan |
| `aktif` | BOOLEAN | default false |
| `provider` | VARCHAR(50) NULL | CAPTCHA: `turnstile` |
| `site_key` | VARCHAR(255) NULL | kunci publik CAPTCHA saja |
| `label` | VARCHAR(255) NULL | label akun Fonnte (opsional) |
| `secret` | TEXT NULL | encrypted cast Laravel; ciphertext di database |
| `secret_versi` | INT UNSIGNED | naik saat simpan secret baru atau nonaktifkan |
| `status_uji` | VARCHAR(20) NULL | `diminta` / `diterima_api` / `terkirim` / `gagal` |
| `status_uji_pesan` | TEXT NULL | ringkasan hasil uji, tanpa credential |
| `status_uji_pada` | TIMESTAMP NULL | waktu uji terakhir |
| `diubah_oleh` | BIGINT UNSIGNED NULL | FK `users` |
| `created_at`, `updated_at` | TIMESTAMP | metadata |

Simpan tepat satu baris per layanan. Enkripsi secret dilakukan oleh cast `encrypted` sehingga nilai plaintext tidak pernah masuk database; kolom `secret` ada di `$hidden` dan halaman Admin hanya memakai `getRawOriginal()` untuk menandai "Tersimpan". Jangan membalik enkripsi dalam respons, audit, log, atau error. `.env` tetap memuat APP_KEY dan kredensial DB, bukan token CAPTCHA/Fonnte.
