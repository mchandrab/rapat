# Skema Database eRAT (Electronic Rapat Anggota Tahunan)

Dokumen ini menjelaskan struktur database eRAT, sistem informasi manajemen Rapat Anggota Tahunan koperasi.
Stack: Laravel 13 + PHP 8.3 + MySQL. Bahasa penulisan kolom mengikuti konvensi Laravel (snake_case).

- **Versi:** 1.0 — 23 Oktober 2026
- **Status:** Draf awal hasil diskusi kebutuhan

> Konvensi umum: semua tabel punya `id` bertipe `BIGINT UNSIGNED AUTO_INCREMENT` sebagai primary key, kecuali dinyatakan lain. `created_at` dan `updated_at` bertipe `TIMESTAMP NULLABLE` mengikuti bawaan Laravel. Kolom dengan keterangan "nullable" berarti boleh kosong (NULL). Uang selalu disimpan sebagai `DECIMAL(15,2)`.

---

## 1. `users`

Akun login aplikasi — **hanya untuk petugas**: `admin` (nama lama di repo terdahulu: `superadmin`), `helpdesk`, `scanner`, `keuangan`. Anggota koperasi **bukan** user: mereka subjek data (lihat tabel `anggota`), tidak punya akun, tidak bisa login, dan tidak ada portal anggota. Karena itu tabel ini **tidak** memiliki kolom `anggota_id` dan model `User` tidak berelasi ke `Anggota` untuk keperluan login. Peserta membuktikan kehadiran dengan QR pada undangannya yang memakai `anggota.qr_token`, bukan dengan akun. Role tiap user disimpan lewat tabel Spatie (bagian 2), bukan kolom di tabel ini.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `name` | VARCHAR(255) | Nama tampilan akun petugas |
| `email` | VARCHAR(255), UNIQUE | Email login, unik |
| `password` | VARCHAR(255) | Hash password (bcrypt) |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

## 2. Tabel Spatie Permission (paket `spatie/laravel-permission`)

Struktur standar bawaan paket Spatie, tidak dikustom. Roles yang dipakai aplikasi **tepat empat**, semuanya akun petugas: `admin`, `helpdesk`, `scanner`, `keuangan`. Tidak ada role untuk anggota koperasi karena anggota tidak login.

- **`roles`** — `id` (BIGINT UNSIGNED, PK), `name` VARCHAR(255) (mis. `admin`), `guard_name` VARCHAR(255), `created_at`, `updated_at`.
- **`permissions`** — `id` (BIGINT UNSIGNED, PK), `name` VARCHAR(255) (mis. `anggota.ubah_sensitif`, `anggota.verifikasi`, `presensi.koreksi`, `presensi.scan`, `presensi.manual`, `undangan.kirim`, `transport.kelola`, `transport.serahkan`, `kas.kelola`, `shu.kelola`, `media.lihat`), `guard_name` VARCHAR(255), `created_at`, `updated_at`.
- **`model_has_roles`** — `role_id` BIGINT UNSIGNED (FK → `roles.id`), `model_type` VARCHAR(255), `model_id` BIGINT UNSIGNED. Primary key gabungan `(role_id, model_id, model_type)`. Menautkan model (umumnya `users`) ke satu atau lebih role.
- **`model_has_permissions`** — `permission_id` BIGINT UNSIGNED (FK → `permissions.id`), `model_type` VARCHAR(255), `model_id` BIGINT UNSIGNED. Primary key gabungan `(permission_id, model_id, model_type)`. Permission langsung ke model, di luar role.
- **`role_has_permissions`** — `permission_id` BIGINT UNSIGNED (FK → `permissions.id`), `role_id` BIGINT UNSIGNED (FK → `roles.id`). Primary key gabungan `(permission_id, role_id)`. Permission yang dimiliki sebuah role.

## 3. `pengaturan`

Pengaturan aplikasi berbentuk key-value (nama koperasi, batas ukuran unggahan foto, dsb.) — lapis konfigurasi global. Konfigurasi yang berbeda antar rapat **tidak** disimpan di sini, melainkan di kolom `rapat` dan `rapat_transport_tier` (lihat tabel 6 dan 7).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `key` | VARCHAR(255), UNIQUE | Kunci pengaturan, mis. `nama_koperasi`, `maks_ukuran_foto_kb` |
| `value` | TEXT | Nilai pengaturan (disimpan sebagai teks, di-cast di aplikasi) |
| `keterangan` | VARCHAR(255), NULLABLE | Penjelasan fungsi pengaturan ini |

## 4. `log_audit`

Jejak perubahan data sensitif dan koreksi data operasional: perubahan field sensitif anggota, keputusan verifikasi, koreksi presensi, serah terima transport, penutupan kas, hasil undian, konflik sinkronisasi offline, dan lookup SHU publik. Hanya tambah data (append-only), tidak boleh diubah atau dihapus dari aplikasi. Log percobaan pengiriman undangan disimpan terpisah di `wa_logs`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `user_id` | BIGINT UNSIGNED, NULLABLE, FK → `users.id` | Pelaku aksi (NULL untuk aksi sistem) |
| `aksi` | VARCHAR(50) | Jenis aksi: `create`, `update`, `delete`, `verifikasi_anggota`, `koreksi_presensi`, `presensi_manual`, `serah_terima_transport`, `tutup_kas_meja`, `undi_doorprize`, `cek_shu_publik`, `konflik_sinkron_offline`, dsb. |
| `entitas` | VARCHAR(100) | Nama entitas/tabel yang terkena aksi, mis. `anggota`, `presensi` |
| `entitas_id` | BIGINT UNSIGNED, NULLABLE | ID baris yang terkena aksi |
| `data_sebelum` | JSON, NULLABLE | Nilai sebelum perubahan (NULL untuk `create`) |
| `data_sesudah` | JSON, NULLABLE | Nilai sesudah perubahan (NULL untuk `delete`) |
| `ip_address` | VARCHAR(45), NULLABLE | IP pelaku |
| `created_at` | TIMESTAMP NULLABLE | Waktu aksi (tanpa `updated_at`) |

Index: `user_id`, `(entitas, entitas_id)` untuk pencarian riwayat per baris data.

## 5. `anggota`

Data induk anggota koperasi — dasar verifikasi undangan, presensi, SHU, dan doorprize.

> **Kolom SENSITIF** (ditandai ⚠): `simpanan_wajib`, `kolektibilitas`, `pinjaman_lancar`, dan `status_anggota`. Keempatnya adalah pelajaran dari celah aplikasi lama: nilai kolom ini **hanya** boleh ditulis server-side oleh `admin` berizin lewat Controller + FormRequest + Policy, tidak pernah diterima dari input client/Livewire public property, dan setiap perubahannya wajib tercatat di `log_audit`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `no_anggota` | VARCHAR(50), UNIQUE | Nomor anggota/NBA koperasi, unik; setelah token dibuat, nomor ini diperlakukan tidak berubah karena menjadi bahan HMAC `qr_token` |
| `nama` | VARCHAR(255) | Nama lengkap anggota |
| `no_hp` | VARCHAR(50), NULLABLE | Nomor HP/WA untuk komunikasi undangan |
| `email` | VARCHAR(255), NULLABLE | Email anggota (bila ada) |
| `alamat` | TEXT, NULLABLE | Alamat tempat tinggal |
| `jenis_anggota` | ENUM('biasa','luar_biasa'), default `biasa` | `biasa` = masuk populasi & pembilang kuorum; `luar_biasa` = tidak dihitung kuorum |
| `status_anggota` ⚠ | ENUM('aktif','nonaktif') | SENSITIF — `aktif` = anggota aktif dan calon verifikasi undangan, `nonaktif` = sudah tidak aktif, data dipertahankan |
| `simpanan_wajib` ⚠ | DECIMAL(15,2), default 0 | SENSITIF — saldo simpanan wajib (Rp); dasar kriteria undangan Mode A |
| `kolektibilitas` ⚠ | TINYINT UNSIGNED, NULLABLE | SENSITIF — tingkat kolektibilitas/NPL pinjaman, skala 1 (lancar) s.d. 5 (macet); NULL bila anggota tidak punya pinjaman; dasar kriteria undangan Mode A |
| `pinjaman_lancar` ⚠ | BOOLEAN, default TRUE | SENSITIF — TRUE = pinjaman lancar (atau tidak punya pinjaman bermasalah), FALSE = menunggak; dasar kriteria undangan Mode B |
| `status_verifikasi` | ENUM('pending','verified','rejected'), default `pending` | Status keputusan verifikasi terakhir: belum diperiksa, data terverifikasi (syarat pintu dan dasar pembuatan peserta), atau verifikasi ditolak. Hasil penilaian kriteria Layak/Tidak Layak dicatat dalam proses verifikasi; status `verified` dengan catatan Tidak Layak tetap dapat masuk |
| `verified_by` | BIGINT UNSIGNED, NULLABLE, FK → `users.id` | Petugas yang membuat keputusan verifikasi terakhir |
| `verified_at` | TIMESTAMP, NULLABLE | Waktu keputusan verifikasi terakhir |
| `catatan_verifikasi` | TEXT, NULLABLE | Catatan pemeriksa; **wajib diisi bila hasil verifikasi dinyatakan Tidak Layak** |
| `alasan_penolakan` | TEXT, NULLABLE | Alasan ringkas penolakan; wajib diisi server-side bila `status_verifikasi = rejected` |
| `cara_undangan` | ENUM('wa','cetak'), NULLABLE | Cara undangan diterbitkan untuk anggota: WhatsApp atau cetak; NULL sebelum dipilih/diproses |
| `qr_token` | CHAR(64), UNIQUE | Token QR deterministik **HMAC-SHA256(`no_anggota`, APP_KEY)** dalam format heksadesimal. Dibangkitkan server-side, unik per anggota, **tidak pernah di-regenerate dan tidak kedaluwarsa**; kirim ulang selalu memakai token yang sama. **APP_KEY tidak boleh diganti**, karena perubahan APP_KEY membuat token lama dan kode resi lama tidak dapat dicocokkan |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

Index: `status_anggota`, `jenis_anggota`, `status_verifikasi`, `nama` (untuk pencarian), `(status_anggota, simpanan_wajib, kolektibilitas)` untuk penyaringan kriteria undangan. Relasi keputusan: `anggota.verified_by` → `users.id`.

## 6. `rapat`

Satu baris = satu rapat (RAT maupun rapat biasa), **sekaligus tempat konfigurasi bisnis rapat itu disimpan**. Semua pilihan dan ambang di tabel ini diisi admin saat setup rapat — tidak ada nilai yang di-hardcode di kode.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `jenis` | ENUM('rat','rapat_biasa') | `rat` = Rapat Anggota Tahunan, `rapat_biasa` = rapat lain di luar RAT |
| `judul` | VARCHAR(255) | Judul rapat, mis. `Rapat Anggota Tahunan 2026` |
| `tanggal` | DATE | Tanggal pelaksanaan |
| `jam_mulai` | TIME | Jam mulai rapat — acuan tier transport dan kuorum |
| `jam_selesai` | TIME | Jam selesai yang direncanakan |
| `lokasi` | VARCHAR(255) | Tempat pelaksanaan |
| `mode_presensi` | ENUM('1x','2x') | Dipilih saat setup rapat: `1x` = presensi datang saja, `2x` = presensi datang + pulang |
| `mode_kriteria` | ENUM('simpanan_kolektibilitas','kelancaran') | Mode kriteria undangan: `simpanan_kolektibilitas` = Mode A (simpanan wajib + kolektibilitas), `kelancaran` = Mode B (lancar/tidaknya pinjaman) |
| `min_simpanan_wajib` | DECIMAL(15,2), NULLABLE | Mode A: batas minimum saldo simpanan wajib agar diundang (NULL = tidak dibatasi dari sisi simpanan) |
| `maks_kolektibilitas` | TINYINT UNSIGNED, NULLABLE | Mode A: batas maksimum kolektibilitas/NPL agar diundang, 1–5 (NULL = tidak dibatasi dari sisi kolektibilitas) |
| `nominal_transport_dasar` | DECIMAL(15,2), default 0 | Nominal transport sebelum potongan keterlambatan (Rp); nominal final tidak boleh melebihi nilai ini |
| `grace_period_menit` | INT, default 15 | Masa tenggang setelah `jam_mulai` yang tidak dihitung sebagai keterlambatan efektif untuk potongan transport |
| `batas_hangus_menit` | INT, NULLABLE, default 120 | Batas menit terlambat mentah; kedatangan melampaui batas ini membuat hak transport hangus (nominal final 0). NULL = tidak ada batas hangus |
| `kuorum_basis` | ENUM('total','terverifikasi'), default `terverifikasi` | Basis populasi kuorum: `total` = seluruh anggota aktif berjenis biasa; `terverifikasi` = anggota aktif berjenis biasa yang berstatus `verified` dan sudah dibuatkan peserta untuk rapat ini |
| `persen_kuorum` | DECIMAL(5,2), default 50 | Persen populasi untuk rumus target kuorum `KuorumService` |
| `kuorum_tambahan` | INT, default 1 | Tambahan orang pada rumus target: `target = FLOOR(persen_kuorum × populasi) + kuorum_tambahan` |
| `status` | ENUM('draft','registrasi','berlangsung','selesai','dibatalkan') | `draft` = disiapkan, `registrasi` = verifikasi undangan berjalan & presensi belum dibuka, `berlangsung` = presensi dibuka, `selesai` = rapat ditutup, `dibatalkan` = tidak jadi dilaksanakan |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

Index: `tanggal`, `status`.

## 7. `rapat_transport_tier`

Tier **potongan** uang transport per rapat. Model ini bukan daftar nominal final: nominal dasar disimpan di `rapat.nominal_transport_dasar`, sedangkan tier menentukan berapa yang dipotong berdasarkan keterlambatan efektif peserta.

`TransportService` menghitung `menit_terlambat` mentah dari `jam_mulai`, lalu keterlambatan efektif = `MAX(0, menit_terlambat - grace_period_menit)`. Tier yang dipakai adalah baris yang rentangnya menampung keterlambatan efektif (`menit_min <= keterlambatan_efektif` dan (`menit_maks IS NULL` atau `keterlambatan_efektif <= menit_maks`)), dipilih mengikuti `urutan`. Rumus snapshot di presensi: `nominal_transport_final = MAX(0, nominal_transport_dasar - nominal_potongan)`. Bila `batas_hangus_menit` terisi dan menit terlambat mentah melampauinya, tidak ada tier yang dipakai dan nominal final 0.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `rapat_id` | BIGINT UNSIGNED, FK → `rapat.id` | Rapat pemilik tier potongan |
| `menit_min` | INT | Batas bawah rentang keterlambatan efektif (inklusif) |
| `menit_maks` | INT, NULLABLE | Batas atas rentang keterlambatan efektif (inklusif); NULL = tanpa batas atas |
| `nominal_potongan` | DECIMAL(15,2), default 0 | Jumlah potongan dari `rapat.nominal_transport_dasar` untuk rentang ini (Rp) |
| `urutan` | INT, default 0 | Urutan evaluasi tier dalam rapat; nilai lebih kecil dievaluasi lebih dulu |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

UNIQUE KEY `uniq_tier_rapat_urutan` pada `(rapat_id, urutan)`. Index: `(rapat_id, menit_min, menit_maks)`. Service wajib memvalidasi `menit_maks` NULL atau `>= menit_min`, serta menolak rentang tier yang tumpang tindih dalam rapat yang sama.

## 8. `peserta`

Anggota yang terundang pada satu rapat. **Verifikasi adalah mekanisme pembuatan peserta:** baris `peserta` hanya dibuat dalam transaksi yang sama ketika `VerifikasiService` menyatakan anggota `verified` untuk rapat tersebut. Ini termasuk anggota `verified` yang hasil penilaian kriterianya tercatat Tidak Layak — status `verified` adalah syarat pintunya. Anggota `pending` atau `rejected` tidak memiliki baris peserta untuk rapat itu.

Kolom `snapshot_*` di tabel inilah **satu-satunya tempat snapshot kriteria disimpan**. Nilai disalin server-side dari anggota tepat saat verifikasi dinyatakan Layak — tidak berubah walau data anggota berubah belakangan dan tidak bisa diubah dari client. Token QR **tidak** disimpan di tabel ini; QR selalu dibaca dari `anggota.qr_token`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `rapat_id` | BIGINT UNSIGNED, FK → `rapat.id` | Rapat tujuan undangan |
| `anggota_id` | BIGINT UNSIGNED, FK → `anggota.id` | Anggota terverifikasi yang diundang |
| `snapshot_simpanan_wajib` | DECIMAL(15,2) | Snapshot saldo simpanan wajib saat verifikasi |
| `snapshot_kolektibilitas` | TINYINT UNSIGNED, NULLABLE | Snapshot kolektibilitas saat verifikasi (NULL bila tidak punya pinjaman) |
| `snapshot_pinjaman_lancar` | BOOLEAN | Snapshot status kelancaran pinjaman saat verifikasi |
| `status_undangan` | ENUM('diundang','dibatalkan') | `diundang` = undangan berlaku, `dibatalkan` = undangan dicabut panitia (peserta tidak bisa presensi) |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel; `created_at` adalah waktu peserta dibuat oleh keputusan verified |

UNIQUE KEY `uniq_peserta_rapat_anggota` pada `(rapat_id, anggota_id)` — satu anggota hanya terundang satu kali per rapat. Index: `(rapat_id, status_undangan)`.

## 9. `presensi`

Kehadiran satu peserta pada satu rapat. Baris hanya dibuat saat peserta benar-benar melakukan presensi datang; peserta yang tidak datang **tidak punya baris** di tabel ini (ketidakhadiran disimpulkan dari ketiadaan baris setelah rapat selesai). Jalur foto & tanda tangan adalah path relatif di **disk privat**, bukan URL publik.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `peserta_id` | BIGINT UNSIGNED, UNIQUE, FK → `peserta.id` | Peserta yang presensi (satu peserta = maksimal satu presensi per rapat) |
| `sumber` | ENUM('scan_qr','ketik_manual','offline'), default `scan_qr` | Jalur pencatatan: scan QR normal, ketik/manual oleh helpdesk untuk pengecualian, atau antrean offline scanner yang kemudian disinkronkan |
| `status_sinkron` | ENUM('tersinkron','pending_sinkron'), default `tersinkron` | `tersinkron` = sudah menjadi data server; `pending_sinkron` = event offline belum terkonfirmasi server |
| `dicatat_oleh` | BIGINT UNSIGNED, FK → `users.id` | User petugas yang mencatat presensi; pada alur normal adalah user `scanner`, pada pengecualian manual adalah user helpdesk yang mencatat |
| `waktu_datang` | DATETIME | Waktu presensi datang; waktu server untuk online, atau `captured_at` perangkat yang divalidasi server untuk event offline |
| `waktu_pulang` | DATETIME, NULLABLE | Waktu presensi pulang; hanya terisi pada rapat mode `2x` setelah peserta presensi pulang |
| `foto_datang_path` | VARCHAR(255) | Path foto wajah saat presensi datang di disk privat (bukti audit kehadiran) |
| `foto_pulang_path` | VARCHAR(255), NULLABLE | Path foto wajah saat presensi pulang (mode `2x`) |
| `tanda_tangan_path` | VARCHAR(255), NULLABLE | Path gambar tanda tangan digital peserta di disk privat; NULL = belum tanda tangan → presensi **belum sah** sampai terisi |
| `menit_terlambat` | INT, default 0 | Menit terlambat mentah dari `rapat.jam_mulai` ke `waktu_datang`; 0 bila datang sebelum atau tepat pada jam mulai |
| `tier_id` | BIGINT UNSIGNED, NULLABLE, FK → `rapat_transport_tier.id` | Tier potongan yang terpakai; NULL bila tidak ada potongan/tidak ada tier cocok, atau hak transport hangus |
| `nominal_transport_final` | DECIMAL(15,2), default 0 | Snapshot hasil akhir transport setelah potongan: `MAX(0, rapat.nominal_transport_dasar - tier.nominal_potongan)`; 0 bila hangus |
| `kode_resi` | CHAR(64), UNIQUE, NULLABLE | Kode resi HMAC berbasis APP_KEY yang diterbitkan **saat serah terima/pembayaran transport berhasil**: mode `1x` pada titik datang, mode `2x` pada titik pulang. NULL sebelum pembayaran; unik bila sudah terbit |
| `status_kehadiran` | ENUM('hadir','dibatalkan') | `hadir` = kehadiran berlaku, `dibatalkan` = kehadiran dibatalkan lewat koreksi admin |
| `dikoreksi_oleh` | BIGINT UNSIGNED, NULLABLE, FK → `users.id` | Admin yang terakhir mengoreksi baris ini (NULL bila belum pernah dikoreksi) |
| `catatan_koreksi` | VARCHAR(255), NULLABLE | Alasan koreksi terakhir — wajib diisi setiap koreksi, dan setiap koreksi juga ditulis ke `log_audit` |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

UNIQUE KEY `uniq_presensi_kode_resi` pada `kode_resi`. Index: `status_kehadiran`, `status_sinkron`, `dicatat_oleh`, `(sumber, status_sinkron)` untuk antrean sinkronisasi. Constraint unik `peserta_id` adalah pengaman database utama anti-duplikat presensi datang; sinkronisasi offline juga wajib idempoten per event perangkat.

## 10. `transport_pembayaran`

Status serah terima uang transport satu peserta. Baris dibuat `TransportService` setelah presensi cukup lengkap untuk dihitung. **Tier dan nominal final yang berwenang ada di `presensi`** (`presensi.tier_id` dan `presensi.nominal_transport_final`); tabel ini tidak menggandakan nominal agar tidak terjadi dua angka yang berbeda. Saat scanner menyerahkan tunai di titik yang ditentukan mode rapat, status diubah menjadi `sudah`, `presensi.kode_resi` diterbitkan, dan pembayaran dikaitkan ke `kas_meja` asal uangnya dalam transaksi yang sama.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `peserta_id` | BIGINT UNSIGNED, UNIQUE, FK → `peserta.id` | Peserta penerima transport (satu peserta = satu baris per rapat, lewat `peserta`) |
| `kas_meja_id` | BIGINT UNSIGNED, NULLABLE, FK → `kas_meja.id` | Meja kas asal uang yang diserahkan; terisi saat pembayaran dilakukan untuk rekonsiliasi |
| `status_bayar` | ENUM('belum','sudah') | Status serah terima ke peserta |
| `dibayar_pada` | DATETIME, NULLABLE | Waktu serah terima dilakukan (NULL bila belum dibayar) |
| `dibayar_oleh` | BIGINT UNSIGNED, NULLABLE, FK → `users.id` | User `scanner` yang menyerahkan tunai di titik scan dan tercatat sebagai penanggung jawab transaksi pada meja tersebut |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

Index: `status_bayar`, `kas_meja_id` (untuk daftar antre bayar dan rekonsiliasi per meja).

## 11. `shu`

Data Sisa Hasil Usaha per anggota per tahun buku. Dikelola oleh **keuangan** lewat `ShuService` (input, impor, dan koreksi — koreksi wajib tercatat di `log_audit`). Relasi `anggota_id` di tabel ini adalah pemilik SHU sebagai subjek data, **bukan** akun login. **Tidak ada portal anggota**: cek SHU publik dilakukan lewat formulir nomor anggota (dan tahun buku) pada endpoint khusus yang di-throttle per IP + nomor anggota; setiap lookup — ditemukan maupun tidak — dicatat ke `log_audit` dengan `user_id` NULL. Helpdesk tidak memiliki lookup SHU internal.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `anggota_id` | BIGINT UNSIGNED, FK → `anggota.id` | Anggota pemilik SHU |
| `tahun_buku` | SMALLINT UNSIGNED | Tahun buku SHU, mis. `2025` |
| `jumlah` | DECIMAL(15,2) | Jumlah SHU anggota pada tahun buku itu (Rp) |
| `keterangan` | VARCHAR(255), NULLABLE | Catatan tambahan, mis. sumber impor |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

UNIQUE KEY `uniq_shu_anggota_tahun` pada `(anggota_id, tahun_buku)` — satu anggota hanya punya satu baris SHU per tahun buku; impor ulang tahun yang sama memperbarui, bukan menggandakan.

## 12. `doorprize_hadiah`

Daftar hadiah doorprize satu rapat. Undian dilakukan per hadiah mengikuti `urutan`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `rapat_id` | BIGINT UNSIGNED, FK → `rapat.id` | Rapat pemilik hadiah |
| `nama_hadiah` | VARCHAR(255) | Nama hadiah, mis. `Sepeda Lipat`, `Rice Cooker` |
| `jumlah` | INT UNSIGNED, default 1 | Jumlah unit hadiah ini (= jumlah pemenang yang diundi untuk hadiah ini) |
| `urutan` | INT UNSIGNED, default 0 | Urutan pengundian (kecil diundi lebih dulu) |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

Index: `(rapat_id, urutan)`.

## 13. `doorprize_pemenang`

Hasil undian doorprize. Pool undian **hanya** peserta rapat itu yang berstatus hadir (punya `presensi` berlaku), disusun `DoorprizeService`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `rapat_id` | BIGINT UNSIGNED, FK → `rapat.id` | Rapat tempat undian berlangsung |
| `hadiah_id` | BIGINT UNSIGNED, FK → `doorprize_hadiah.id` | Hadiah yang dimenangkan |
| `peserta_id` | BIGINT UNSIGNED, FK → `peserta.id` | Peserta pemenang |
| `diundi_pada` | DATETIME | Waktu pengundian (jam server) |
| `diundi_oleh` | BIGINT UNSIGNED, FK → `users.id` | Petugas yang menjalankan undian |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

UNIQUE KEY `uniq_pemenang_peserta_rapat` pada `(rapat_id, peserta_id)` — satu peserta maksimal menang satu kali per rapat, dijamin di level database. Index: `hadiah_id`.

## 14. `kas_meja`

Alokasi dan penutupan kas fisik transport untuk satu meja pada satu rapat. Keuangan mencatat kas awal dan melakukan rekonsiliasi penutupan; user `scanner` pada baris ini adalah penanggung jawab meja dan petugas yang menyerahkan tunai di titik scan.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `rapat_id` | BIGINT UNSIGNED, FK → `rapat.id` | Rapat pemilik kas meja |
| `scanner_user_id` | BIGINT UNSIGNED, FK → `users.id` | User `scanner` penanggung jawab meja |
| `nama_meja` | VARCHAR(100) | Nama/label meja, mis. `Meja 1` atau `Pintu A` |
| `kas_awal` | DECIMAL(15,2), default 0 | Jumlah kas fisik yang diserahkan ke meja saat dibuka (Rp) |
| `sisa_kas` | DECIMAL(15,2), NULLABLE | Jumlah kas fisik yang dihitung saat penutupan; NULL berarti meja belum ditutup |
| `catatan_selisih` | TEXT, NULLABLE | Penjelasan selisih penutupan; **wajib diisi bila selisih tidak sama dengan 0** |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel |

UNIQUE KEY `uniq_kas_meja_rapat_nama` pada `(rapat_id, nama_meja)`. Index: `(rapat_id, scanner_user_id)`. Sisa kas seharusnya dihitung `KasService` sebagai `kas_awal - total nominal pembayaran berstatus sudah yang kas_meja_id-nya menunjuk baris ini`; selisih adalah `sisa_kas - sisa_kas_seharusnya`. Validasi catatan wajib untuk selisih bukan nol ditegakkan di FormRequest/Service dan dicatat ke `log_audit`.

## 15. `wa_logs`

Log setiap percobaan penerbitan undangan per anggota, baik lewat WhatsApp maupun cetak. Pengiriman WhatsApp bersifat **on-demand per anggota** dari detail/antrean verifikasi; tidak ada broadcast massal.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, auto increment | Primary key |
| `anggota_id` | BIGINT UNSIGNED, FK → `anggota.id` | Anggota tujuan undangan |
| `jenis` | ENUM('verifikasi','kirim_ulang','cetak') | `verifikasi` = penerbitan pertama setelah verified, `kirim_ulang` = kirim ulang undangan/token yang sama, `cetak` = pencetakan undangan |
| `target` | VARCHAR(255) | Tujuan log: nomor WA tujuan untuk pengiriman, atau identitas target cetak |
| `status` | ENUM('terkirim','gagal') | Hasil percobaan penerbitan |
| `metode` | VARCHAR(50) | Metode/channel, mis. `fonnte`, `gateway_wa`, atau `cetak` |
| `detail` | TEXT, NULLABLE | Detail hasil: ID/respons gateway, pesan kegagalan, atau catatan cetak |
| `created_at`, `updated_at` | TIMESTAMP NULLABLE | Bawaan Laravel; `created_at` adalah waktu percobaan dicatat |

Index: `anggota_id`, `jenis`, `status`, `created_at` untuk penelusuran riwayat pengiriman per anggota.

---

## Relasi Antar Tabel

- `users` **tidak berelasi** ke `anggota`: user adalah akun petugas (admin/helpdesk/scanner/keuangan), sedangkan anggota adalah subjek data yang tidak punya akun login. Relasi ke `anggota` hanya ada pada `peserta.anggota_id` dan `shu.anggota_id`.
- `anggota.verified_by` → `users.id` (satu petugas dapat memverifikasi banyak anggota; keputusan terakhir tersimpan di anggota dan riwayatnya di `log_audit`).
- `rapat_transport_tier.rapat_id` → `rapat.id` (satu rapat punya banyak tier potongan transport).
- `peserta.rapat_id` → `rapat.id`; `peserta.anggota_id` → `anggota.id` (satu rapat punya banyak peserta; satu anggota bisa jadi peserta di banyak rapat).
- `presensi.peserta_id` → `peserta.id` (satu peserta punya maksimal satu presensi per rapat); `presensi.tier_id` → `rapat_transport_tier.id`; `presensi.dicatat_oleh` → `users.id`.
- `transport_pembayaran.peserta_id` → `peserta.id`; `transport_pembayaran.kas_meja_id` → `kas_meja.id`; `transport_pembayaran.dibayar_oleh` → `users.id`.
- `kas_meja.rapat_id` → `rapat.id`; `kas_meja.scanner_user_id` → `users.id` (satu rapat dapat memiliki banyak meja; satu scanner menjadi penanggung jawab meja sesuai penugasan).
- `wa_logs.anggota_id` → `anggota.id` (satu anggota memiliki banyak riwayat penerbitan undangan).
- `shu.anggota_id` → `anggota.id` (satu anggota punya banyak baris SHU, satu per tahun buku).
- `doorprize_hadiah.rapat_id` → `rapat.id` (satu rapat punya banyak hadiah).
- `doorprize_pemenang.rapat_id` → `rapat.id`; `doorprize_pemenang.hadiah_id` → `doorprize_hadiah.id`; `doorprize_pemenang.peserta_id` → `peserta.id`; `doorprize_pemenang.diundi_oleh` → `users.id`.
- `presensi.dikoreksi_oleh` → `users.id`.
- `log_audit.user_id` → `users.id` (satu user bisa tercatat di banyak log).
- Relasi Spatie: `model_has_roles` dan `model_has_permissions` menautkan `users` (via `model_type` + `model_id`) ke `roles`/`permissions`; `role_has_permissions` menautkan `roles` ke `permissions`.

## Aturan Integritas Penting

- **Field sensitif anggota terkunci dari client.** `anggota.simpanan_wajib`, `anggota.kolektibilitas`, `anggota.pinjaman_lancar`, dan `anggota.status_anggota` hanya boleh ditulis server-side oleh admin berizin (Controller + FormRequest + Policy) dan setiap perubahannya wajib masuk `log_audit` — pelajaran dari celah aplikasi lama di mana nilai field ini bisa ditentukan dari Livewire public property di sisi client.
- **Snapshot peserta tidak boleh berubah dan hanya ada di `peserta`.** Nilai `peserta.snapshot_*` ditulis satu kali oleh `VerifikasiService` tepat saat anggota dinyatakan verified untuk rapat dan tidak ada jalur ubah sesudahnya; perubahan data anggota setelah itu tidak memengaruhi snapshot.
- **Satu anggota satu baris peserta per rapat** (dijaga unique key `rapat_id` + `anggota_id`), dan **satu peserta satu presensi** (dijaga unique key `peserta_id` di `presensi`).
- **Presensi tanpa tanda tangan belum sah.** `presensi.tanda_tangan_path` yang masih NULL berarti kehadiran belum tervalidasi peserta; panitia wajib menindaklanjuti sebelum rapat ditutup.
- **Koreksi presensi tidak pernah senyap.** Koreksi hanya oleh admin, wajib mengisi `catatan_koreksi`, mengisi `dikoreksi_oleh`, dan menulis baris `log_audit` berisi nilai sebelum/sesudah.
- **Nominal transport adalah snapshot di `presensi`.** `presensi.tier_id` dan `presensi.nominal_transport_final` dihitung dari nominal dasar, grace period, batas hangus, dan tier potongan saat presensi; mengedit konfigurasi sesudahnya tidak mengubah hak peserta yang sudah tercatat. `transport_pembayaran` hanya menyimpan status dan kaitan kas meja.
- **Satu peserta maksimal menang satu kali per rapat** (dijaga unique key `rapat_id` + `peserta_id` di `doorprize_pemenang`), dan pool undian hanya berisi peserta dengan presensi berstatus `hadir`.
- **Satu anggota satu baris SHU per tahun buku** (dijaga unique key `anggota_id` + `tahun_buku`).
- **Anggota yang sudah punya riwayat (peserta, SHU) tidak boleh di-hard delete.** Penonaktifan dilakukan dengan mengubah `status_anggota` menjadi `nonaktif` agar riwayat rapat tetap utuh.
- **Foreign key memakai `restrict` (tidak cascade delete)** untuk relasi ke `anggota`, `rapat`, dan `peserta`, agar data riwayat tidak ikut terhapus diam-diam.
- **`log_audit` bersifat append-only**: tidak ada fitur ubah atau hapus log dari aplikasi.
- **Foto wajah dan tanda tangan tidak pernah menjadi berkas publik.** Kolom `foto_datang_path`, `foto_pulang_path`, dan `tanda_tangan_path` menyimpan path relatif di disk privat; berkas hanya disajikan lewat route berotorisasi (lihat `ARCHITECTURE.md` Bab 9).
- **QR undangan bukan kredensial login dan tokennya permanen.** `anggota.qr_token` adalah HMAC-SHA256 deterministik dari nomor anggota/NBA dan APP_KEY; token tidak disimpan di `peserta`, tidak di-regenerate, dan tidak kedaluwarsa. Endpoint scan tetap menolak siapa pun yang tidak login sebagai petugas. **APP_KEY tidak boleh diganti** karena token QR dan `kode_resi` lama akan rusak/tidak cocok.
- **Semua angka bisnis berasal dari konfigurasi.** Target kuorum memakai `rapat.persen_kuorum`, `rapat.kuorum_basis`, dan `rapat.kuorum_tambahan`; populasi kuorum hanya anggota aktif berjenis `biasa`. Kriteria undangan, mode presensi, nominal dasar transport, grace period, batas hangus, dan tier potongan tersimpan sebagai data — kode dilarang meng-hardcode nilai-nilai ini.
- **Verifikasi adalah satu-satunya jalan menjadi peserta.** Tidak ada pembuatan `peserta` dari proses impor/bangkitan massal tanpa keputusan verified. Anggota `rejected` wajib memiliki alasan penolakan dan catatan verifikasi; hasil penilaian Tidak Layak juga wajib memiliki catatan, tetapi anggota yang statusnya `verified` tetap dibuatkan peserta dan boleh melewati pintu berdasarkan status verified tersebut.
- **Pembayaran dan resi adalah satu transaksi.** `presensi.kode_resi` hanya boleh terisi ketika `transport_pembayaran.status_bayar = sudah`; mode `1x` menerbitkannya pada titik datang dan mode `2x` pada titik pulang.
- **Kas meja harus rekonsiliasi.** Bila `kas_meja.sisa_kas` menghasilkan selisih bukan nol terhadap sisa seharusnya, `catatan_selisih` wajib terisi.
- **Setiap penerbitan undangan tercatat.** Pengiriman awal, kirim ulang, dan cetak wajib menghasilkan baris `wa_logs`, berhasil maupun gagal; tidak ada jalur broadcast yang melewati log ini.
- **Sinkronisasi offline anti-duplikat.** Event offline diproses idempoten; baris `presensi` unik per peserta dan status sinkron hanya berubah ke `tersinkron` setelah server menerima event yang sah.
