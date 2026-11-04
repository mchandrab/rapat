# PRD: eRAT — Electronic Rapat Anggota Tahunan

**Versi:** 1.1 — 23 Oktober 2026
**Status:** Draf revisi hasil diskusi kebutuhan dan penyesuaian dengan PRD v2.2 serta SOP Petugas pada repo lama
**Stack:** Laravel 13 + PHP 8.3 + MySQL, Blade + Livewire, Tabler (UI penuh: CSS, JS, ikon), Pest (testing), SQLite :memory:
**Bahasa antarmuka:** Indonesia. **Bahasa kode:** Inggris.
**Catatan proyek:** eRAT dibangun ulang dari nol (tanpa migrasi data lama), berdiri sendiri (tidak terhubung ke ERP), dan akan memakai repo baru.

---

## 1. Latar Belakang & Tujuan

Rapat Anggota Tahunan (RAT) adalah forum tertinggi koperasi: di sanalah laporan pengurus disahkan, keputusan penting diambil, dan kehadiran anggota menentukan sah atau tidaknya rapat (kuorum). Proses RAT dan rapat anggota lain saat ini masih bermasalah:

1. **Registrasi dan presensi masih manual.** Daftar hadir kertas membuat antrean panjang, pencatatan lambat, dan hasilnya sulit ditelusuri ulang.
2. **Verifikasi kelayakan undangan belum terstruktur.** Petugas perlu memeriksa anggota satu per satu berdasarkan kriteria rapat, mencatat siapa yang layak, tidak layak, atau datanya bermasalah, dan memastikan hanya anggota terverifikasi yang menerima undangan.
3. **Kuorum dihitung manual.** Panitia menghitung daftar hadir dengan kalkulator; sah atau tidaknya rapat rawan disengketakan karena tidak ada angka yang bisa diverifikasi bersama.
4. **Uang transport rawan sengketa.** Besaran transport bergantung pada waktu datang peserta, sementara pembayaran tunai di meja presensi harus cocok dengan kas awal, kas keluar, dan sisa kas.
5. **Audit kehadiran lemah.** Daftar hadir kertas tidak punya bukti pendukung — tidak ada foto peserta, tidak ada tanda tangan yang terikat ke catatan kehadiran digital.
6. **Aplikasi lama punya celah keamanan serius.** Field sensitif anggota (status anggota, simpanan wajib, kolektibilitas) bisa ditentukan dari sisi client, dan login tidak dibatasi percobaannya. Data undangan dan kehadiran jadi tidak bisa dipercaya.
7. **Aturan undangan berubah-ubah.** Kriteria penilaian anggota kadang berdasarkan simpanan wajib dan kolektibilitas, kadang berdasarkan lancar/tidaknya pinjaman. Sistem harus menyediakan indikator kriteria tanpa menjadikannya gerbang otomatis yang kaku: keputusan akhir berada pada verifikasi petugas.

**Tujuan:** membangun ulang eRAT dari nol sebagai sistem informasi manajemen RAT berbasis web yang mendigitalisasi, mengamankan, dan mempercepat proses verifikasi undangan, registrasi, presensi berbasis QR, perhitungan kuorum, pembayaran uang transport berbasis waktu, rekonsiliasi kas, dan audit kehadiran memakai foto wajah peserta — dilengkapi halaman publik terbatas, cek SHU publik berproteksi, dan undian doorprize untuk peserta yang hadir.

Prinsip dasar pembangunan ulang ini: **semua aturan bisnis ditentukan dan divalidasi di server**, bukan dipercaya dari browser pengguna.

---

## 2. Pengguna Sasaran

Hanya ada **4 role login** (dikelola dengan paket spatie/laravel-permission): `admin`, `helpdesk`, `scanner`, dan `keuangan`. Prinsipnya **kewenangan terkecil** (*least privilege*): setiap role hanya bisa melakukan yang menjadi tugasnya.

1. **Admin** — pengelola sistem dan koordinator panitia. Mengelola master anggota termasuk field sensitif (selalu server-side dan tercatat), setup rapat dan kriteria penilaian, verifikasi peserta, daftar undangan, koreksi data dan presensi, doorprize, pengaturan aplikasi, manajemen user dan role, semua laporan, serta log audit. Admin dapat melihat foto dan tanda tangan untuk audit kehadiran.
2. **Helpdesk** — meja bantuan dan verifikasi. Melakukan verifikasi peserta satu per satu (termasuk verifikasi susulan pada hari-H), mengoreksi data **non-sensitif**, mencetak atau mengirim ulang undangan, melayani token/QR yang tidak dikenal, dan meneruskan masalah field sensitif kepada admin. Helpdesk **tidak dapat melihat foto atau tanda tangan**, tidak mengubah field sensitif, tidak mengoperasikan pembayaran, dan tidak mengoreksi presensi yang sudah selesai.
3. **Scanner** — operator stasiun scan dan titik penyerahan uang transport. Memindai QR presensi datang/pulang, mengambil foto wajah dan tanda tangan dalam alur presensi, menyerahkan uang transport tunai pada titik scan sesuai mode rapat, menerbitkan resi kehadiran setelah pembayaran, dan bekerja dalam mode offline terbatas bila jaringan terputus. Scanner tidak menelusuri master data secara bebas, tidak melakukan verifikasi peserta, tidak mengubah data anggota, dan tidak membuka arsip foto/tanda tangan.
4. **Keuangan** — pengelola konfigurasi dan rekonsiliasi uang transport serta data SHU. Mengatur nominal dasar, masa tenggang, batas hangus, dan tier potongan transport; mengisi kas awal per meja scanner; memantau kas keluar; menginput sisa kas; melakukan rekonsiliasi; serta menginput/mengimpor data SHU. Keuangan dapat melihat foto dan tanda tangan hanya untuk keperluan audit kas. Keuangan **tidak membayar transport dari meja terpisah**, tidak mengubah presensi, field sensitif kriteria anggota, kriteria rapat, doorprize, pengaturan, atau user.

**Anggota koperasi/peserta bukan role login.** Peserta tidak memiliki akun atau portal login di eRAT. Peserta memakai undangan berisi QR token yang melekat pada data anggotanya. Beberapa halaman publik tersedia tanpa login dengan data yang dibatasi secara ketat (lihat modul 3.9). Pengurus koperasi sebagai organisasi menerima hasil rapat lewat laporan yang diekspor admin dan disampaikan di luar sistem.

---

## 3. Ruang Lingkup per Modul

### 3.1 Master Anggota dan Token QR

- CRUD anggota: nomor anggota/NBA (unik), nama, **jenis anggota** (`biasa` atau `luar_biasa`), kontak (no. HP, email bila ada), alamat, dan status anggota (aktif/nonaktif).
- **Nomor anggota dikunci** dari perubahan biasa. Helpdesk tidak dapat mengubah nomor anggota. Perubahan identitas pokok hanya dapat ditangani admin melalui prosedur koreksi tercatat.
- Field sensitif anggota: **status anggota**, **saldo simpanan wajib**, **kolektibilitas/NPL**, dan **status kelancaran pinjaman**. Field ini hanya boleh diubah oleh admin berizin, selalu diproses server-side, dan setiap perubahan tercatat di log audit lengkap dengan nilai sebelum dan sesudah.
- Helpdesk hanya dapat mengoreksi data **non-sensitif** (misalnya nama sesuai identitas, nomor HP, alamat, atau data kontak lain yang ditetapkan sebagai non-sensitif). Koreksi helpdesk tercatat dan tidak pernah mencakup status anggota, simpanan wajib, atau kolektibilitas.
- Pengisian data awal dilakukan dari nol (input manual atau impor berkas dengan format baku yang disediakan aplikasi). Tidak ada penarikan data dari aplikasi lama.
- **Token QR anggota bersifat deterministik dan permanen.** Token dibangkitkan server-side sebagai HMAC dari nomor anggota/NBA dan `APP_KEY`. Token yang sama selalu dihasilkan untuk anggota yang sama, tidak pernah kedaluwarsa, dan **tidak ada proses regenerate token**. Token melekat pada data anggota, bukan dibuat berbeda untuk setiap rapat atau peserta.
- Undangan, cetakan, dan pengiriman ulang selalu memakai token yang sama. "Kirim ulang QR" berarti mengirim kembali token yang sama, bukan mengganti token. Karena token bergantung pada `APP_KEY`, kunci aplikasi production harus dijaga stabil dan dicadangkan secara aman; mengganti `APP_KEY` akan mengubah hasil HMAC dan merupakan peristiwa migrasi keamanan, bukan operasi harian.
- Token QR adalah kredensial penunjuk identitas anggota untuk presensi dan halaman undangan publik. Token tidak berisi field sensitif dan tidak memberi akses login.

### 3.2 Rapat, Kriteria, dan Verifikasi Undangan

- CRUD rapat: **jenis rapat** (RAT atau rapat biasa), judul, tanggal dan jam, lokasi, status (draf, persiapan/verifikasi, aktif, selesai/ditutup).
- **Mode presensi dipilih saat setup rapat:** `1x` (presensi datang saja) atau `2x` (presensi datang dan presensi pulang). Konsep presensi "dua sesi" pada aplikasi lama — datang dan pulang untuk rapat-rapat biasa di luar RAT — kini menjadi pilihan dinamis per rapat.
- **Mode kriteria penilaian dipilih saat setup rapat:**
  - **Mode A:** indikator Layak bila simpanan wajib **≥** batas minimum **dan** kolektibilitas/NPL **≤** batas maksimum **dan** status anggota aktif.
  - **Mode B:** indikator Layak/Tidak Layak berdasarkan status kelancaran pinjaman sesuai pilihan setup rapat.
  Nilai ambang dan pilihan status selalu diisi per rapat — tidak ada angka kriteria yang hardcoded di kode.
- Kriteria terpilih menghasilkan **indikator Layak/Tidak Layak** untuk membantu petugas. Indikator ini **hanya informasi**, bukan keputusan otomatis dan bukan syarat pintu masuk.
- **Mekanisme undangan adalah verifikasi pra-rapat satu per satu** melalui panel **Kelola Anggota** untuk rapat terkait. Verifikasi dilakukan oleh admin atau helpdesk, terutama pada **H-7 sampai H-1**, dan dapat dilanjutkan sebagai verifikasi susulan pada hari-H.
- Setiap calon peserta mulai berstatus **`pending`**. Petugas membuka satu anggota pada satu waktu, memeriksa data dan indikator kriterianya, lalu menetapkan:
  - **`verified`** — anggota berhak menerima undangan dan boleh masuk rapat. Bila indikator **Layak**, petugas dapat langsung memverifikasi. Bila indikator **Tidak Layak**, petugas tetap boleh memverifikasi, tetapi **wajib mengisi catatan petugas** yang menjelaskan pertimbangan/keputusan.
  - **`rejected`** — data anggota bermasalah sehingga undangan tidak diterbitkan. Petugas wajib mengisi catatan masalah. Setelah data dikoreksi, status kembali menjadi **`pending`** dan anggota harus diverifikasi ulang.
- **Syarat pintu satu-satunya adalah status `verified` pada rapat tersebut.** Anggota yang indikatornya Tidak Layak tetapi sudah `verified` tetap boleh masuk; anggota yang indikatornya Layak tetapi masih `pending` atau berstatus `rejected` belum boleh masuk sebelum diverifikasi.
- Setiap keputusan verifikasi menyimpan **snapshot** nilai kriteria saat verifikasi, mode dan ambang kriteria rapat, hasil indikator, status keputusan, catatan petugas, **nama/ID verifikator**, dan **waktu verifikasi**. Perubahan data anggota sesudah verifikasi tidak mengubah snapshot keputusan tersebut.
- Hanya peserta `verified` yang masuk daftar undangan rapat. Anggota `luar_biasa` dapat diverifikasi dan diundang sesuai keputusan petugas, tetapi diperlakukan khusus dalam kuorum (lihat modul 3.4).
- Daftar undangan tidak "dibangkitkan final" hanya dari filter otomatis. Sistem boleh menyiapkan daftar kerja, filter status, dan indikator untuk mempercepat pemeriksaan, tetapi keputusan `verified`/`rejected` selalu merupakan tindakan petugas satu per satu.

### 3.3 Pengiriman dan Bentuk Undangan

- Undangan hanya diterbitkan untuk peserta berstatus `verified` pada rapat terkait.
- Pengiriman WhatsApp dilakukan **on-demand per anggota, satu per satu**, dari panel peserta/undangan. **Tidak ada fitur broadcast** pengiriman undangan ke banyak anggota sekaligus.
- Anggota yang tidak memiliki WhatsApp atau tidak dapat dihubungi lewat WhatsApp menerima **undangan cetak** berisi informasi rapat dan QR token anggotanya.
- Setiap tindakan **kirim WhatsApp** dan **cetak undangan** dicatat dalam log pengiriman: peserta, rapat, kanal, petugas yang melakukan, waktu, dan status hasil (terkirim/gagal/dicetak). Mengirim ulang undangan selalu memakai token yang sama.
- Undangan memuat tautan halaman undangan publik per token (modul 3.9), informasi rapat, dan QR untuk presensi. Undangan tidak memuat snapshot kriteria, field sensitif, data kas, foto, atau tanda tangan.

### 3.4 Registrasi & Presensi

- Presensi utama dilakukan di **stasiun scan oleh role scanner**: peserta menunjukkan QR undangannya, sistem menurunkan/memvalidasi token HMAC terhadap data anggota, lalu memeriksa apakah anggota tersebut berstatus `verified` untuk rapat aktif.
- Bila token tidak dikenal, token tidak cocok dengan anggota mana pun, atau peserta belum `verified`, scanner mengarahkan peserta ke **helpdesk**. Helpdesk mencari berdasarkan nomor anggota/nama, memverifikasi identitas, memeriksa status verifikasi, mencetak/mengirim ulang undangan yang sama bila diperlukan, atau melakukan verifikasi susulan bila memang diputuskan petugas. Peserta kembali ke scanner untuk dipindai setelah masalah selesai.
- **Presensi datang:** sistem mencatat waktu dari jam server, lalu scanner mengambil **foto wajah peserta** memakai kamera perangkat. Foto disimpan di disk privat.
- Setelah presensi datang, peserta **wajib tanda tangan digital** sebagai validasi kehadiran. Peserta baru berstatus **hadir lengkap** setelah presensi dan tanda tangannya tersimpan.
- **Mode 2x:** peserta melakukan presensi pulang dengan memindai QR yang sama. Waktu pulang dicatat server. Peserta membubuhkan **tanda tangan pulang**, yang sekaligus menjadi **tanda terima uang transport** (lihat modul 3.5).
- Status presensi per peserta mencakup: belum hadir, hadir belum tanda tangan, hadir lengkap, sudah pulang (mode 2x), serta status pembayaran dan resi yang terkait.
- **Koreksi presensi yang sudah selesai hanya oleh admin**, wajib disertai alasan, dan tercatat di log audit. Helpdesk dan scanner tidak memiliki akses koreksi.
- **Mode offline scanner:** sebelum acara, perangkat scanner yang sudah login mengunduh snapshot peserta rapat aktif berisi hanya **nomor anggota, nama, dan status verifikasi/kehadiran yang diperlukan** — tanpa field sensitif, snapshot kriteria, data kas rinci, foto arsip, atau tanda tangan arsip. Konfigurasi presensi dan transport yang diperlukan untuk perhitungan lokal ikut tersedia pada perangkat.
- Saat offline, scanner tetap dapat mencatat presensi ke antrean lokal perangkat dengan status **`pending_sync`**. Foto dan tanda tangan yang diambil selama offline disimpan sementara secara terenkripsi pada antrean perangkat dan dihapus setelah sinkronisasi terkonfirmasi. Setiap kejadian lokal memiliki **kunci idempotensi unik** dari perangkat.
- Saat koneksi kembali, antrean disinkronkan satu per satu. Server memvalidasi token, status `verified`, rapat, urutan datang/pulang, dan kunci idempotensi. Kejadian yang sudah pernah diterima ditandai duplikat dan **tidak membuat presensi, pembayaran, atau resi kedua**. Konflik yang tidak dapat diputuskan otomatis masuk daftar pemeriksaan admin/keuangan dan tercatat audit.
- Peserta yang baru diverifikasi setelah snapshot offline dibuat tidak muncul pada snapshot lama; ia harus diproses melalui stasiun online atau helpdesk hingga snapshot diperbarui.

### 3.5 Kuorum Realtime

- **Hanya anggota `jenis_anggota = biasa` yang dihitung dalam kuorum.** Anggota `luar_biasa` tidak memiliki hak suara untuk kuorum, sehingga dikecualikan dari populasi kuorum maupun jumlah hadir kuorum, walaupun mereka dapat hadir secara fisik.
- Basis populasi dipilih saat setup rapat, salah satu dari:
  - **total anggota biasa** yang tercatat; atau
  - **anggota biasa yang terverifikasi/terundang** untuk rapat tersebut.
- Pembilang kuorum adalah peserta **anggota biasa** yang berstatus `verified` dan **hadir lengkap** pada rapat tersebut.
- Rumus target kuorum:

  **target = floor(persen_kuorum × populasi) + tambahan_kuorum**

  Nilai bawaan: `persen_kuorum = 50%` dan `tambahan_kuorum = 1`, sehingga target bawaan adalah **50% + 1**. Persen dan tambahan diatur per rapat. Status **Sah** tercapai bila jumlah hadir anggota biasa ≥ target. Sistem memberi peringatan saat setup bila target yang diatur melebihi populasi.
- Dashboard kuorum internal (Livewire) menampilkan populasi, target, jumlah hadir yang dihitung, persentase terhadap populasi, kekurangan peserta, dan status **Sah / Belum Sah** secara realtime. Sistem mencatat waktu saat kuorum pertama kali tercapai.
- **Papan skor kuorum publik** tersedia tanpa login untuk ditampilkan di lokasi/proyektor. Halaman ini hanya menampilkan angka agregat rapat (judul rapat, populasi, target, jumlah hadir kuorum, persentase, dan status sah/belum) — tanpa nama peserta, data personal, data kas, foto, atau tanda tangan.
- Bila rapat ditutup sebelum kuorum tercapai, laporan akhir mencatat status kuorum apa adanya.

### 3.6 Uang Transport Berbasis Waktu dan Kas Meja Scanner

- Besaran transport dihitung dari **nominal dasar** per rapat dikurangi **potongan bertingkat** berdasarkan menit keterlambatan peserta terhadap jam mulai rapat.
- Konfigurasi per rapat:
  - `nominal_dasar` — nominal sebelum potongan.
  - `grace_period_menit` — masa tenggang tanpa potongan; **bawaan 15 menit**, dapat diubah per rapat.
  - `batas_hangus_menit` — batas keterlambatan maksimum untuk tetap menerima transport; **bawaan 120 menit**, boleh dikosongkan. Bila diisi dan menit terlambat melewati batas ini, nominal transport menjadi **Rp0**.
  - **Tier potongan** — daftar rentang menit terlambat beserta nominal potongannya. Tier diatur per rapat dan tidak hardcoded.
- Menit terlambat dihitung dari selisih waktu datang peserta terhadap jam mulai rapat, dengan nilai minimum 0. Bila waktu datang masih dalam masa tenggang, potongan Rp0. Bila melewati batas hangus yang terisi, nominal akhir Rp0. Selain itu sistem memilih tier yang rentangnya mencakup menit terlambat, lalu menghitung:

  **nominal akhir = max(0, nominal dasar − nominal potongan tier)**

- Hasil perhitungan **dikunci dari waktu datang** dan disimpan sebagai snapshot pada catatan presensi/pembayaran: nominal dasar, menit terlambat, masa tenggang yang berlaku, tier yang terpilih, nominal potongan, batas hangus, dan nominal akhir. Perubahan konfigurasi sesudahnya tidak mengubah nominal peserta yang sudah tercatat.
- **Uang transport diserahkan tunai oleh scanner di titik scan**, bukan oleh keuangan di meja terpisah:
  - **Mode 1x:** pembayaran dilakukan saat presensi datang selesai dan tanda tangan kehadiran tersimpan. Scanner menyerahkan tunai, sistem menandai pembayaran, lalu **resi kehadiran terbit**.
  - **Mode 2x:** pembayaran dilakukan saat presensi pulang. Peserta membubuhkan tanda tangan pulang sebagai tanda terima uang, scanner menyerahkan tunai, sistem menandai pembayaran, lalu **resi kehadiran terbit**.
- Setiap pembayaran terikat pada peserta, rapat, meja/stasiun scanner, petugas scanner, waktu pembayaran, dan snapshot nominal. Pembayaran ganda untuk presensi yang sama dicegah, termasuk saat sinkronisasi offline.
- **Pengelolaan kas oleh keuangan:**
  - Sebelum acara, keuangan mengisi **kas awal per meja scanner** dan mencatat penyerahan kas kepada scanner bertugas.
  - Selama acara, keuangan memantau **kas keluar** per meja berdasarkan pembayaran yang tercatat/sinkron.
  - Setelah acara, keuangan menginput **sisa kas fisik** yang dikembalikan per meja.
  - Sistem menghitung sisa kas seharusnya = kas awal − total transport dibayar. Bila **selisih ≠ 0 wajib ada catatan rekonsiliasi** dari keuangan; rekonsiliasi tidak dapat difinalkan tanpa catatan tersebut.
  - Selisih, catatan, petugas yang menginput, dan waktu rekonsiliasi tercatat dalam log audit.
- Keuangan dapat membuka foto/tanda tangan terkait pembayaran hanya untuk **audit kas** melalui route berotorisasi. Helpdesk tidak memiliki akses tersebut.

### 3.7 Resi Kehadiran

- Resi kehadiran terbit **setelah pembayaran transport selesai**:
  - Mode 1x: setelah pembayaran pada presensi datang.
  - Mode 2x: setelah pembayaran pada presensi pulang.
- Resi berisi informasi teks kehadiran dan pembayaran yang perlu diverifikasi: nama/nomor anggota, rapat, waktu datang, waktu pulang bila mode 2x, nominal transport, meja scanner, dan status pembayaran.
- Setiap resi memiliki **kode verifikasi HMAC** dan **QR verifikasi** yang mengarah ke halaman verifikasi resi publik.
- Halaman verifikasi resi publik menampilkan data teks resi yang cocok dan status valid/tidak valid tanpa login. Halaman ini **tidak menampilkan foto wajah atau tanda tangan**, yang tetap privat.

### 3.8 Cek SHU Publik

- Data SHU dikelola oleh **keuangan** melalui input atau impor. Data per anggota per tahun buku mencakup komponen **balas jasa saham** dan **balas jasa pinjaman**, beserta total SHU yang ditampilkan dari kedua komponen tersebut.
- **Tidak ada portal login anggota dan tidak ada lookup SHU oleh helpdesk.** Cek SHU dilakukan melalui **halaman publik tanpa login** dengan formulir nomor anggota (dan tahun buku bila tersedia lebih dari satu tahun).
- Setiap pencarian SHU publik dilindungi **throttle anti-enumerasi** berdasarkan alamat IP/perangkat dan pola percobaan. Hasil untuk nomor yang tidak ditemukan ditampilkan generik agar tidak menjadi alat menebak data.
- **Setiap pencarian dicatat dalam log audit pencarian**: waktu, nomor anggota yang dicari, tahun buku, hasil ditemukan/tidak, dan alamat IP yang diproses sesuai kebijakan privasi. Log ini dipakai untuk mendeteksi penyalahgunaan.
- Halaman hasil hanya menampilkan data SHU yang diminta. Koreksi data SHU hanya oleh keuangan dan tercatat audit.

### 3.9 Halaman Publik Tanpa Login

Halaman publik dibatasi hanya pada fungsi berikut:

1. **Papan skor kuorum** — agregat kuorum saja, tanpa data personal, kas, foto, atau tanda tangan.
2. **Undangan publik per token** — token HMAC anggota membuka undangan rapat yang relevan bila anggota berstatus `verified` untuk rapat tersebut; menampilkan informasi rapat dan status undangan tanpa field sensitif atau snapshot kriteria.
3. **Cek SHU** — formulir nomor anggota ber-throttle dan teraudit seperti modul 3.8.
4. **Verifikasi resi kehadiran** — data teks resi terbuka untuk dicocokkan; foto wajah dan tanda tangan tetap privat.

Tidak ada halaman publik lain yang menampilkan daftar anggota, daftar hadir personal, snapshot kriteria, data kas, foto, tanda tangan, atau log internal.

### 3.10 Undian Doorprize

- Doorprize adalah keputusan produk Chandra untuk eRAT baru; fitur ini tidak berasal dari aplikasi lama.
- Admin mengelola daftar hadiah doorprize per rapat dan **mengoperasikan undiannya**.
- Undian dijalankan admin hanya dari peserta **berstatus hadir lengkap**. Peserta yang tidak hadir tidak pernah masuk undian.
- Helpdesk dapat melihat daftar pemenang untuk membantu pengumuman; keuangan dapat melihat daftar pemenang bila diperlukan untuk serah terima hadiah; scanner tidak memiliki akses doorprize.
- **Satu peserta maksimal menang satu kali per rapat** — pemenang otomatis tersisih dari undian hadiah berikutnya pada rapat yang sama.
- Setiap pemenang tercatat (hadiah, peserta, waktu undian) dan tampil di laporan akhir.

### 3.11 Laporan & Audit

- Laporan per rapat untuk admin: daftar verifikasi dan undangan, daftar kehadiran (termasuk waktu datang/pulang dan status), status kuorum akhir, rekap transport dan rekonsiliasi kas, serta daftar pemenang doorprize.
- Laporan transport, rekonsiliasi kas, dan SHU menjadi wilayah kerja **keuangan**; admin dapat melihatnya untuk pengawasan dan ekspor pertanggungjawaban.
- Semua laporan utama bisa diekspor admin (Excel/PDF) untuk arsip dan pertanggungjawaban; laporan akhir rapat disampaikan kepada pengurus koperasi **di luar sistem**.
- **Log audit** mencatat perubahan data sensitif, koreksi data non-sensitif oleh helpdesk, keputusan verifikasi (termasuk verifikator, waktu, snapshot, dan catatan), koreksi presensi, pengiriman/pencetakan undangan, pembayaran, rekonsiliasi kas, perubahan data SHU, pencarian SHU publik, dan hasil sinkronisasi offline. Log audit **hanya dapat ditelusuri oleh admin** dan tidak dapat dihapus dari antarmuka.

### 3.12 Pengaturan & Manajemen User

- Manajemen user dan role: admin membuat akun petugas dan menetapkan role-nya — hanya ada 4 role login: `admin`, `helpdesk`, `scanner`, `keuangan` (spatie/laravel-permission). Anggota koperasi **tidak** dibuatkan akun login.
- Pengaturan aplikasi dasar (identitas koperasi dan sejenisnya).
- Angka-angka bisnis (kriteria penilaian, persen dan tambahan kuorum, nominal dasar transport, masa tenggang, batas hangus, dan tier potongan) **bukan** pengaturan global — semuanya diatur per rapat.

---

## 4. Aturan Bisnis Kunci

1. **Token QR deterministik per anggota:** token adalah HMAC dari nomor anggota/NBA + `APP_KEY`, melekat pada anggota, tidak kedaluwarsa, dan tidak pernah di-regenerate. Kirim/cetak ulang selalu memakai token yang sama.
2. **Verifikasi adalah syarat pintu satu-satunya:** hanya peserta berstatus `verified` untuk rapat terkait yang boleh menerima undangan dan masuk presensi. Indikator Layak/Tidak Layak hanya membantu petugas mengambil keputusan.
3. **Verifikasi satu per satu:** admin/helpdesk memutuskan `verified` atau `rejected` dari panel Kelola Anggota. Tidak Layak yang diverifikasi wajib memiliki catatan petugas; `rejected` berarti data bermasalah, tanpa undangan, dan setelah koreksi kembali ke `pending` untuk verifikasi ulang.
4. **Snapshot verifikasi:** nilai kriteria, hasil indikator, keputusan, catatan, verifikator, dan waktu verifikasi dibekukan pada saat keputusan dibuat.
5. **Pengiriman undangan tidak broadcast:** WhatsApp dikirim on-demand per anggota atau undangan dicetak; setiap kirim/cetak tercatat.
6. **Mode presensi dinamis per rapat:** `1x` (datang saja) atau `2x` (datang + pulang), dipilih saat setup rapat dan berlaku untuk seluruh peserta rapat tersebut.
7. **Tanda tangan digital wajib:** tanda tangan datang memvalidasi kehadiran; pada mode 2x tanda tangan pulang juga menjadi tanda terima uang transport.
8. **Kuorum hanya anggota biasa:** populasi dan hadir dihitung hanya dari `jenis_anggota = biasa`. Target = `floor(persen × populasi) + tambahan`, dengan bawaan 50% + 1. Basis populasi adalah total anggota biasa atau anggota biasa terverifikasi/terundang sesuai setup rapat.
9. **Transport adalah nominal dasar dikurangi potongan:** potongan dipilih dari tier rentang menit terlambat. Masa tenggang bawaan 15 menit tanpa potongan; batas hangus bawaan 120 menit dan boleh kosong. Hasil dihitung dan dikunci dari waktu datang.
10. **Scanner menyerahkan uang tunai:** pembayaran terjadi di titik scan — mode 1x saat datang, mode 2x saat pulang — dan resi terbit setelah pembayaran. Keuangan mengelola kas awal, pemantauan, sisa kas, dan rekonsiliasi, bukan meja pembayaran terpisah.
11. **Rekonsiliasi kas wajib cocok atau beralasan:** selisih antara sisa kas seharusnya dan sisa kas fisik yang diinput wajib Rp0 atau disertai catatan bila tidak Rp0.
12. **Halaman publik sangat terbatas:** hanya papan skor kuorum agregat, undangan per token, cek SHU ber-throttle dan teraudit, serta verifikasi resi teks. Foto dan tanda tangan tidak pernah publik.
13. **Mode offline scanner terbatas dan anti-duplikat:** snapshot hanya nomor anggota, nama, dan status yang diperlukan; presensi offline masuk antrean `pending_sync`; sinkronisasi memakai kunci idempotensi dan tidak boleh menggandakan presensi/pembayaran/resi.
14. **Doorprize hanya untuk peserta hadir lengkap**, dioperasikan admin, dan satu peserta maksimal menang satu kali per rapat.
15. **Tidak ada angka bisnis yang hardcoded:** kriteria penilaian, kuorum, dan seluruh konfigurasi transport tersimpan per rapat.

---

## 5. Aturan Keamanan (Wajib — Pelajaran dari Aplikasi Lama)

1. **Field sensitif anggota tidak boleh ditentukan dari input client.** Status anggota, simpanan wajib, kolektibilitas/NPL, dan status kelancaran pinjaman tidak boleh berasal dari properti publik Livewire atau isian browser yang dipercaya begitu saja; nilainya hanya ditetapkan/diubah server-side oleh admin berizin.
2. **Otorisasi per aksi** memakai policy dan role (spatie/laravel-permission) di server — bukan sekadar menyembunyikan tombol di tampilan.
3. **Login wajib rate limiting**: percobaan login dibatasi (throttle) untuk mencegah tebakan kata sandi.
4. **Foto wajah dan tanda tangan digital disimpan di disk privat** dan hanya disajikan lewat route yang memeriksa otorisasi — tidak ada URL publik langsung ke berkasnya. Akses arsip media hanya untuk **admin** dan **keuangan** (keuangan khusus audit kas). **Helpdesk tidak dapat melihat foto/tanda tangan.** Scanner hanya mengambil media dalam alur presensi dan tidak dapat membuka arsipnya.
5. **Semua koreksi presensi dan perubahan data sensitif tercatat di log audit** (siapa, kapan, nilai sebelum → sesudah, alasan). Keputusan verifikasi, koreksi data non-sensitif, pembayaran, dan rekonsiliasi juga tercatat.
6. **Validasi server-side** untuk semua masukan formulir, termasuk nilai ambang kriteria, konfigurasi kuorum, konfigurasi transport, data SHU, dan catatan wajib.
7. **Token QR adalah HMAC server-side** dari nomor anggota/NBA + `APP_KEY`. Token tidak memuat data sensitif dalam bentuk terbaca. Endpoint scan tetap memerlukan login role scanner (atau role lain yang diberi izin eksplisit); membuka QR saja tidak memberikan akses ke aplikasi petugas.
8. **Proteksi halaman publik:** papan skor hanya agregat; undangan token hanya menampilkan informasi rapat untuk peserta terverifikasi; verifikasi resi hanya teks; cek SHU wajib throttle anti-enumerasi dan audit setiap pencarian. Tidak ada endpoint publik yang mengembalikan daftar anggota atau data sensitif.
9. **Keamanan mode offline:** snapshot perangkat hanya berisi data minimum (nomor anggota, nama, status). Antrean presensi lokal terenkripsi, terikat pada perangkat dan pengguna scanner yang mengunduhnya, memakai kunci idempotensi, dan media sementara dihapus setelah sinkronisasi terkonfirmasi.
10. **Snapshot nilai kriteria pada verifikasi tidak boleh diubah dari client**; snapshot ditulis server-side pada saat keputusan verifikasi disimpan.

---

## 6. Di Luar Ruang Lingkup

Yang berikut ini **tidak** dikerjakan dalam PRD ini:

1. **Migrasi data dari aplikasi lama** — sistem mulai dari nol; data lama tidak dipindahkan.
2. **Integrasi dengan ERP** — eRAT berdiri sendiri dan tidak bertukar data dengan aplikasi ERP koperasi.
3. **Modul simpan-pinjam** — eRAT bukan aplikasi simpan-pinjam. Saldo simpanan wajib, kolektibilitas, dan status kelancaran pinjaman hanya dipakai sebagai indikator penilaian undangan dan ditampilkan seperlunya, bukan dihitung atau dikelola transaksinya di sini.
4. **Perhitungan SHU** — SHU dihitung di luar sistem; eRAT hanya menyimpan data balas jasa saham dan balas jasa pinjaman untuk ditampilkan lewat cek SHU publik.
5. **Pengenalan wajah otomatis (face recognition)** — foto wajah hanya menjadi bukti untuk audit manual oleh admin dan audit kas oleh keuangan, bukan verifikasi biometrik otomatis.
6. **Aplikasi mobile native** — hanya aplikasi web responsif (bisa dibuka dari HP/tablet lewat browser).
7. **Pembayaran transport non-tunai/otomatis** — transport diserahkan tunai oleh scanner di titik scan; tidak ada transfer bank dari sistem dan tidak ada meja pembayaran keuangan terpisah.
8. **Portal/akun anggota** — anggota koperasi tidak punya akun login atau portal di eRAT. Informasi publik dibatasi pada undangan per token, papan skor kuorum agregat, cek SHU berproteksi, dan verifikasi resi teks.
9. **Broadcast undangan WhatsApp** — pengiriman undangan hanya on-demand satu per satu dari petugas; tidak ada kirim massal.
10. **Regenerate token QR** — token anggota deterministik dan permanen; tidak ada fitur mengganti token anggota.

---

## 7. Rencana Fase Ringkas

| Fase | Isi | Hasil akhir fase |
|---|---|---|
| **Fase 0 — Fondasi** | Scaffold Laravel 13 + Tabler penuh, autentikasi + 4 role (spatie: admin, helpdesk, scanner, keuangan), master anggota termasuk jenis anggota dan field sensitif server-side, token HMAC deterministik | Petugas bisa login sesuai role-nya dan admin mengelola anggota dengan aman |
| **Fase 1 — Rapat, Verifikasi & Undangan** | Setup rapat + kriteria indikator + konfigurasi kuorum, panel verifikasi satu per satu dengan snapshot dan catatan, daftar undangan dari peserta verified, kirim WhatsApp per anggota/cetak dengan log pengiriman, halaman undangan publik per token | Peserta terverifikasi menerima undangan yang benar dan dapat membuka undangan publiknya |
| **Fase 2 — Presensi, Transport & Kuorum** | Presensi scanner online/offline, foto + tanda tangan, mode 1x/2x, perhitungan transport dan pembayaran tunai oleh scanner, resi berkode HMAC + verifikasi publik, kas meja dan rekonsiliasi keuangan, kuorum realtime + papan skor publik | Satu rapat dapat dijalankan ujung ke ujung, termasuk pembayaran dan rekonsiliasi kas |
| **Fase 3 — SHU & Doorprize** | Input/impor SHU oleh keuangan (balas jasa saham & pinjaman), cek SHU publik ber-throttle dan teraudit, undian doorprize oleh admin dari peserta hadir | SHU dapat dicek publik secara terbatas; doorprize dapat diundi dari peserta hadir |
| **Fase 4 — Audit & Pengerasan** | Log audit menyeluruh, penelusuran audit untuk admin, uji keamanan (rate limiting, halaman publik, akses berkas privat, otorisasi per aksi dan per role, sinkronisasi offline anti-duplikat), acceptance test | Sistem siap dipakai untuk RAT sungguhan |

---

## 8. Risiko & Asumsi

**Risiko:**

1. **Antrean presensi di hari-H.** Foto wajah + tanda tangan menambah waktu proses per peserta. Mitigasi: lebih dari satu meja/perangkat presensi berjalan paralel, dan alur presensi diuji dengan data contoh sebelum hari-H.
2. **Kamera perangkat gagal atau tidak tersedia.** Presensi bergantung pada kamera tablet/laptop panitia. Mitigasi: uji perangkat sebelum rapat dan siapkan perangkat cadangan; masalah data/undangan ditangani helpdesk, sedangkan koreksi presensi yang sudah selesai hanya oleh admin dan tercatat.
3. **Jaringan di lokasi rapat tidak stabil.** Mitigasi: mode offline scanner dengan snapshot minimum dan antrean `pending_sync`, lalu sinkronisasi anti-duplikat saat online kembali. Peserta yang berubah status setelah snapshot diambil dapat memerlukan stasiun online/helpdesk.
4. **Selisih kas meja.** Pembayaran tunai oleh banyak scanner rawan selisih. Mitigasi: kas awal per meja tercatat, kas keluar terpantau dari pembayaran, sisa kas fisik diinput keuangan, dan selisih bukan nol wajib disertai catatan rekonsiliasi.
5. **Token QR dibagikan ke orang lain.** Token adalah penunjuk identitas, bukan bukti biometrik. Mitigasi: pemeriksaan peserta oleh scanner, foto wajah, tanda tangan, dan status verifikasi per rapat.
6. **Penyalahgunaan cek SHU publik.** Nomor anggota dapat ditebak berurutan. Mitigasi: throttle anti-enumerasi, respons generik untuk data tidak ditemukan, dan audit setiap pencarian.
7. **`APP_KEY` production berubah.** Karena token adalah HMAC dari nomor anggota + `APP_KEY`, perubahan kunci mengubah semua token tercetak. Mitigasi: `APP_KEY` production dijaga stabil dan dicadangkan aman; rotasi kunci diperlakukan sebagai migrasi terencana, bukan operasi biasa.
8. **Sengketa kuorum atau transport.** Diminimalkan oleh snapshot verifikasi, catatan waktu server, rumus kuorum yang eksplisit, snapshot perhitungan transport dari waktu datang, foto + tanda tangan, dan log audit.

**Asumsi:**

1. Satu koperasi, satu aplikasi (single-company), sama seperti pola aplikasi koperasi yang sudah berjalan.
2. Data anggota diisi dari nol oleh admin sebelum rapat pertama diselenggarakan (manual atau impor berkas format baku).
3. Anggota koperasi **tidak memiliki akun login** eRAT; kredensial kehadiran mereka adalah token QR deterministik pada undangan. Akun login hanya untuk petugas dengan 4 role (admin, helpdesk, scanner, keuangan), dibuatkan oleh admin; tidak ada pendaftaran mandiri.
4. Panitia menyediakan perangkat berkamera (tablet/laptop) untuk stasiun scan dan meja helpdesk, serta kas tunai per meja scanner yang diserahkan melalui pencatatan keuangan.
5. Besaran kriteria penilaian, persen dan tambahan kuorum, serta konfigurasi transport adalah kebijakan koperasi yang diputuskan per rapat — sistem hanya menyediakan tempat mengaturnya.

---

## 9. Kriteria Keberhasilan

1. Token QR untuk satu nomor anggota selalu menghasilkan nilai yang sama pada pemanggilan berulang dan antarrapat; tidak ada fungsi regenerate token; kirim/cetak ulang memakai token yang sama.
2. Panel verifikasi menampilkan indikator Mode A (simpanan wajib, kolektibilitas, status aktif) dan Mode B (kelancaran pinjaman) dengan benar terhadap data uji, tetapi status pintu tetap hanya ditentukan oleh keputusan `verified`.
3. Peserta Tidak Layak dapat diverifikasi hanya bila catatan petugas terisi; peserta `rejected` tidak menerima undangan; setelah datanya dikoreksi statusnya kembali `pending` dan dapat diverifikasi ulang. Snapshot serta verifikator dan waktu verifikasi tersimpan.
4. Hanya peserta `verified` yang dapat dibuatkan undangan, membuka undangan publik per token untuk rapat tersebut, dan lolos pemeriksaan presensi.
5. Pengiriman WhatsApp hanya dapat dilakukan per anggota (tidak ada aksi broadcast), undangan non-WhatsApp dapat dicetak, dan setiap kirim/cetak muncul di log pengiriman.
6. Presensi mencatat waktu dari jam server; foto wajah tersimpan di disk privat dan **tidak bisa diakses tanpa otorisasi**. Helpdesk tidak dapat membuka foto/tanda tangan; scanner tidak dapat membuka arsip media; admin dan keuangan dapat membukanya sesuai keperluan audit.
7. Peserta **belum berstatus hadir lengkap** sebelum tanda tangan digitalnya tersimpan.
8. Kuorum hanya menghitung anggota biasa: anggota luar biasa tidak masuk populasi maupun pembilang. Untuk populasi 100, target bawaan adalah 51; status Sah berubah tepat saat jumlah hadir anggota biasa mencapai target hasil rumus `floor(persen × populasi) + tambahan`.
9. Transport dihitung benar pada kasus batas masa tenggang, tier potongan, dan batas hangus: datang dalam 15 menit bawaan tanpa potongan; tier yang mencakup menit terlambat mengurangi nominal dasar; lewat batas hangus yang terisi menghasilkan Rp0; batas hangus kosong tidak menghanguskan transport. Snapshot nominal terkunci dari waktu datang.
10. Pada mode 1x, pembayaran oleh scanner dan penerbitan resi terjadi setelah presensi datang; pada mode 2x, pembayaran dan resi terjadi setelah presensi pulang dan tanda tangan pulang sebagai tanda terima uang. Tidak ada pembayaran ganda.
11. Rekonsiliasi kas per meja menghitung kas awal − kas keluar sebagai sisa seharusnya; input sisa fisik yang menghasilkan selisih bukan nol tidak dapat difinalkan tanpa catatan keuangan.
12. Resi dapat diverifikasi pada halaman publik menggunakan kode/QR HMAC dan menampilkan data teks yang cocok tanpa foto/tanda tangan.
13. Cek SHU publik menampilkan balas jasa saham, balas jasa pinjaman, dan total untuk nomor serta tahun yang benar; percobaan berurutan terkena throttle; setiap pencarian tercatat untuk audit.
14. Mode offline hanya memuat nomor anggota, nama, dan status minimum; sinkronisasi antrean yang sama dua kali tidak menggandakan presensi, pembayaran, atau resi.
15. Undian doorprize **tidak pernah** memilih peserta yang tidak hadir lengkap, dan **tidak pernah** memilih pemenang yang sama dua kali dalam satu rapat.
16. Pembatasan role terbukti dengan uji akses: scanner ditolak dari master data bebas, verifikasi, SHU, doorprize, laporan internal, dan audit; helpdesk tidak bisa mengubah field sensitif atau membuka media; keuangan tidak bisa mengubah presensi, kriteria rapat, field sensitif anggota, atau doorprize.
17. Percobaan login berulang terkena rate limiting.
18. Setiap keputusan verifikasi, koreksi data/presensi, pengiriman undangan, pembayaran, rekonsiliasi, dan perubahan field sensitif muncul di log audit dengan pelaku dan waktu.
19. Seluruh fitur tercakup automated test (Pest); **0 test gagal** sebelum sistem dinyatakan siap dipakai.
