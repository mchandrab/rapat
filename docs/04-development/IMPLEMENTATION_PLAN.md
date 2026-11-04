# IMPLEMENTATION PLAN — Tahapan Pembangunan

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Aturan fase
Satu fase tuntas setelah test PHPUnit hijau, acceptance disetujui, dan tidak ada keputusan tertunda yang memblokir fase. Jangan mengubah fase berikutnya diam-diam.

| Fase | Pekerjaan | Syarat selesai |
|---|---|---|
| 0 | Fondasi Laravel, Tabler, role, auth, audit | 4 role server-side + test keamanan |
| 1 | Master anggota, NBA format, impor tahunan, tgl_ja/tgl_keluar | Update tanpa duplikat dan snapshot aman |
| 2 | Rapat, tahun buku, dua jam, konfigurasi dinamis, kunci | Perhitungan & status sesuai aturan |
| 3 | Kelayakan, per-rat verification, token acak, cetak/WA & log petugas | Verified saja menerbitkan QR, Tidak Layak dengan alasan |
| 4 | Alokasi kas per Scanner, kontrol akses & audit serah terima | Satu kas/Scanner/RAT, tanpa top-up |
| 5 | Mode Presensi 1x online & transport, foto/TTE, saldo | Scan gagal bila kas tidak cukup, tidak double pay |
| v2.2 | Integrasi CAPTCHA & Fonnte dari Pengaturan | Secret terenkripsi, uji nyata, WA undangan aktif |
| v2.3 | Manajemen pengguna (selesai 4 Nov 2026) | CRUD akun petugas Admin-only, empat role tetap, ganti sandi, proteksi Admin sendiri, audit tanpa sandi |
| 6 | Mode Presensi 2x (Datang-Pulang) | Selesai 3 Nov 2026 (D-034): datang mengunci nominal, pulang membayar dari kas Scanner pencatat pulang, transport hangus bila tidak pulang |
| 7 | Kuorum, doorprize, SHU Admin, halaman publik | Hak akses, hasil hitung, privacy |
| 7a | Kuorum anggota biasa (selesai 3 Nov 2026) | Target, hadir, status sah sesuai konfigurasi rapat |
| 7b | SHU Admin + saklar cek publik (selesai; koreksi manual 4 Nov 2026) | Input manual, koreksi beralasan, impor XLSX per tahun buku, audit, tombol buka/tutup |
| 7d | Doorprize (selesai 3 Nov 2026) | Jumlah pemenang per rapat, pop-up masih ada/sudah pulang |
| 7c | Cek SHU publik (selesai 3 Nov 2026) | NBA + Turnstile, rate limit, log 14 hari, purge harian |
| 8 | Rekonsiliasi, laporan, audit lengkap (tutup kas selesai 3 Nov 2026) | Cocok total dan selisih tertangani |
| 8b | Laporan FR-011 + ekspor XLSX (selesai 3 Nov 2026) | Rekap per rapat sesuai role; angka halaman = ekspor |
| 9 | Offline/sinkron | tidak dikerjakan (D-032): koneksi putus = pemindaian berhenti total, tanpa antrean |
| 10 | Kesiapan operasional (sebagian selesai) | Backup/restore dan benchmark selesai; UAT hari-H, load test nyata, pemasangan scheduler produksi, dan signoff masih wajib |

**Status pelaksanaan:** Fase 0 selesai (fondasi Laravel, empat role, auth, shell UI). Fase 1 selesai 29 Oktober 2026: tabel `anggota` dan `log_audit`, validasi NBA 15 digit, pencarian dengan/tanpa titik, impor XLSX (D-020), audit perubahan master. Fase 2 selesai: tabel `rapat` dan `rapat_transport_tier`, parameter tersimpan per rapat, tanggal acuan 31 Desember dihitung dari tahun buku, kunci otomatis saat Berlangsung, transisi Draf → Verifikasi → Berlangsung → Selesai/Dibatalkan, seluruh perubahan diaudit. Pembukaan presensi mengikuti D-021 (status tetap Verifikasi + penanda `presensi_dibuka_at`).

**Fase 3 selesai 30 Oktober 2026:** tabel `peserta` dan `log_undangan`; penilaian kelayakan otomatis Mode A/B dengan minimum proporsional; tombol **Siapkan Peserta** idempoten yang menyimpan snapshot parameter; keputusan Verified/Rejected tersimpan dengan audit dan alasan wajib untuk Tidak Layak yang tetap diberi undangan; token QR acak 32 byte (hash + ciphertext) terbit sekali per peserta per rapat; kirim/cetak undangan tercatat dan **Riwayat Undangan** membaca `log_undangan`; dashboard memakai hitungan peserta nyata. WhatsApp tetap gagal-tertutup sampai Integrasi v2.2. Pengujian: 75 tes hijau (SQLite), `migrate:fresh --seed` bersih di MySQL, jalur `peserta`/`log_undangan` diuji langsung di MySQL lalu di-rollback sehingga database tetap kosong.

**Fase 4 selesai 30 Oktober 2026:** tabel `kas_scanner` dengan UNIQUE `(rapat_id, scanner_user_id)`; **Keuangan** mencatat alokasi kas awal per akun Scanner lewat `/kas`, alokasi kedua ditolak (tanpa top-up/transfer), setiap penyerahan diaudit; **Admin** hanya memantau halaman yang sama; **Scanner** melihat kas miliknya sendiri di **Kas Saya** dan dashboard; halaman Rekonsiliasi tetap non-aktif (Fase 8). Konfirmasi penerimaan kas dan penggantian Scanner ditetapkan final oleh D-026 (dikerjakan bersama Tutup Kas di Fase 8); ambang peringatan saldo menipis ditetapkan D-025; kolom `diterima_pada` disiapkan tetapi belum dipakai. Pengujian: 91 tes hijau (SQLite), jalur `kas_scanner` diuji langsung di MySQL lalu di-rollback.

**Fase 5 inti selesai 30 Oktober 2026 (foto/TTE & kamera QR menyusul):** tabel `presensi` dan `transport_pembayaran`; **Admin** membuka presensi lewat tombol **Buka Presensi** (status rapat tetap Verifikasi, D-021) dan aksinya diaudit; **Scanner** mencatat presensi dari token QR peserta Verified di **Kas Saya**; nominal dihitung server (BR-008) dari jam mulai RAT, bukan jam presensi; kas Scanner berkurang lewat `KasScanner::sisa()`; scan ganda, peserta belum Verified, anggota Nonaktif, token rapat lain, kas tidak cukup, dan presensi yang belum dibuka ditolak; transport **Rp0 (hangus)** tetap mencatat presensi tanpa baris pembayaran (D-022). Pengujian: 140 tes hijau (SQLite), jalur `presensi`/`transport_pembayaran` diuji langsung di MySQL lalu di-rollback.

**Database:** aplikasi memakai MySQL `erat_rukun`; seluruh migration berhasil dijalankan di MySQL 8.4.3. Setelah reset total hanya akun dengan username `admin` yang dibuat ulang; akun Helpdesk, Scanner, dan Keuangan dibuat Admin melalui menu **Pengaturan → Pengguna** (D-035, D-041). `AnggotaSeeder` dan `RapatSeeder` tetap opsional. Panduan uji per role: `docs/02-workflows/ALUR_PROSES_SEMUA_ROLE.md`.

## Fase 3 — Peserta, Kelayakan, Verifikasi, Token QR (selesai)

**Tujuan:** mengubah status verifikasi dari tampilan menjadi data tersimpan per rapat, dengan kelayakan otomatis sesuai D-003/D-008 dan token QR unik sesuai D-004.

**Prasyarat yang sudah ada:** tabel `anggota`, `rapat`, `rapat_transport_tier`, `log_audit`; `Kelayakan::minimumProporsional()`; modal Kelola Anggota; `mode_kelayakan` (`A`/`B`) dan ambang kolektibilitas tersimpan per rapat.

### 3.1 Skema data

1. Migration `peserta` sesuai `DATABASE_SCHEMA.md`: `rapat_id`, `anggota_id`, `hasil_kelayakan`, `status_verifikasi`, `alasan_override`, `alasan_penolakan`, `verified_by`, `verified_at`, snapshot (`simpanan_wajib`, `tgl_ja`, `minimum_proporsional`, `kolektibilitas`, `pinjaman_lancar`, `konfigurasi` JSON), `qr_token_hash`, `qr_dibuat_at`, unique `(rapat_id, anggota_id)`.
2. Migration `log_undangan`: `rapat_id`, `peserta_id`, `petugas_user_id`, `metode`, `status`, `waktu_aksi`, `provider_message_id`, `detail_error`.
3. Model `Peserta` + `LogUndangan`, relasi ke `Rapat`, `Anggota`, `User`; `Peserta` memakai snapshot, bukan membaca ulang master, agar hasil tidak berubah saat master dikoreksi setelah rapat berjalan.

### 3.2 Penilaian kelayakan

4. `PenilaianKelayakanService`: Mode A = simpanan wajib ≥ minimum proporsional **dan** kolektibilitas ≤ batas rapat; Mode B = simpanan wajib ≥ minimum proporsional **dan** pinjaman lancar. Status keanggotaan tidak menjadi syarat (D-003), tetapi `nonaktif` tetap memblokir presensi (D-006).
5. Command/aksi `peserta:siapkan` (atau tombol Admin "Siapkan Peserta") membuat baris `peserta` untuk seluruh anggota pada rapat berstatus Verifikasi, menyimpan snapshot konfigurasi. Idempoten: jalankan ulang tidak menggandakan dan tidak menimpa keputusan verifikasi yang sudah ada.
6. Kasus tepi `tgl_ja` mengikuti D-033: kosong = Tidak Layak dengan alasan `Tanggal jadi anggota belum lengkap`; setelah tahun buku = minimum 0; bulan inklusif dibatasi 12. Perubahan aturan cukup di `PenilaianKelayakan`/`Kelayakan` + test, bukan di view.

### 3.3 Verifikasi & token

7. Aksi `verifikasi`/`tolak` pada `peserta-verifikasi` menulis ke `peserta`, mengisi `verified_by`/`verified_at`, dan mencatat `log_audit`. Tidak Layak yang di-Verified wajib `alasan_override` (BR-004).
8. Token QR: 32 byte acak (`random_bytes`), disimpan sebagai hash untuk pencarian **dan** nilai terenkripsi untuk cetak ulang; terbit hanya saat status menjadi Verified, sekali per peserta per rapat, dan tidak pernah diturunkan dari NBA.
9. `log_undangan` dicatat setiap kirim/cetak; WhatsApp tetap gagal-tertutup sampai `FonnteGateway` (v2.2) selesai, dengan status `gagal`/`belum_terkirim` yang jujur.
10. Halaman Riwayat Undangan membaca `log_undangan`; filter status kelayakan dan status verifikasi di daftar anggota memakai kolom nyata, bukan placeholder.

### 3.4 Tampilan

11. Modal Kelola Anggota menampilkan hasil kelayakan otomatis dan status verifikasi dari `peserta`, dengan tombol Verifikasi/Tolak/Kirim/Cetak kembali aktif dan indikator loading.
12. Dashboard admin/helpdesk memakai hitungan `peserta` nyata (Verified, Pending, kuorum dasar) dan menampilkan "Rapat belum disiapkan" bila belum ada baris `peserta`.

### 3.5 Pengujian & acceptance

| Uji | Harapan |
|---|---|
| Mode A: simpanan di bawah minimum | `tidak_layak`, tombol Verifikasi butuh alasan |
| Mode A: kolektibilitas melebihi batas | `tidak_layak` |
| Mode B: pinjaman tidak lancar | `tidak_layak` |
| Anggota nonaktif | Boleh dinilai, tetapi presensi nanti ditolak |
| Siapkan peserta dua kali | Jumlah baris tetap, keputusan lama tidak hilang |
| Verifikasi lalu reload | Status tetap Verified (tersimpan) |
| Token QR | Unik per peserta–rapat, bukan turunan NBA, tidak tampil di log |
| Kirim/cetak dua kali | `log_undangan` bertambah, token sama |
| Helpdesk vs Admin | Helpdesk tidak dapat mengubah master maupun parameter |
| Rapat terkunci | Snapshot dan konfigurasi tidak berubah setelah Berlangsung |

**Selesai bila:** test hijau, `migrate:fresh --seed` bersih di MySQL, dan pengguna menyetujui hasil halaman Anggota & Undangan.

**Selesai 4 November 2026:** papan kuorum publik dan ringkasan Dashboard Scanner. Doorprize dan SHU selesai 3 November 2026; WhatsApp aktif lewat Integrasi v2.2; offline tidak dikerjakan (D-032).

## Fase 4 — Alokasi kas per Scanner (selesai)

**Tujuan:** kas hanya bisa ada satu kali per akun Scanner per rapat, diserahkan Keuangan, terlihat oleh Scanner yang bersangkutan, dan setiap penyerahan tercatat.

### 4.1 Skema dan aturan

1. Migration `kas_scanner`: `rapat_id`, `scanner_user_id`, `kas_awal`, `diserahkan_oleh`, `diserahkan_pada`, `diterima_pada` (konfirmasi Scanner, D-026, belum dipakai), `sisa_fisik`, `catatan_selisih`, `status`, `ditutup_oleh`, `ditutup_pada`; UNIQUE `(rapat_id, scanner_user_id)`.
2. `AlokasiKas::serahkan()` menolak penerima yang bukan akun role `scanner`, menolak rapat `Selesai`/`Dibatalkan`, dan menolak alokasi kedua untuk Scanner yang sama pada rapat yang sama. Tidak ada jalur ubah/top-up/transfer.
3. Setiap penyerahan menulis `log_audit` (`aksi=alokasi`, `entitas=kas_scanner`).
4. Hanya role **Keuangan** yang boleh mencatat alokasi (D-013). Admin memantau di halaman yang sama tanpa form.
5. `KasScanner::sisa()` adalah satu-satunya tempat saldo dihitung. Saat ini sama dengan kas awal karena pembayaran baru ada di Fase 5.

### 4.2 Tampilan

6. `/kas` (Admin, Keuangan): form alokasi untuk Keuangan, tabel posisi kas per Scanner dengan sisa dan status.
7. `/kas-saya` (Scanner): hanya baris milik akun yang login, dengan petunjuk bila belum dialokasikan.
8. Dashboard Keuangan memakai total kas awal/sisa nyata; dashboard Scanner memakai kas miliknya.

### 4.3 Pengujian

| Uji | Harapan |
|---|---|
| Keuangan mencatat alokasi | Baris `kas_scanner` + audit `alokasi` |
| Alokasi kedua pada rapat sama | Ditolak, nominal lama tidak berubah |
| Scanner/Admin mencoba mencatat alokasi | 403 |
| Penerima bukan akun Scanner | Ditolak sebelum menulis |
| Kas awal 0 atau negatif | Ditolak |
| Rapat Selesai/Dibatalkan | Ditolak |
| Rapat berbeda | Alokasi baru sah (satu per rapat) |
| Kas Saya | Hanya kas Scanner yang login |
| Admin membuka `/kas` | Tanpa form, hanya pemantauan |

**Tetap tertunda:** konfirmasi penerimaan kas & penggantian Scanner (D-026, bersama Tutup Kas di Fase 8), ambang peringatan saldo (D-025), penutupan kas dan rekonsiliasi (Fase 8).

**Koreksi nominal (30 Oktober 2026):** salah input nominal dapat dikoreksi oleh Keuangan lewat tombol **Koreksi** pada baris kas. Koreksi hanya mengganti `kas_awal` (bukan menambah), wajib alasan minimal 10 karakter, menolak nominal yang sama, hanya untuk kas berstatus `aktif`, dan tertutup otomatis setelah ada pembayaran (Fase 5). Jejak `sebelum`/`sesudah` + alasan tersimpan di `log_audit` (`aksi=koreksi`); tidak ada kolom baru dan tidak ada baris kas kedua. Penghapusan alokasi tetap tidak disediakan.

## Fase 5 - Presensi & transport (selesai)

**Sudah aktif (30 Oktober 2026):**
1. `presensi` menyimpan kehadiran per peserta (UNIQUE `peserta_id`) beserta nominal transport saat itu; `transport_pembayaran` hanya dibuat bila nominal > 0 (D-022).
2. `Rapat::bukaPresensi()` mengisi `presensi_dibuka_at` + `dibuka_oleh`; status rapat tetap `Verifikasi` (D-021). Hanya Admin, hanya sekali, hanya dari `Verifikasi`; aksi diaudit (`buka_presensi`).
3. `PenghitungTransport::hitung()` menerapkan BR-008: acuan jam mulai RAT, toleransi inklusif, potongan tier, nominal minimum 0.
4. `PresensiScan::catat()` menjadi satu-satunya gerbang scan: rapat belum Selesai/Dibatalkan, presensi dibuka, token cocok dengan rapat, peserta Verified, anggota masih Aktif, belum pernah presensi, foto + tanda tangan tersedia, kas Scanner ada dan cukup untuk nominal > 0.
5. Presensi, pembayaran, dan audit ditulis dalam satu transaksi; saldo hanya dihitung di `KasScanner::sisa()`. Baris kas dikunci (`lockForUpdate`) dan saldo diperiksa ulang di dalam transaksi agar dua scan bersamaan tidak membuat saldo minus; UNIQUE presensi diubah menjadi pesan "sudah tercatat".
6. Livewire `presensi-scan` memuat saldo, pemindai, dan tabel pembayaran dalam satu komponen sehingga semuanya ikut diperbarui setelah scan. Status presensi juga tampil di dashboard Scanner.
7. Koreksi kas otomatis tertutup setelah ada pembayaran.
8. Zona waktu aplikasi **Asia/Jakarta** + koneksi MySQL `+07:00`; tanpa ini jam datang dan jam mulai RAT selisih 7 jam.

**Peta Fase 5 (per 30 Oktober 2026):**

| # | Bagian | Status | Syarat selesai |
|---|---|---|---|
| 5.1 | Tabel `presensi` + `transport_pembayaran`, gerbang scan, hitung transport, kas terkunci, zona waktu | ✅ selesai | 140 tes hijau, jalur MySQL teruji |
| 5.2 | Admin membuka presensi (D-021) + audit | ✅ selesai | Hanya Admin, sekali, dari Verifikasi |
| 5.3 | Pemindai token manual di Kas Saya + saldo & pembayaran | ✅ selesai | Saldo ikut berubah setelah scan |
| 5.4 | Validasi tier potongan (bersambung dari menit 1) | ✅ selesai | Celah/tumpang tindih ditolak |
| 5.5 | Pemindaian QR dengan kamera | ✅ selesai | Token terbaca kamera mengisi kolom yang sama; tanpa logika server baru |
| 5.6 | Foto + tanda tangan (TTE) saat scan, privat (D-023) | ✅ selesai | Foto dari kamera + tanda tangan canvas wajib; berkas privat, permanen |
| 5.7 | Resi presensi (lihat/cetak) | ✅ selesai | Kode resi, nama, nominal, waktu, Scanner tampil dan bisa dicetak |
| 5.8 | Akses bukti untuk audit kas | ✅ selesai | Hanya Admin/Keuangan (Helpdesk 403), file dari private disk |
| 5.9 | Ringkasan presensi di dashboard Admin/Keuangan | ⏭ opsional | Jumlah hadir + total transport; kuorum final tetap Fase 7 |
| 5.10 | Ambang peringatan kas menipis | ✅ selesai (D-025) | Bawaan 5 × transport dasar, hanya informasi |
| 5.11 | Konfirmasi terima kas / ganti Scanner | ⛔ Fase 8 (D-026) | Aturan final, dikerjakan bersama Tutup Kas |
| 5.12 | Mode Presensi 2x | selesai 3 Nov 2026 (D-034) | `PresensiScan::pulang()`, tab Datang/Pulang di halaman pindai, T-PULANG-01..09 |
| 5.13 | Offline / sinkron | tidak dikerjakan (D-032) | Pemindaian berhenti total saat koneksi putus, tanpa antrean |

**Resi transport (5.7, selesai 30 Oktober 2026):** `GET /resi/{kode}` (`resi.show`) menampilkan kode resi, nama anggota, NBA, rapat, waktu bayar, nominal, dan nama Scanner yang menyerahkan, plus tombol **Cetak resi** (cetak peramban, `d-print-none` untuk tombol). Hak akses dicek di controller: Scanner pemilik pembayaran (`dibayar_oleh`), Admin, dan Keuangan; Helpdesk dan Scanner lain 403; tamu diarahkan ke login. Kode resi tidak dikenal → 404. Tautan resi muncul di tabel pembayaran **Kas Saya** dan pada pesan sukses setelah scan. Verifikasi resi oleh publik **tidak dibuat**: resi hanya untuk internal (D-027).

**Akses bukti audit (5.8, selesai 31 Oktober 2026):** menu **Presensi & Bukti** (`GET /presensi`) untuk Admin dan Keuangan mendaftar presensi rapat terakhir beserta nama, NBA, nominal, petugas pencatat, dan tautan **Foto** / **Tanda tangan**. Berkas disajikan `BuktiPresensiController` (`GET /presensi/{presensi}/bukti/{jenis}`) langsung dari disk privat `local`; Helpdesk dan Scanner 403, tamu diarahkan ke login, berkas hilang atau jenis tak dikenal 404. Tidak ada URL publik dan tidak ada penyalinan berkas ke disk publik. Pembukaan bukti tidak dicatat di `log_audit` karena bukan perubahan data.

**QR undangan & kamera (5.5, selesai 31 Oktober 2026):** undangan cetak `GET /undangan/{peserta}` (`undangan.cetak`) menampilkan kartu berisi nama, NBA, rapat, tanggal, jam presensi, lokasi, QR dari token rapat, dan kode cadangan teks; hanya Admin dan Helpdesk (middleware `role`), peserta belum Verified 404. QR dibuat server-side dengan `endroid/qr-code` (SVG, tanpa berkas tersimpan). Pemindai memakai `html5-qrcode` yang disimpan lokal di `public/vendor/html5-qrcode/` (bukan CDN) dan dimuat lewat `@assets`. Kamera menyala otomatis bila izin kamera untuk origin ini sudah pernah diberikan (`navigator.permissions`), selain itu tombol **Nyalakan kamera** memicu satu kali permintaan izin; setelah izin tersimpan, kunjungan berikutnya menyala sendiri. Token hasil pindai mengisi kolom token yang sama, kamera dimatikan otomatis, dan kolom manual tetap tersedia. Kamera hanya berjalan pada secure context (HTTPS atau localhost); di luar itu tombol disembunyikan dan muncul petunjuk memakai token manual.

**Peringatan kas menipis (5.10, selesai 31 Oktober 2026, D-025):** `Rapat::batasPeringatan()` mengembalikan `batas_peringatan_kas` atau 5 × `transport_dasar` bila kosong. Admin mengisi ambang di tab **Uang Transport** (boleh kosong). Banner **Kas menipis** muncul di **Kas Saya** (beserta perkiraan jumlah peserta yang masih bisa dilayani) dan badge di **Kas Scanner**; hanya informasi, tidak memblokir scan.

**Foto & TTE (D-023, selesai 30 Oktober 2026):** `PresensiScan::catat()` menerima foto (`UploadedFile`) dan tanda tangan (data URL PNG) sebagai argumen wajib. Foto disimpan di `presensi/{rapat_id}/{peserta_id}/foto.<ext>`, tanda tangan di `.../tte.png`, keduanya pada disk privat `local`; kolom `foto_path`/`tte_path` disembunyikan dari serialisasi. Server memeriksa tanda tangan (prefix PNG, magic byte, maksimal 2 MB) dan foto maksimal 5 MB. Bila transaksi gagal, berkas yang sudah ditulis dihapus kembali. Komponen Livewire `presensi-scan` memakai `WithFileUploads`, input `capture="environment"`, dan canvas tanda tangan yang mereset dirinya hanya setelah presensi berhasil. Retensi permanen, tanpa penghapusan otomatis.

| Uji | Harapan |
|---|---|
| Scan peserta Verified saat presensi dibuka | Presensi + pembayaran, kas berkurang |
| Scan token sama dua kali | Ditolak, tidak menggandakan presensi/kas |
| Presensi belum dibuka / rapat Selesai | Ditolak |
| Peserta belum Verified, anggota Nonaktif, token rapat lain | Ditolak |
| Kas tidak cukup atau Scanner tanpa kas | Ditolak sebelum menulis |
| Transport Rp0 (hangus) | Presensi tercatat, tanpa pembayaran, kas tetap |
| Buka presensi oleh non-Admin / rapat Draf / kedua kali | Ditolak |

**Catatan:** ambang kas menipis (D-025) dan konfirmasi terima kas (D-026) selesai di Fase 8; offline tidak dikerjakan (D-032); Mode Presensi 2x selesai (D-034).

## Fase 7a - Kuorum (selesai 3 November 2026)

**Hasil:** `App\Services\Kuorum::hitung(Rapat)` menjadi satu sumber angka kuorum. Populasi = peserta rapat yang anggotanya berjenis `biasa` dan berstatus `aktif`; basis `terverifikasi` menambah saringan `status_verifikasi = verified`. Target = `floor(persen_kuorum × populasi / 100) + tambahan_kuorum` memakai pembagian bulat eksak, bukan pecahan mengambang. Hadir = presensi rapat itu dari anggota biasa aktif; anggota `luar_biasa` dihitung terpisah dan tidak menambah kuorum. Status sah bila hadir ≥ target dan target > 0 (rapat tanpa peserta tidak dianggap sah). Jam kuorum tercapai diambil dari presensi ke-`target` menurut `datang_at`, sehingga tidak perlu kolom baru.

**Layar:** `GET /kuorum` (`kuorum.index`, middleware `role:admin,keuangan`) berisi komponen Livewire `kuorum-papan` dengan `wire:poll.10s`. Menampilkan hadir, target, populasi, persentase, progres, basis populasi, rumus yang dipakai, kekurangan, jam tercapai, dan jumlah kehadiran luar biasa. Menu **Kuorum** muncul untuk Admin dan Keuangan.

**Lanjutan selesai 4 November 2026:** `GET /papan-kuorum` dapat dibuka tanpa login dan menampilkan komponen `kuorum-ringkas` berisi angka agregat saja. Komponen yang sama tampil hanya pada Dashboard Scanner. Keduanya memakai `App\Services\Kuorum`, diperbarui setiap 10 detik, memiliki keadaan kosong saat belum ada rapat, dan tidak mengirim nama, NBA, foto, tanda tangan, nominal transport, atau baris presensi individu.

## Fase 7b - SHU Admin & saklar cek publik (selesai 3 November 2026)

**Hasil:** tabel `shu` (`(anggota_id, tahun_buku)` UNIQUE, komponen balas jasa saham dan pinjaman; total dihitung `Shu::total()`) dan `shu_publik` (satu baris per tahun buku: `aktif`, `dibuka_oleh`, `dibuka_at`, `ditutup_at`). `App\Services\ImportShu` mengimpor XLSX berisi `no_anggota`, `balas_jasa_saham`, `balas_jasa_pinjaman`; baris tidak valid dilaporkan per baris tanpa menghentikan baris lain, NBA yang belum ada di master anggota ditolak, sel nominal kosong dianggap 0, dan impor ulang memperbarui baris yang sama. Setiap perubahan masuk `log_audit` (`entitas = shu`, alasan "impor XLSX tahun buku X").

**Layar:** `GET /shu` (`shu.index`, `role:admin`) menampilkan tahun buku yang dipilih, tabel SHU berpaginasi 50 baris, total setahun, input manual, koreksi, dan kartu saklar cek publik. Aksi manual menerima NBA yang sudah ada di master; kombinasi anggota–tahun buku tidak boleh ganda. Koreksi nominal wajib alasan minimal 10 karakter dan mencatat nilai sebelum/sesudah. Aksi lain: `POST /shu/impor`, `GET /shu/template` (template XLSX), `POST /shu/publik/buka`, `POST /shu/publik/tutup`. Membuka cek publik ditolak bila tahun buku itu belum punya data SHU. Saklar tidak bergantung pada status rapat.

**Belum termasuk:** halaman publik cek SHU, dikerjakan dan selesai di Fase 7c di bawah.

## Fase 7c - Cek SHU publik (selesai 3 November 2026)

**Hasil:** tabel `log_cek_shu` (`nba_dicari`, `tahun_buku`, `ip_address`, `hasil` enum `ditemukan|tidak_ditemukan|ditolak_captcha|dibatasi|tidak_valid`, `created_at` berindeks; tanpa nominal SHU). `CekShuController` memakai `Integrasi::verifikasiCaptcha()` (fail closed), rate limit 10 percobaan/menit per IP, dan hanya melayani tahun buku yang saklar publiknya `aktif`. Setiap percobaan yang mencapai endpoint dicatat. `PurgeLogCekShu` (`cek-shu:purge`) menghapus bertahap baris lebih tua dari 14 hari dan dijadwalkan `daily()`.

**Layar:** `GET /cek-shu` (publik, `layouts.guest`) berisi pilihan tahun buku yang dibuka, isian NBA, widget Cloudflare Turnstile memakai site key tersimpan, dan hasil berupa balas jasa saham, balas jasa pinjaman, serta total. Nama anggota tidak ditampilkan. Bila CAPTCHA belum siap atau belum ada tahun buku terbuka, form tidak melayani pencarian. Riwayat 14 hari tampil di halaman **Audit** (Admin saja).

**Belum termasuk:** pengesahan risiko privasi sebelum publikasi produksi (tetap wajib menurut BR-SHU-LOG-01).

## Fase 7d - Doorprize (selesai 3 November 2026)

**Hasil:** kolom `rapat.jumlah_pemenang_doorprize` (bawaan 10) dan tabel `doorprize_pemenang` (`(rapat_id, peserta_id)` UNIQUE, `hasil` menang/gugur, `urutan` untuk pemenang). `App\Services\Doorprize` memilih kandidat acak dari peserta yang punya presensi pada rapat itu dan belum punya baris hasil; `putuskan()` menulis hasil + `log_audit` dalam transaksi.

**Layar:** menu **Doorprize** (`doorprize.index`, `role:admin`) berisi komponen Livewire `doorprize-undian`: kartu target/menang/gugur/sisa, tombol **Undi**, pop-up konfirmasi (masih ada = menang, sudah pulang = gugur, batal = tidak menyimpan), serta daftar pemenang dan daftar gugur. Satu undian per klik; penggantian kandidat gugur tidak otomatis. Parameter jumlah pemenang diisi di **Manajemen RAT → tab Doorprize**.

**Belum termasuk:** daftar hadiah per item (tidak diperlukan karena hadiah tidak dicatat per nama).

## Fase 8 - Tutup kas & rekonsiliasi (selesai 3 November 2026)

**Hasil:** `App\Services\TutupKas` menutup kas per Scanner (`status=ditutup`, `sisa_fisik`, `catatan_selisih`, `ditutup_oleh`, `ditutup_pada`) dan menulis `log_audit` `tutup_kas` berisi `selisih`. Selisih = `sisa_fisik` − `sisa()` (kas awal − pembayaran tercatat); selisih bukan nol mewajibkan catatan (D-031). `AlokasiKas::terima()` mengubah `menunggu` → `aktif` + `diterima_pada` + audit `terima_kas`, hanya oleh Scanner pemilik dan hanya sekali (D-026). `PresensiScan::catat()` menolak scan selama kas masih `menunggu`.

**Layar:** banner **Saya sudah menerima kas** di **Kas Saya** (`POST /kas-saya/terima`, `role:scanner`); badge status `menunggu`/`aktif`/`ditutup` di halaman Kas Scanner; menu **Rekonsiliasi Kas** (`GET /rekonsiliasi`, `role:keuangan`) menampilkan kas awal, terbayar, sisa seharusnya, sisa fisik, selisih, status, penutup dan catatan, dengan form **Tutup kas** per baris (`POST /rekonsiliasi/{kas}`).

**Belum termasuk:** persetujuan Admin atas selisih (tidak dipakai, D-031). Laporan FR-011 dikerjakan di Fase 8b.

## Fase 8b - Laporan FR-011 (selesai 3 November 2026)

**Hasil:** `App\Services\Laporan` menghitung tujuh bagian dari tabel nyata tanpa tabel baru: peserta & verifikasi, undangan per petugas, presensi & transport per petugas, kas per Scanner (kas awal, terbayar, sisa seharusnya, sisa fisik, selisih, status), kuorum (memakai `App\Services\Kuorum`), SHU per tahun buku, dan doorprize. Bagian dibatasi role sesuai ROLE_PERMISSION_MATRIX: Admin semua; Keuangan presensi, kas, dan kuorum; Helpdesk peserta dan undangan; Scanner tidak diberi akses (ringkasan diri sudah ada di Kas Saya).

**Layar:** menu **Laporan** (`GET /laporan`, `role:admin,helpdesk,keuangan`) dengan pemilih rapat (`?rapat=`) sehingga RAT tahun lalu tetap dapat dibuka, kartu kuorum, dan tabel per bagian. Tombol **Ekspor XLSX** (`GET /laporan/ekspor`) memakai `App\Support\LaporanXlsx` untuk menulis satu lembar berisi bagian yang sama dengan halaman; nominal ditulis sebagai angka berformat ribuan.

**Belum termasuk:** ekspor PDF, filter tanggal, dan rekap lintas rapat dalam satu berkas.

## Fase 10 - Kesiapan operasional (sebagian selesai)

**Hasil:** command `erat:backup` menulis cadangan `.sql` lewat `mysqldump` (kredensial dikirim sebagai berkas sementara `--defaults-extra-file`, bukan argumen proses) dan mengarsipkan `storage/app/private` menjadi `.zip`; retensi bawaan 7 hari hanya menyentuh berkas berawalan `erat-`/`media-` (`App\Support\Backup`). Cadangan dijadwalkan harian pukul 23.00 di `routes/console.php` bersama purge log cek SHU. Command `erat:benchmark` mengukur waktu `Kuorum::hitung` dan `Laporan::rekap` pada data nyata dan gagal bila kuorum melewati target 10 detik. Uji restore dilakukan ke database uji `erat_restore_uji` lalu dihapus: jumlah baris `users`/`rapat`/`log_audit`/`log_cek_shu` identik.

**Dokumen:** `docs/02-workflows/PANDUAN_PETUGAS.md` (panduan satu halaman per role), `docs/05-quality/UAT_SCENARIOS.md` (kasus 11-21 + form bukti), `docs/06-operations/DEPLOYMENT_RUNBOOK.md` (perintah cadangan dan langkah restore).

**Belum termasuk:** uji beban HTTP bersamaan pada perangkat nyata, pemasangan cron `schedule:run` di server produksi, dan tanda tangan UAT petugas (harus dilakukan manusia pada hari-H).

## Cara agent bekerja
Lihat kebutuhan FR pada PRD, aturan BR dan keputusan, tulis test terlebih dahulu, implementasi service/policy, UI, perbaiki tes, update traceability, minta review. Perubahan aturan pada satu fase mengharuskan pembaruan seluruh dokumen terkait.

## Manajemen pengguna v2.3 - selesai 4 November 2026
Menu **Pengaturan → Pengguna** hanya untuk Admin. `PenggunaController` menyediakan daftar, tambah, ubah nama/username/role, ganti sandi opsional, dan hapus akun. Username unik, dinormalisasi ke huruf kecil, dan menjadi identitas login; email tidak dipakai autentikasi (D-041). Role dibatasi tepat empat; sandi minimal 8 karakter, disimpan ter-hash dan tidak pernah masuk audit. Admin tidak dapat menghapus atau menurunkan role akun sendiri. Reset total hanya membuat ulang akun Admin (D-035). Helpdesk tetap tidak dapat melakukan presensi (D-036).

## Ringkasan akses role - selesai 4 November 2026
Dashboard Helpdesk menampilkan jadwal/status/kriteria kelayakan; Dashboard Scanner menampilkan jadwal/status/mode/gerbang presensi; Dashboard Keuangan menampilkan jadwal/status/mode dan konfigurasi transport. Seluruhnya baca-saja. Helpdesk tetap dapat mencari dan memproses verifikasi/undangan tetapi tidak dapat mengubah master anggota; Keuangan tidak mendapat halaman master anggota. Route perubahan master dan rapat tetap Admin-only (D-039).

## Navigasi sidebar - selesai 4 November 2026
`config/menu.php` membagi menu menurut alur kerja per role dan mempertahankan indikator route aktif. Sidebar Admin hanya memiliki satu tautan **Pengaturan**; partial `partials/pengaturan-nav.blade.php` menyatukan **Pengguna**, **Integrasi**, dan **Aplikasi** dalam navbar horizontal `nav-overflow` mode pills pada seluruh halaman terkait. Audit berada di span Pelaporan. Scanner memakai istilah **Status Koneksi**, bukan Status Sinkron, sesuai kebijakan tanpa antrean offline (D-032, D-040).


### Pekerjaan wajib BR-SHU-LOG-01 - selesai 3 November 2026
Migration `log_cek_shu` (indeks `created_at`, enum `hasil`, tanpa kolom nominal) diterapkan. `CekShuController` memeriksa rate limit (10/menit per IP) dan CAPTCHA server-side **sebelum** query SHU, gagal-tertutup bila integrasi belum siap, lalu mencatat setiap percobaan ke `log_cek_shu`. Command `cek-shu:purge` menghapus bertahap (default 1000 baris/batch) baris `created_at` lebih tua dari 14 hari dan dijadwalkan harian lewat `routes/console.php`. Riwayat hanya tampil di halaman Audit (Admin). Risiko privasi NBA + CAPTCHA tetap dicatat: CAPTCHA bukan bukti kepemilikan NBA.


## Pekerjaan Integrasi Pengaturan (v2.2) — selesai 3 November 2026

**Hasil:** tabel `pengaturan_integrasi` (satu baris per layanan: `captcha`, `fonnte`); secret memakai encrypted cast dan disembunyikan dari serialisasi; `PengaturanIntegrasi` menyimpan status uji, versi credential, dan Admin terakhir. `App\Services\Integrasi` adalah satu-satunya pintu baca/tulis: `simpan()` mempertahankan secret lama bila input kosong, `nonaktifkan()` menghapus secret secara eksplisit, `verifikasiCaptcha()` memanggil `challenges.cloudflare.com/turnstile/v0/siteverify` dan gagal-tertutup, `kirimWhatsapp()` memanggil `api.fonnte.com/send` dan hanya mencatat `diterima_api` bila vendor menerima (status `terkirim` hanya untuk bukti vendor). Halaman **Pengaturan → Integrasi** aktif: simpan, uji, dan nonaktifkan untuk kedua layanan; menyimpan form tidak pernah memanggil vendor. Audit mencatat layanan, status aktif, dan versi secret — tanpa secret. Pengiriman undangan WhatsApp kini memakai Fonnte; kegagalan tidak mengubah status Verified dan tetap tercatat di `log_undangan`.

Pemakaian CAPTCHA di endpoint Cek SHU publik telah selesai pada Fase 7c dan tetap gagal-tertutup saat konfigurasi tidak siap.

**Sisa butir rencana awal:** cache invalidation belum diperlukan karena pengaturan dibaca langsung dari database per permintaan. APP_KEY tetap di `.env`.
