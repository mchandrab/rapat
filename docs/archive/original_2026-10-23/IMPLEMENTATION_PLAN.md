# IMPLEMENTATION PLAN — eRAT (Electronic Rapat Anggota Tahunan)

Versi 1.0 — 23 Oktober 2026
Status: Draf awal hasil diskusi kebutuhan
Penulis: Muse (untuk Chandra)

## 1. Gambaran Umum

Dokumen ini adalah urutan langkah konkret membangun **eRAT** dari nol: sistem informasi manajemen **Rapat Anggota Tahunan (RAT)** koperasi berbasis web. eRAT mendigitalisasi, mengamankan, dan mempercepat proses registrasi, presensi, validasi aturan bisnis, perhitungan kuorum, pelacakan uang transport berbasis waktu, hingga audit kehadiran memakai foto wajah peserta — plus fitur cek SHU dan undian doorprize untuk peserta yang hadir.

- **Sifat proyek:** bangun ulang total. **Tanpa migrasi data lama**, berdiri sendiri, **tidak terhubung ke ERP**. Repo baru (dibuat terpisah dari repo aplikasi lama).
- **Motivasi:** bersih dari masalah keamanan aplikasi lama (field sensitif anggota pernah bisa diubah dari sisi client) sekaligus menambah fitur baru (konfigurasi dinamis per rapat, tanda tangan digital, foto wajah untuk audit).
- **Stack:** Laravel 13 + PHP 8.3 + MySQL. Antarmuka **Tabler penuh** (CSS, JavaScript, dan ikon semuanya dari template Tabler, bukan campuran framework lain). Layar interaktif (presensi, dashboard kuorum realtime) memakai Blade + Livewire.
- **Arsitektur:** monolit Laravel satu aplikasi. Tidak ada konsep multi-koperasi/tenant.
- **Role login** (paket `spatie/laravel-permission`) — hanya ada empat role:
  - `admin` — mengelola master anggota dan field sensitif secara server-side; setup rapat & kriteria; membangkitkan kandidat, mengunci undangan, dan ikut melakukan verifikasi pra-rapat; koreksi presensi; doorprize; pengaturan, user, dan role; semua laporan; serta log audit.
  - `helpdesk` — mencari peserta dan ikut melakukan verifikasi pra-rapat melalui panel Kelola Anggota; mengoreksi hanya data non-sensitif; melihat undangan, status hadir, dan status transport; menampilkan/mengirim ulang QR dengan token yang sama persis; serta melakukan presensi manual hanya untuk peserta terundang dan terverifikasi ketika QR gagal, dengan alasan wajib dan catatan audit. Peserta tidak terundang diteruskan ke admin. Helpdesk boleh melihat riwayat seperlunya, tetapi **tidak memiliki lookup SHU dan tidak dapat mengakses foto/tanda tangan elektronik (TTE)**; pengecekan SHU dilakukan peserta melalui form publik.
  - `scanner` — mengoperasikan stasiun rapat aktif untuk scan QR datang/pulang, tab ketik manual berdasarkan nomor anggota, mode offline dengan sinkronisasi anti-duplikat, pengambilan foto dan tanda tangan, pembayaran tunai transport di titik scan, serta ringkasan stasiun/kuorum. Scanner tidak boleh menelusuri master data, mengoreksi presensi, mengubah konfigurasi, mengelola SHU, menjalankan doorprize, membuka laporan/audit, atau melakukan rekonsiliasi kas.
  - `keuangan` — memantau konfigurasi transport yang diatur admin dan mengelola data SHU (balas jasa saham & pinjaman) melalui input/impor/koreksi teraudit; membuka kas awal per meja scanner, memantau sisa kas, dan melakukan rekonsiliasi; melihat daftar hadir, kuorum, dan media foto/TTE melalui route berotorisasi; serta membuat laporan transport/SHU.
  - **Anggota/peserta bukan role login.** Tidak ada akun peserta. Token QR deterministik per anggota adalah kredensial presensi, bukan kredensial login. Halaman publik terbatas hanya untuk papan skor agregat, undangan berdasarkan token, cek SHU berdasarkan nomor anggota, dan verifikasi resi teks.
- **Bahasa antarmuka:** Indonesia. **Kode:** Inggris (nama class, method, variabel).
- **Testing:** Pest, database SQLite `:memory:` untuk test.
- **Cara kerja:** vibe coding. Chandra mengarahkan, agent menulis kode + test, Chandra mereview tiap fase, iterasi sampai beres.
- **Aturan keras:** satu fase selesai 100% (semua test Pest hijau + review Chandra disetujui) baru lanjut ke fase berikutnya. **Dilarang lompat fase, dilarang mengerjakan dua fase sekaligus.**
- **Aturan angka bisnis:** semua angka dan ambang (ambang kuorum, batas tier transport, kriteria undangan) tersimpan di pengaturan/konfigurasi per rapat. **Dilarang hardcode** di controller, service, Livewire, atau Blade.
- **Aturan keamanan (pelajaran dari aplikasi lama, berlaku sejak Fase 0):**
  1. Field sensitif anggota (`simpanan_wajib`, `kolektibilitas`, `status_anggota`, status kelancaran pinjaman) **hanya** diubah server-side oleh admin berizin. Tidak boleh ada jalur dari input client / properti publik Livewire yang bisa menentukan nilai field ini.
  2. Otorisasi ditegakkan per aksi lewat policy/role — bukan cuma menyembunyikan tombol di UI.
  3. Login **wajib** rate limiting, dengan pesan kesalahan Bahasa Indonesia.
  4. Foto wajah dan tanda tangan peserta disimpan di **disk privat**; hanya bisa disajikan lewat route berotorisasi kepada `admin` dan `keuangan`. Helpdesk selalu ditolak (403) untuk foto/TTE. Scanner hanya boleh mengambil dan mengunggah media di dalam transaksi presensi aktif, bukan membuka arsip media. Halaman verifikasi resi publik hanya menampilkan teks dan tidak pernah memberi akses foto/TTE.
  5. Semua perubahan data sensitif, transisi verifikasi pra-rapat, koreksi presensi, presensi manual helpdesk, pembayaran tunai scanner, rekonsiliasi kas, lookup SHU publik, dan koreksi SHU tercatat di **log audit** (siapa/petugas atau sumber publik, kapan, nilai sebelum → sesudah bila relevan).
  6. Token QR dibuat server-side secara **deterministik** dari HMAC nomor anggota dengan `APP_KEY`. Token anggota yang sama selalu sama selama `APP_KEY` tidak berubah dan **tidak pernah diganti/diterbitkan sebagai token baru oleh aplikasi**. Perubahan `APP_KEY` adalah aturan operasional di luar skenario aplikasi karena akan mengubah token hasil derivasi.
- **Catatan lingkungan:** dikembangkan di Laragon milik Chandra (PHP 8.3.36). Repo baru; **tidak push ke GitHub tanpa izin eksplisit Chandra.**

## 2. Fase 0 — Fondasi

**Tujuan:** Aplikasi bisa diinstall, user internal bisa login dengan aman, empat role dan hak aksesnya berfungsi, ada tabel pengaturan (key–value), log audit, dan master data Anggota yang field sensitifnya terkunci server-side. Peserta tidak dibuatkan akun login dan tidak ada portal peserta.

**Cakupan fitur:**
1. Project Laravel 13 baru; konfigurasi MySQL; seluruh aset Tabler (CSS, JS, ikon) dipasang sebagai template utama dan dipakai di semua halaman, termasuk halaman login.
2. Autentikasi (login/logout) dengan **rate limiting** pada percobaan login (percobaan gagal beruntun dibatasi; pesan penolakan dalam Bahasa Indonesia).
3. Install `spatie/laravel-permission`; buat hanya role login `admin`, `helpdesk`, `scanner`, dan `keuangan`, beserta permission per modul dan konteks (contoh: `anggota.kelola`, `anggota.koreksi-nonsensitif`, `anggota.verifikasi`, `rapat.kelola`, `undangan.lihat`, `presensi.scan`, `presensi.ketik-manual`, `presensi.manual-helpdesk`, `presensi.koreksi`, `presensi.sinkron-offline`, `transport.konfigurasi`, `transport.bayar-tunai`, `kas.kelola`, `kas.rekonsiliasi`, `shu.kelola`, `shu.lihat`, `media.lihat`, `doorprize.kelola`, `laporan.lihat`, `audit.lihat`). Menu tampil sesuai role, dan route/service menolak role yang tidak berhak (403). User tidak memiliki relasi login ke data anggota/peserta.
4. Migrasi + model:
   - `pengaturan` (key–value: nama koperasi, teks kop undangan, dsb.)
   - `log_audit` (user_id, aksi, entitas, id entitas, data sebelum, data sesudah, waktu)
   - `anggota` (no anggota unik, nama, kontak, alamat, jenis anggota `biasa`/`luar_biasa`, status anggota, saldo simpanan wajib, kolektibilitas/NPL, status kelancaran pinjaman). Field sensitif hanya bisa ditulis lewat jalur admin berizin (FormRequest + policy); tidak ada binding langsung dari properti publik Livewire.
5. CRUD master Anggota untuk admin (daftar, tambah, ubah, nonaktifkan — nonaktif, bukan hapus permanen) + **impor awal** data anggota dari file spreadsheet dengan laporan baris berhasil/gagal. Helpdesk tidak mengelola master secara umum; izin koreksi non-sensitifnya baru dipakai pada panel verifikasi pra-rapat di Fase 1 dengan whitelist field (mis. nama, kontak, alamat) dan larangan keras menyentuh field sensitif.
6. Seeder: role + permission, satu user admin, pengaturan default.
7. Helper pencatatan audit (`AuditLogger`) yang dipakai semua fase berikutnya; halaman daftar log audit hanya untuk admin.

**Deliverables:**
- Aplikasi berjalan dengan template Tabler penuh; login + rate limit berfungsi.
- Skema `pengaturan`, `log_audit`, `anggota`; CRUD + impor Anggota; halaman log audit.
- Seeder role/user/pengaturan; factory `AnggotaFactory` dan `UserFactory` dengan state hanya untuk `admin()`, `helpdesk()`, `scanner()`, dan `keuangan()` — tanpa state atau relasi user-anggota.

**Test wajib:** (detail di `TESTING.md`)
- Login sukses/gagal; login gagal beruntun kena throttle dengan pesan Bahasa Indonesia.
- User tanpa permission ditolak 403 pada route terproteksi; user belum login diarahkan ke login.
- **Regresi keamanan utama:** upaya mengubah `simpanan_wajib`/`kolektibilitas`/`status_anggota` tanpa izin (termasuk lewat request yang meniru payload Livewire) ditolak dan nilai di database tidak berubah.

**Kriteria selesai:**
- `php artisan test` hijau untuk seluruh test Fase 0.
- Login sebagai admin, helpdesk, scanner, dan keuangan — masing-masing hanya melihat menu sesuai perannya. Scanner hanya masuk ke layar scan rapat aktif; helpdesk tidak menemukan aksi ubah field sensitif, konfigurasi, pembayaran, data SHU, atau koreksi presensi selesai; keuangan tidak menemukan aksi ubah kriteria, presensi, doorprize, atau pengaturan/user. Tidak ada login peserta yang bisa dibuat atau digunakan.
- Impor contoh data anggota berhasil dan laporannya terbaca jelas.
- Review Chandra: disetujui.

## 3. Fase 1 — Rapat, Verifikasi Pra-Rapat & Undangan

**Tujuan:** Admin bisa membuat rapat dengan seluruh konfigurasi dinamisnya, membangkitkan daftar kandidat dari kriteria, lalu admin dan helpdesk memverifikasi kandidat melalui panel Kelola Anggota pra-rapat. Undangan formal hanya diterbitkan untuk kandidat berstatus `verified` dengan hasil **Layak**; syarat pintu presensi satu-satunya adalah status `verified`, sehingga kandidat `verified` dengan hasil Tidak Layak tetap dapat masuk melalui pemeriksaan stasiun.

**Cakupan fitur:**
1. Migrasi + model `rapat`: jenis (`RAT` / rapat biasa), judul, tanggal & jam, lokasi, dan konfigurasi per rapat:
   - `mode_presensi`: **1x** (datang saja) atau **2x** (datang + pulang) — dipilih saat setup rapat. (Konsep lama "presensi dua sesi" untuk rapat biasa = presensi datang dan presensi pulang.)
   - `mode_kriteria_undangan`: **Mode A** atau **Mode B** (lihat poin 2).
   - Konfigurasi kuorum: `persen_kuorum`, `tambahan_kuorum` (bawaan 1), dan basis populasi (`total` atau `terverifikasi`) — dirumuskan lengkap di Fase 3 dan hanya menghitung jenis anggota `biasa`.
   - Konfigurasi transport tidak dikelola pada setup rapat fase ini; nominal dasar, tier potongan, grace period, batas hangus, kas, dan rekonsiliasi dikelola pada Fase 3.
2. **Kriteria undangan dinamis** (nilai ambang diisi per rapat, tidak hardcoded):
   - **Mode A:** kandidat dipilih bila `simpanan_wajib >= minimum` **DAN** `kolektibilitas/NPL <= maksimum` (ambang minimum & maksimum diisi saat setup rapat).
   - **Mode B:** kandidat dipilih berdasarkan **lancar/tidaknya pinjaman** (mis. hanya anggota berstatus lancar).
3. `UndanganService`: membangkitkan baris kandidat/peserta dari kriteria rapat. Kriteria yang dipakai untuk keputusan verifikasi dibekukan sebagai **snapshot pada saat verifikasi**, bukan sekadar saat kandidat dibangkitkan, sehingga panel mencatat nilai persis yang dilihat dan dinilai petugas.
4. Migrasi + model `peserta` (kandidat/undangan per rapat): rapat, anggota, status verifikasi (`pending`/`verified`/`rejected`), hasil kelayakan (`layak`/`tidak_layak`, terisi pada keputusan verifikasi), catatan hasil/penolakan/koreksi, `verified_by`, `verified_at`, snapshot kriteria saat verifikasi, dan status kehadiran awal `belum_hadir`.
5. **Panel verifikasi pra-rapat di Kelola Anggota** untuk `admin` dan `helpdesk`:
   - Status awal semua kandidat adalah `pending`.
   - Aksi verifikasi mengubah `pending → verified`, menyimpan snapshot kriteria saat verifikasi, mengisi `verified_by` dan `verified_at` dari user petugas dan waktu server, lalu mencatat hasil **Layak** atau **Tidak Layak**. Hasil **Tidak Layak wajib disertai catatan**; tanpa catatan, keputusan ditolak.
   - Status `rejected` dipakai bila data kandidat tidak dapat diverifikasi; status ini tidak menerima undangan formal. Alasan/catatan penolakan wajib dicatat agar koreksi dapat ditindaklanjuti.
   - Kandidat `rejected` dapat dikoreksi lalu dikembalikan ke `pending` untuk verifikasi ulang. Saat kembali ke `pending`, `verified_by`, `verified_at`, dan hasil kelayakan dikosongkan; riwayat penolakan, koreksi, dan transisi tetap tercatat audit.
   - Koreksi oleh helpdesk hanya boleh menyentuh field non-sensitif melalui whitelist. Field sensitif yang dikirim helpdesk — termasuk lewat payload Livewire — ditolak server-side. Koreksi field sensitif hanya boleh dilakukan admin lewat jalur resmi.
   - Setiap transisi dan koreksi tercatat di log audit.
6. **Undangan formal dan syarat pintu:** undangan diterbitkan untuk semua peserta berstatus `verified`, baik hasil **Layak** maupun **Tidak Layak** (Tidak Layak wajib catatan petugas). Syarat pintu presensi satu-satunya adalah status `verified`. Peserta `pending` atau `rejected` selalu ditolak di pintu walaupun token/nomor anggotanya benar.
7. **Kunci daftar undangan:** setelah dikunci admin, daftar undangan formal tidak bisa dibangkitkan ulang dan tidak bisa diubah massal; daftar tersebut memuat seluruh peserta `verified` (Layak maupun Tidak Layak) untuk pemeriksaan status di pintu. Koreksi individual sesudah kunci hanya oleh admin dan tercatat di log audit.
8. **Token QR deterministik per anggota:** server menurunkan token dari `HMAC-SHA256(no_anggota, APP_KEY)` dan merendernya sebagai QR. Untuk nomor anggota dan `APP_KEY` yang sama, token selalu sama persis; tidak ada aksi ganti token di aplikasi. Admin/helpdesk dapat menampilkan atau mengirim ulang undangan secara on-demand per peserta — tanpa broadcast massal — dan selalu memakai token yang sama persis. Halaman publik undangan berdasarkan token hanya menampilkan undangan formal pemilik token tersebut; token yang tidak cocok menghasilkan 404 dan tidak membocorkan data anggota lain.

**Deliverables:**
- CRUD rapat + halaman konfigurasi dinamis per rapat.
- `UndanganService` + daftar kandidat + panel verifikasi pra-rapat admin/helpdesk dengan status, hasil kelayakan, catatan, snapshot, `verified_by`, dan `verified_at`.
- Daftar undangan berisi semua peserta `verified` (Layak maupun Tidak Layak), mekanisme kunci daftar, QR/token deterministik, dan halaman publik undangan per token.

**Test wajib:** (detail di `TESTING.md`)
- Mode A menyaring benar tepat di batas (`>=` minimum dan `<=` maksimum lolos; satu satuan di luar batas tidak lolos).
- Mode B hanya mengambil anggota berstatus lancar.
- Setiap transisi verifikasi diuji: `pending → verified` dengan hasil Layak; `pending → verified` dengan hasil Tidak Layak dan catatan wajib; `pending → rejected`; `rejected → dikoreksi → pending → verified`; snapshot dan penanda verifikator sesuai saat aksi dilakukan.
- Helpdesk dapat mengoreksi field non-sensitif pada panel, tetapi setiap upaya mengubah field sensitif ditolak dan nilai database tidak berubah (regresi P0).
- Semua peserta `verified` (Layak maupun Tidak Layak) menerima undangan dan lolos syarat pintu; `pending`/`rejected` ditolak di pintu dan tidak menerima undangan.
- Token hasil derivasi untuk anggota yang sama selalu sama persis pada pembangkitan dan pengiriman ulang; token anggota lain atau token untuk anggota yang hanya terdaftar pada rapat lain ditolak pada rapat aktif.

**Kriteria selesai:**
- `php artisan test` hijau untuk seluruh test Fase 0–1.
- Demo: dua rapat contoh (satu mode 1x + Mode A, satu mode 2x + Mode B) berhasil dibuat; kandidat contoh melewati panel verifikasi, daftar undangan berisi semua yang `verified`, dan pengiriman ulang undangan menampilkan token yang sama persis.
- Review Chandra: disetujui.

## 4. Fase 2 — Presensi

**Tujuan:** Di hari rapat, stasiun scanner bisa mencatat kehadiran peserta terverifikasi dengan cepat lewat scan QR atau tab ketik manual nomor anggota: presensi datang dengan foto wajah dan tanda tangan digital, serta presensi pulang untuk rapat mode 2x — tanpa duplikat, termasuk ketika stasiun bekerja offline lalu menyinkronkan antrean. Helpdesk menangani pengecualian secara terbatas dan teraudit, sedangkan koreksi presensi hanya dilakukan admin.

**Cakupan fitur:**
1. Migrasi + model `presensi`: peserta, rapat, `waktu_datang`, `waktu_pulang` (mode 2x), path foto wajah, path tanda tangan, status kehadiran, metode pencatatan (`qr`, `ketik_manual_scanner`, `manual_helpdesk`, dan varian offline-nya), `client_event_id` unik untuk idempotensi sinkronisasi, stasiun/meja, alasan presensi manual helpdesk, petugas pencatat, serta status sinkronisasi.
2. **Layar stasiun scanner** (Livewire) khusus role `scanner` dan hanya untuk rapat aktif; petugas login terlebih dahulu. Tersedia dua tab yang sama-sama berada dalam wewenang scanner:
   - **Scan QR:** peserta memindai token undangan untuk presensi datang/pulang.
   - **Ketik Manual:** petugas mengetik nomor anggota untuk menemukan tepat satu kandidat/peserta pada rapat aktif. Tab ini bukan penelusuran master dan tidak menampilkan daftar anggota; hasil hanya dapat dilanjutkan bila status kandidat `verified` — hasil kelayakan tidak menjadi syarat tambahan di pintu.
   Hasil yang tampil bersifat minimal (berhasil/gagal dan identitas seperlunya untuk melanjutkan foto/tanda tangan).
3. **Validasi token server-side:** endpoint scan wajib diakses petugas yang login dan berizin. Sistem menurunkan/memvalidasi token HMAC dari nomor anggota, memastikan token cocok dengan anggota yang dimaksud, lalu memastikan anggota tersebut adalah peserta `verified` pada rapat aktif. Token anggota lain, token anggota yang hanya menjadi peserta rapat lain, token tidak dikenal, serta token peserta `pending`/`rejected` ditolak. Kegagalan diarahkan ke helpdesk.
4. **Foto wajah saat presensi datang:** scanner mengambil foto dari kamera perangkat dalam alur aktif, lalu file disimpan di **disk privat** (bukan folder publik). Foto dipakai untuk audit manual — bukan pencocokan wajah otomatis. Hak membuka arsip foto/TTE hanya dimiliki `admin` dan `keuangan` melalui route berotorisasi; helpdesk selalu 403; scanner hanya mengambil/mengunggah dalam transaksi aktif dan tidak dapat membuka arsip.
5. **Tanda tangan digital wajib:** setelah presensi datang tercatat melalui scanner, peserta membubuhkan tanda tangan di layar (canvas) sebagai validasi kehadiran; presensi tidak dianggap lengkap tanpa tanda tangan.
6. **Presensi pulang (khusus mode 2x):** peserta yang sudah datang memindai QR yang sama atau memakai tab ketik manual untuk dicatat pulangnya. Pada rapat mode 1x, fitur pulang tidak tersedia sama sekali.
7. **Cegah duplikat:** presensi datang kedua untuk peserta yang sama pada rapat yang sama ditolak; presensi pulang tanpa presensi datang ditolak; pulang kedua ditolak. Kunci unik database dan validasi service sama-sama menegakkan aturan ini untuk jalur online maupun hasil sinkronisasi offline.
8. **Mode offline scanner + sinkronisasi anti-duplikat:** bila koneksi terputus, stasiun menyimpan entri presensi secara lokal dalam antrean terproteksi beserta `client_event_id` UUID, rapat, identitas peserta/token atau nomor anggota, jenis peristiwa datang/pulang, waktu peristiwa di perangkat, dan media yang diperlukan. Saat koneksi kembali, antrean dikirim berurutan ke endpoint sinkronisasi petugas login. Server memvalidasi ulang rapat aktif, status `verified`, dan idempotensi: pengiriman ulang `client_event_id` yang sama tidak membuat data baru; entri offline untuk anggota yang ternyata sudah hadir dari jalur/stasiun lain **ditolak saat sinkron** sebagai konflik duplikat, tidak menimpa presensi asli, tidak membuat presensi kedua, dan masuk daftar konflik untuk ditinjau petugas. Entri yang diterima memakai waktu peristiwa offline sebagai waktu presensi setelah lolos validasi.
9. **Meja helpdesk untuk pengecualian:** helpdesk dapat mencari dan memverifikasi peserta; melihat undangan, status hadir, dan status transport; serta menampilkan/mengirim ulang undangan dengan token yang sama persis. Bila alur scanner tidak dapat dipakai, helpdesk hanya boleh melakukan presensi manual untuk **peserta terundang dan terverifikasi**, dengan alasan wajib yang tercatat audit. Peserta tidak terundang atau belum terverifikasi tidak diproses helpdesk dan diteruskan ke admin. Helpdesk tidak boleh mengubah field sensitif, konfigurasi, pembayaran, data SHU, atau melakukan koreksi atas presensi yang sudah selesai.
10. **Koreksi presensi oleh admin:** hanya admin yang bisa mengoreksi waktu/status presensi yang salah catat; setiap koreksi **wajib** tercatat di log audit (nilai sebelum → sesudah + siapa yang mengoreksi).
11. Route penyajian foto/TTE hanya untuk `admin` dan `keuangan` yang login dan berizin. Helpdesk, scanner di luar transaksi aktif, publik, dan user tanpa izin ditolak 403. Verifikasi resi publik tidak mengubah aturan ini.

**Deliverables:**
- Layar scanner rapat aktif (Livewire): tab Scan QR + tab Ketik Manual → datang (+foto) → tanda tangan → pulang (mode 2x), lengkap dengan hasil minimal, ringkasan stasiun/kuorum, antrean offline, status sinkronisasi, dan daftar konflik sinkronisasi.
- Endpoint sinkronisasi offline yang idempoten dan anti-duplikat.
- Meja helpdesk: pencarian/verifikasi peserta, tampilan status, tampil/kirim ulang undangan dengan token yang sama, dan presensi manual terbatas yang wajib beralasan serta teraudit.
- Penyimpanan privat foto & tanda tangan + route media khusus admin/keuangan.
- Halaman daftar presensi per rapat untuk admin dan keuangan sesuai izin + koreksi admin tercatat audit.

**Test wajib:** (detail di `TESTING.md`)
- Token valid pada rapat aktif mencatat presensi datang lengkap (waktu + foto + tanda tangan); tab ketik manual scanner untuk nomor anggota peserta `verified` menghasilkan alur yang sama; tanpa tanda tangan, presensi tidak lengkap/ditolak.
- Token tidak dikenal, token anggota lain, token peserta rapat lain, dan token/nomor anggota peserta `pending`/`rejected` ditolak; presensi datang duplikat ditolak; mode 2x: pulang tanpa datang ditolak; mode 1x: tidak ada presensi pulang.
- Entri offline yang sah tersinkron tepat satu kali; retry dengan `client_event_id` sama tidak menggandakan; entri offline untuk anggota yang sudah hadir ditolak saat sinkron dan tidak menimpa/menggandakan data.
- Scanner tidak bisa memakai aksi manual helpdesk, koreksi, atau mengakses master/SHU/manajemen pembayaran/doorprize/laporan/audit.
- Helpdesk hanya dapat melakukan presensi manual bagi peserta terundang dan terverifikasi dengan alasan wajib dan catatan audit; peserta tidak terundang/belum terverifikasi diteruskan ke admin.
- Koreksi hanya bisa oleh admin dan tercatat di log audit; helpdesk menerima 403 untuk foto/TTE dan hanya admin/keuangan yang dapat membuka media.

**Kriteria selesai:**
- `php artisan test` hijau untuk seluruh test Fase 0–2.
- Demo alur penuh untuk kedua mode rapat (1x dan 2x): scanner memakai QR dan ketik manual, satu sesi diputus koneksinya lalu disinkronkan tanpa duplikat, kegagalan ditangani helpdesk sesuai batasnya, dan koreksi contoh hanya berhasil melalui admin.
- Review Chandra: disetujui.

## 5. Fase 3 — Kuorum & Transport

**Tujuan:** Selama rapat berjalan, kuorum terpantau realtime berdasarkan target jumlah orang dan hanya dari anggota jenis `biasa`; uang transport tiap peserta terhitung dari nominal dasar dikurangi potongan keterlambatan, lalu dibayar **tunai oleh scanner di titik scan**. Keuangan memantau konfigurasi transport yang diatur admin, serta mengelola kas awal per meja, sisa kas, dan rekonsiliasi.

**Cakupan fitur:**
1. `KuorumService`: menghitung target dan kehadiran realtime —
   - **Populasi** dipilih per rapat: basis `total` = seluruh populasi rapat dari jenis anggota `biasa`; basis `terverifikasi` = populasi jenis `biasa` yang berstatus `verified`.
   - **Target kuorum** = `floor(persen_kuorum × populasi) + tambahan_kuorum`. Bawaan: persen 50% dan tambahan 1, sehingga populasi 120 menghasilkan target 61.
   - **Hitungan hadir** hanya menjumlah peserta hadir yang jenis anggotanya `biasa` dan memenuhi syarat verifikasi. Anggota `luar_biasa` dikeluarkan dari populasi maupun hitungan hadir; kehadirannya boleh tercatat untuk administrasi, tetapi tidak menambah angka kuorum.
   - Status **sah** bila hitungan hadir `>= target`; selain itu **belum sah**.
2. **Layar proyektor kuorum dan papan skor publik** (Livewire, refresh otomatis): jumlah hadir kuorum, populasi, target, persentase tampilan, dan status sah/belum sah. Layar internal admin/keuangan boleh menampilkan rincian daftar sesuai izin; papan skor publik hanya menampilkan angka agregat rapat dan **tanpa data personal**. Scanner hanya melihat ringkasan stasiun/kuorum rapat aktif; helpdesk hanya melihat status per peserta.
3. `TransportService`: menghitung hak transport dari **waktu datang** (bukan waktu pulang atau waktu bayar):
   - Konfigurasi per rapat dikelola `keuangan`: `nominal_dasar`, `grace_menit` (bawaan 15 menit), `batas_hangus_menit` (bawaan 120 menit), dan daftar tier potongan berisi `menit_dari`, `menit_sampai`, serta `nominal_potongan`. Konfigurasi tier harus menutupi rentang setelah grace sampai batas hangus tanpa celah.
   - Keterlambatan = selisih waktu datang terhadap jam mulai rapat, minimum 0 menit. Bila keterlambatan `<= grace_menit`, nominal akhir = nominal dasar. Bila masuk rentang tier, nominal akhir = `max(0, nominal_dasar − nominal_potongan)`. Bila keterlambatan `> batas_hangus_menit`, nominal akhir = Rp0.
   - Contoh wajib: rapat mulai 08:00, nominal dasar Rp100.000, tier 16–30 menit memotong Rp25.000 → scan 08:10 menghasilkan Rp100.000; scan 08:20 menghasilkan Rp75.000; scan 10:30 (telat 150 menit) menghasilkan Rp0.
4. Migrasi + model `transport` (presensi/peserta unik, nominal dasar, menit telat, tier terpilih, nominal potongan, nominal akhir, status `belum_dibayar`/`sudah_dibayar`/`tidak_ada_yang_dibayar`, waktu pembayaran, user scanner pembayar, stasiun/meja, sesi kas, dan kode resi unik).
5. **Pembayaran tunai oleh scanner di titik scan:**
   - Mode presensi **1x:** setelah presensi datang lengkap, scanner membayar tunai nominal akhir pada titik scan yang sama dan mencatat pembayaran tepat satu kali.
   - Mode presensi **2x:** hak dihitung dari waktu datang, tetapi tunai dibayar scanner saat presensi pulang selesai; scanner menerbitkan **resi** pembayaran pada saat itu.
   - Pembayaran ganda dicegah oleh status dan kunci unik transport per peserta/rapat. Nominal Rp0 dicatat sebagai tidak ada pembayaran tunai, bukan sebagai pembayaran Rp0 yang dapat diulang.
   - Bila pembayaran terjadi dari entri yang awalnya offline, baris pembayaran lokal ikut antre dengan identitas idempotensi presensi dan hanya diposting satu kali setelah sinkronisasi diterima; konflik duplikat tidak memposting pembayaran kedua.
6. **Kas per meja dan rekonsiliasi oleh keuangan:**
   - Sebelum acara, `keuangan` membuat sesi kas untuk setiap meja/stasiun scanner dengan rapat, scanner penanggung jawab, dan `kas_awal`.
   - Sistem menghitung `kas_keluar` sebagai jumlah `nominal_akhir` semua baris transport berstatus `sudah_dibayar` pada sesi itu; `kas_seharusnya` = `kas_awal − kas_keluar`.
   - Saat penutupan, keuangan mencatat `sisa_kas_fisik`; sistem menghitung `selisih = sisa_kas_fisik − kas_seharusnya`.
   - Rekonsiliasi tidak dapat difinalkan bila `selisih != 0` dan catatan rekonsiliasi kosong. Selisih bukan nol hanya dapat difinalkan dengan catatan penjelasan; seluruh angka dan catatan tercatat audit.
7. **Verifikasi resi publik:** pemilik resi dapat memasukkan kode resi untuk melihat ringkasan teks pembayaran (rapat, status, nominal, waktu, dan meja/stasiun). Halaman ini tidak menampilkan atau menautkan foto/TTE; endpoint media tetap 403 tanpa login role yang berhak.
8. Rekap transport per rapat oleh keuangan: total peserta, total hak, total terbayar, jumlah belum dibayar, daftar pembayaran per meja, dan hasil rekonsiliasi. Admin dapat membuka rekap sebagai bagian dari semua laporan; helpdesk hanya melihat status transport per peserta.

**Deliverables:**
- `KuorumService` dengan formula target eksplisit + layar proyektor + papan skor publik agregat.
- `TransportService` model nominal dasar − potongan + konfigurasi transport oleh keuangan.
- Pembayaran tunai dan resi terintegrasi di alur scanner + model sesi kas/rekonsiliasi keuangan.
- Halaman verifikasi resi publik berbasis teks.

**Test wajib:** (detail di `TESTING.md`)
- Target kuorum memakai `floor(persen × populasi) + tambahan`: populasi 120 anggota `biasa` pada 50% + 1 menghasilkan target 61; basis total dan terverifikasi dibedakan dengan benar; hadir dari anggota `luar_biasa` tidak menaikkan hitungan.
- Tiga contoh transport wajib menghasilkan Rp100.000, Rp75.000, dan Rp0 persis seperti aturan di atas; pembayaran ganda dicegah.
- Scanner membayar pada titik yang benar (1x saat datang; 2x saat pulang dengan resi); keuangan tidak mengeksekusi pembayaran per peserta, tetapi mengelola kas awal, sisa kas, dan rekonsiliasi.
- Total kas keluar sama dengan jumlah nominal seluruh baris terbayar pada sesi; rekonsiliasi dengan selisih bukan nol tanpa catatan ditolak.

**Kriteria selesai:**
- `php artisan test` hijau untuk seluruh test Fase 0–3.
- Demo: presensi contoh membuat target/angka kuorum berubah sampai status "SAH"; scanner membayar contoh pembayaran tunai pada titik scan dan menerbitkan resi mode 2x; keuangan menutup meja contoh dan rekonsiliasi cocok dengan baris pembayaran.
- Review Chandra: disetujui.

## 6. Fase 4 — SHU & Doorprize

**Tujuan:** Keuangan mengelola data balas jasa SHU secara teraudit, publik dapat mengecek SHU melalui form nomor anggota yang diberi throttle dan tercatat audit, dan admin menggelar undian doorprize yang adil — hanya dari peserta yang benar-benar hadir.

**Cakupan fitur:**
1. Migrasi + model `shu`: anggota, tahun buku, `balas_jasa_saham`, `balas_jasa_pinjaman`, dan total SHU yang dihitung server-side dari kedua komponen. Hanya `keuangan` yang mengelola data balas jasa saham dan pinjaman melalui input manual, impor spreadsheet, dan koreksi; setiap koreksi tercatat audit dan data antar tahun buku terpisah.
2. **Cek SHU publik:** halaman publik menyediakan form nomor anggota eksak; bila ditemukan, hasil menampilkan komponen balas jasa saham, balas jasa pinjaman, total, dan tahun buku tanpa memerlukan login peserta. Setiap lookup — ditemukan maupun tidak — diberi throttle per alamat IP dan nomor anggota serta tercatat di log audit sebagai peristiwa lookup publik. Helpdesk dan scanner tidak memiliki konsol/lookup SHU petugas dan tidak dapat menambah, mengubah, mengimpor, atau mengoreksi data SHU; admin menerima SHU melalui laporan, sedangkan pengelolaan operasionalnya tetap oleh keuangan.
3. Migrasi + model `doorprize` (nama hadiah, jumlah unit) dan `pemenang_doorprize` (rapat, hadiah, peserta pemenang, waktu diundi).
4. **Undian doorprize oleh admin:** hanya admin yang mengelola hadiah dan menjalankan undian. Kumpulan peserta yang boleh diundi = hanya peserta rapat tersebut yang berstatus **hadir**. Satu peserta **maksimal menang sekali per rapat** (pemenang otomatis keluar dari kumpulan undian berikutnya). Undian berhenti/ditolak bila hadiah sudah habis. Helpdesk dan keuangan hanya dapat melihat daftar pemenang seperlunya; scanner tidak memiliki akses doorprize.
5. **Layar panggung undian** (Livewire) yang dioperasikan admin: tampilan besar untuk proyektor — jalankan undian, tampilkan nama pemenang, daftar pemenang yang sudah tercatat.

**Deliverables:**
- Kelola SHU oleh keuangan (input + impor + koreksi teraudit untuk balas jasa saham & pinjaman) + form cek SHU publik ber-throttle dan teraudit.
- Kelola hadiah + layar undian panggung + daftar pemenang tercatat, khusus eksekusi oleh admin.

**Test wajib:** (detail di `TESTING.md`)
- Keuangan dapat mengelola komponen balas jasa saham & pinjaman dan koreksi SHU tercatat audit; total adalah jumlah kedua komponen; SHU tahun buku berbeda tidak tercampur.
- Form publik dengan nomor anggota yang tepat menampilkan SHU; percobaan lookup beruntun mencapai throttle; setiap lookup tercatat audit; helpdesk/scanner tidak dapat membuka konsol SHU atau mengubah data.
- Undian hanya mengambil peserta hadir; pemenang tidak bisa menang dua kali dalam rapat yang sama; hadiah habis → undian tidak bisa dijalankan lagi; hanya admin yang dapat menjalankan undian.

**Kriteria selesai:**
- `php artisan test` hijau untuk seluruh test Fase 0–4.
- Demo: keuangan mengimpor/mengoreksi contoh komponen SHU secara teraudit; publik mengecek nomor contoh melalui form dan lookup beruntun menunjukkan throttle; admin menjalankan undian contoh yang hanya memunculkan peserta hadir dan tidak pernah memunculkan nama yang sama dua kali.
- Review Chandra: disetujui.

## 7. Fase 5 — Laporan & Pengerasan

**Tujuan:** Semua kebutuhan pasca-rapat tersedia (laporan + ekspor), keamanan terbukti lewat uji regresi menyeluruh, dan aplikasi cukup tangguh untuk dipakai di meja registrasi pada hari-H.

**Cakupan fitur:**
1. **Laporan per rapat dan per role:** laporan kehadiran (daftar hadir + status + waktu datang/pulang), laporan transport (penerima + nominal + status pembayaran + sesi kas/rekonsiliasi), laporan SHU (per tahun buku dan komponen), dan log audit. Admin dapat membuka semua laporan dan menjadi satu-satunya role yang dapat membuka log audit. Keuangan dapat membuka laporan transport/SHU serta melihat daftar hadir, kuorum, dan media foto/TTE yang berotorisasi. Helpdesk tidak membuka laporan menyeluruh, log audit, atau media, hanya status/riwayat peserta seperlunya dalam konteks kasus. Scanner tidak dapat membuka laporan maupun audit.
2. **Ekspor** laporan ke spreadsheet (dan cetak daftar hadir bila dibutuhkan) mengikuti batas role pada poin 1: admin untuk semua laporan; keuangan untuk laporan transport/SHU dan kebutuhan daftar hadir/kuorum yang diizinkan.
3. **Uji keamanan regresi menyeluruh:** seluruh skenario keamanan di `TESTING.md` dijalankan ulang sebagai satu suite (field sensitif termasuk regresi P0 helpdesk, empat role login, token HMAC deterministik, verifikasi pra-rapat, presensi manual helpdesk, scanner offline anti-duplikat, pembayaran tunai dan rekonsiliasi, halaman publik, rate limit login dan cek SHU, media privat khusus admin/keuangan, audit hanya admin) — semua harus hijau.
4. **Uji beban ringan stasiun scanner:** simulasi scan/ketik manual presensi berurutan dalam jumlah peserta realistis (mis. ratusan peserta dalam waktu singkat), termasuk antrean offline dan sinkronisasi ulang, untuk memastikan stasiun tetap responsif dan tidak menghasilkan duplikat; perbaiki hambatan yang ditemukan (indeks database, query berulang).
5. **Panduan operasional singkat** per role: admin menyiapkan rapat/kriteria dan daftar final; admin/helpdesk menyelesaikan verifikasi pra-rapat; keuangan membuka sesi kas per meja sebelum acara; scanner mengoperasikan stasiun online/offline, membayar tunai pada titik scan, dan menyerahkan hitungan sisa kas; helpdesk menangani QR gagal dan eskalasi peserta tidak terundang/belum terverifikasi ke admin; keuangan menutup sesi dan merekonsiliasi kas; admin menangani koreksi presensi, doorprize, laporan akhir, dan audit. Perubahan `APP_KEY` diperlakukan sebagai insiden operasional karena mengubah derivasi token, bukan sebagai fitur aplikasi.
6. Pembersihan akhir: seeder contoh tidak ikut ke lingkungan nyata, pesan error Bahasa Indonesia di semua layar, pemeriksaan izin folder penyimpanan privat.

**Deliverables:**
- Tiga laporan + ekspor sesuai role, dengan log audit khusus admin.
- Hasil uji regresi keamanan & catatan uji beban ringan.
- Panduan operasional per role (dokumen singkat).

**Test wajib:** (detail di `TESTING.md`)
- Seluruh suite Pest (Fase 0–5) hijau dalam sekali jalan.
- Laporan cocok dengan data contoh; ekspor terbuka dan isinya sesuai filter rapat serta batas role; log audit hanya dapat dibuka admin.

**Kriteria selesai:**
- `php artisan test` hijau 100% untuk keseluruhan proyek.
- Simulasi rapat mini ujung-ke-ujung bersama Chandra: admin membuat rapat → kandidat diverifikasi dan daftar final dikunci → keuangan membuka kas meja → scanner mencatat presensi online/offline (foto + tanda tangan) → kuorum sah di proyektor/papan skor → scanner membayar tunai pada titik scan dan menerbitkan resi → keuangan merekonsiliasi kas → admin menjalankan undian doorprize → laporan keluar sesuai role dan audit hanya dibuka admin.
- Review akhir Chandra: disetujui. Proyek dinyatakan siap pakai.

## 8. Aturan Main Tiap Fase (berlaku untuk semua fase di atas)

1. **Bangun** → **Pest hijau** (`php artisan test` / `pest`) → **demo/review ke Chandra** → **revisi bila ada masukan** → **lanjut fase berikutnya**.
2. Dilarang mengerjakan dua fase sekaligus; dilarang lompat fase.
3. Setiap perubahan skema database memakai migrasi Laravel; tidak ada ubah tabel manual.
4. Semua validasi input lewat FormRequest; otorisasi lewat policy/role — ditegakkan di server, bukan hanya di tampilan.
5. Setiap aksi penting (bangkitkan kandidat, transisi/koreksi verifikasi pra-rapat, kunci daftar, kirim ulang undangan dengan token yang sama, presensi manual helpdesk, sinkronisasi offline yang diterima/ditolak sebagai duplikat, koreksi presensi, ubah field sensitif, konfigurasi transport, pembayaran tunai scanner, finalisasi rekonsiliasi kas, lookup SHU publik, koreksi SHU oleh keuangan, undian doorprize oleh admin) tercatat di log audit.
6. Semua angka bisnis dibaca dari konfigurasi rapat/pengaturan — kalau angka belum ada di konfigurasi, tambahkan dulu ke konfigurasi, jangan tulis langsung di kode.
7. Bahasa tampilan dan pesan error: Indonesia, sederhana, tanpa jargon. Foto dan tanda tangan tidak pernah disimpan di folder publik.
