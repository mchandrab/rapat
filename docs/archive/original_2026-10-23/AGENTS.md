# AGENTS.md — Instruksi Permanen untuk AI Agent (eRAT)

> Versi 1.0 — 23 Oktober 2026
> Status: Draf awal hasil diskusi kebutuhan

Dokumen ini adalah aturan main yang wajib diikuti oleh AI agent yang mengerjakan proyek eRAT (Electronic Rapat Anggota Tahunan). Berlaku untuk setiap sesi, setiap modul, setiap perubahan kode. Jangan menyimpang tanpa instruksi eksplisit dari Chandra.

eRAT adalah sistem informasi manajemen Rapat Anggota Tahunan koperasi berbasis web: registrasi, presensi, validasi aturan bisnis, perhitungan kuorum, pelacakan uang transport berbasis waktu, audit kehadiran memakai foto wajah, plus cek SHU dan undian doorprize. Proyek ini **dibangun ulang dari nol** (tanpa migrasi data lama), **berdiri sendiri** (tidak terhubung ke ERP), dan akan memakai **repo baru** khusus eRAT.

## 1. Bahasa & Komunikasi

- Selalu berkomunikasi dalam **Bahasa Indonesia santai** (mis. "Siap", "Udah kelar", "Ini yang belum cocok").
- Jelaskan hal teknis dengan **bahasa sederhana, tanpa jargon**. Kalau harus pakai istilah teknis, terjemahkan ke bahasa sehari-hari dulu.
- Laporan progress singkat dan konkret: apa yang selesai, apa yang belum, apa yang butuh keputusan Chandra.

## 2. Aturan Sebelum Mulai Kerja

- **Keputusan hasil sesi tanya-jawab kebutuhan dengan Chandra pada 23 Oktober 2026 bersifat mengikat.** Keputusan itu yang menjadi dasar PRD dan seluruh dokumen proyek ini. Jangan mengubahnya diam-diam; kalau ragu, tanya dulu.
- **Bila ada asumsi yang bisa mengubah hasil, sebutkan dulu sebelum eksekusi.** Satu kalimat cukup. Tunggu jawaban Chandra kalau itu keputusan arsitektur atau aturan bisnis.
- **Jangan gaspol tanpa konfirmasi untuk keputusan arsitektur** (pilihan paket/library, perubahan struktur database, perubahan aturan undangan/kuorum/transport, perubahan pembagian role). Klarifikasi dulu, baru kerjakan.
- Kalau perintah Chandra ambigu dan ada dua penafsiran yang masuk akal, tanya dulu. Jangan menebak.

## 3. Aturan Coding

- Wajib mengikuti dokumen proyek ini: `PRD.md` (kebutuhan), `ARCHITECTURE.md` (struktur kode), `DATABASE_SCHEMA.md` (skema database), dan `DESIGN_SYSTEM.md` (tampilan). Kalau ada konflik antar dokumen, tanya Chandra sebelum memutuskan.
- Stack yang sudah diputuskan: **Laravel 13 + PHP 8.3 + MySQL**, arsitektur **monolit Laravel**, Blade + **Livewire** untuk layar interaktif (presensi, dashboard kuorum realtime). Jangan mengusulkan ganti stack tanpa diminta.
- UI memakai **Tabler secara penuh** (CSS, JavaScript, dan ikon dari template Tabler), mengikuti `DESIGN_SYSTEM.md`. Jangan mencampur framework UI lain.
- **Semua validasi input wajib lewat FormRequest.** Jangan validasi langsung di controller atau di Blade.
- **Semua angka dan rumus bisnis WAJIB membaca pengaturan/konfigurasi per rapat.** Yang termasuk: persen kuorum, tambahan kuorum, basis populasi kuorum; nominal dasar uang transport, masa tenggang, tier potongan menurut keterlambatan, dan batas hangus; kriteria undangan (minimum simpanan wajib, maksimum kolektibilitas/NPL, status kelancaran pinjaman); serta mode presensi (1x atau 2x). Rumus boleh ditulis sekali di service, tetapi **parameternya dilarang keras hardcode** di controller, model, service, component, atau Blade. Contoh angka pada dokumen/test adalah fixture konfigurasi rapat uji, bukan nilai bawaan kode. Kalau nilai belum ada di konfigurasi rapat, tambahkan dulu lewat migration + seeder.
- Mode presensi dan mode kriteria undangan bersifat **dinamis per rapat**, dipilih admin saat setup rapat. Jangan mengunci perilaku ke satu mode.
- Role memakai paket **spatie/laravel-permission** dengan **TEPAT empat role login**: `admin`, `helpdesk`, `scanner`, `keuangan`. Tidak ada role lain. Otorisasi ditegakkan lewat policy/permission di server, bukan cuma menyembunyikan menu. Rincian matriksnya ada di `ROLE_PERMISSION_MATRIX.md`; ringkasnya:
  - **`admin`**: master anggota + field sensitif (server-side); setup rapat & kriteria undangan; verifikasi pra-rapat dan penerbitan undangan dari hasil verifikasi; koreksi presensi; doorprize; pengaturan/user/role; semua laporan; log audit. Keputusan verifikasi mencatat verifikator dan waktu.
  - **`helpdesk`**: cari peserta dan lihat status verifikasi, undangan, status hadir, serta status transport; tampilkan/kirim ulang undangan dengan token QR yang sama setelah verifikasi identitas; presensi manual HANYA untuk peserta terundang berstatus `verified` saat QR gagal (alasan wajib + tercatat audit) — peserta `pending`/`rejected` diteruskan ke proses koreksi dan verifikasi ulang; lihat riwayat seperlunya. **Dilarang** mengubah field sensitif, konfigurasi, pembayaran, dan koreksi presensi yang sudah selesai. Helpdesk **tidak punya lookup SHU dan tidak punya akses foto/tanda tangan**.
  - **`scanner`**: HANYA layar scan rapat aktif — scan QR presensi datang/pulang, lihat hasil scan minimal, ambil foto wajah & tanda tangan, ringkasan stasiun/kuorum, dan **menyerahkan uang transport tunai hasil hitung sistem di titik scan** (mode 1x saat datang; mode 2x saat pulang disertai resi). Scanner dapat bekerja dengan antrean offline yang wajib sinkron anti-duplikat, tetapi **dilarang** mengubah nominal, browse master anggota, presensi manual, koreksi, konfigurasi, SHU, doorprize, laporan, dan audit. Scan gagal → arahkan peserta ke helpdesk.
  - **`keuangan`**: pantau aturan/tier uang transport yang diatur admin di pengaturan rapat (tidak mengedit tier); catat **kas awal per meja**, sisa kas, dan rekonsiliasi penutupan (selisih wajib memiliki catatan); kelola data SHU termasuk **balas jasa saham dan balas jasa pinjaman** (input/impor/koreksi — koreksi tercatat audit); lihat daftar hadir & kuorum; laporan transport/SHU. Serah terima tunai ke peserta dilakukan scanner di titik scan, bukan dari konsol keuangan. **Dilarang** mengubah field sensitif kriteria, kriteria rapat, presensi, doorprize, dan pengaturan/user.
- **Peserta/anggota BUKAN role login.** Tidak ada akun atau password untuk peserta, dan jangan pernah membuatkannya. Satu-satunya kredensial peserta adalah **token QR deterministik per anggota**, diturunkan server-side dari nomor anggota dengan **HMAC memakai `APP_KEY`**. Token yang sama dipakai untuk undangan, pengiriman ulang, dan presensi — **QR bukan kredensial login**.
- **Endpoint scan QR tetap wajib diakses user petugas yang login** (scanner/helpdesk/admin sesuai izin). Halaman undangan publik boleh menampilkan undangan berdasarkan token, tetapi tidak boleh menjalankan presensi atau membuka konsol petugas.
- Akses baca arsip media (foto wajah & tanda tangan) **HANYA untuk `admin` dan `keuangan`**. Helpdesk tidak punya akses media sama sekali; scanner hanya boleh mengambil/mengirim media dalam alur scan aktif dan tidak boleh membuka arsip.
- **DILARANG membuat fitur regenerate token/QR.** Kirim ulang undangan harus menghasilkan QR yang persis sama. Jangan mengubah, merotasi, atau mengganti `APP_KEY` pada aplikasi yang sudah hidup karena tindakan itu mengubah seluruh token anggota.
- **Verifikasi pra-rapat adalah mekanisme penerbitan undangan.** Status verifikasi hanya `pending`, `verified`, atau `rejected`, dan setiap keputusan mencatat verifikator + waktu. Undangan diterbitkan untuk semua peserta `verified`, baik hasil Layak maupun Tidak Layak. Hasil **Tidak Layak** wajib catatan; peserta `verified` dengan hasil itu tetap dapat masuk karena syarat pintu satu-satunya adalah `verified`. Status `rejected` tidak menerima undangan dan hanya dapat kembali ke `pending` untuk verifikasi ulang setelah data dikoreksi.
- **Kuorum hanya menghitung jenis anggota `biasa`.** Target = `floor(persen × populasi) + tambahan`; persen, tambahan, dan basis populasi (`total` atau `terverifikasi`) dibaca dari konfigurasi rapat.
- **Pengiriman WhatsApp bersifat on-demand per peserta.** Jangan membuat broadcast, blast, antrean kirim massal, atau tombol kirim ke semua peserta. Pengiriman ulang untuk peserta yang sama memakai token QR yang sama.
- **Permukaan publik sangat terbatas:** papan skor hanya agregat tanpa data personal; undangan publik berdasarkan token; cek SHU publik dengan form nomor anggota, throttle anti-enumerasi, dan audit lookup; serta verifikasi resi dalam teks terbuka berdasarkan kode resi. Jangan membuka data personal lain tanpa login dan otorisasi petugas.
- Semua label, pesan error, pesan sukses, dan teks di antarmuka pakai **Bahasa Indonesia**.
- Kode backend dalam Bahasa Inggris (nama class, method, variabel), teks yang dilihat user dalam Bahasa Indonesia.
- **Tidak ada konsep tenant/multi-company.** eRAT single-purpose untuk satu koperasi. Jangan membuat kolom `tenant_id`, tabel tenants, atau middleware tenant.
- **eRAT berdiri sendiri.** Jangan menyambungkan eRAT ke ERP atau sistem lain (tidak ada sinkronisasi data karyawan/anggota antar sistem) tanpa instruksi eksplisit dari Chandra.
- Ikuti konvensi Laravel baku: Eloquent, route resource, policy untuk otorisasi. Jangan bikin pola sendiri yang aneh.
- Aplikasi lama hanya boleh dibaca sebagai referensi alur bisnis. **Dilarang copy-paste kode dari aplikasi lama** — semua ditulis ulang bersih mengikuti dokumen proyek ini.

## 4. Aturan Keamanan Khusus eRAT (Larangan Keras)

Bagian ini adalah pelajaran langsung dari celah aplikasi lama: dulu field sensitif anggota bisa diubah dari sisi client karena nilainya dipercaya dari Livewire public property. Itu tidak boleh terulang. Aturan berikut **tidak bisa ditawar**:

- **DILARANG menaruh field sensitif anggota di Livewire public property atau menerima nilainya dari client.** Field sensitif: saldo simpanan wajib, kolektibilitas/NPL, status kelancaran pinjaman, dan status anggota. Nilai field ini selalu dibaca dan ditulis **server-side** oleh admin yang berizin, tidak pernah dari input/properti yang dikirim browser.
- **Setiap aksi di Livewire component dan controller WAJIB memanggil otorisasi** (`authorize()` / policy / permission check) sebelum membaca atau mengubah data. Tidak ada aksi yang "dibiarkan terbuka dulu".
- **Login WAJIB memakai throttle/rate limiting.** Jangan pernah menonaktifkan atau melonggarkannya.
- **Foto wajah dan tanda tangan digital WAJIB disimpan di disk privat** (bukan folder publik) dan hanya disajikan lewat route yang berotorisasi. Jangan menaruh file-file ini di `public/` atau disk `public`.
- **Setiap koreksi presensi WAJIB menulis log audit** (siapa, kapan, nilai sebelum, nilai sesudah, alasan). Tidak ada koreksi yang senyap.
- Setiap perubahan field sensitif anggota juga **WAJIB tercatat di log audit** (siapa, kapan, sebelum/sesudah).
- **Jangan pernah mematikan atau melemahkan validasi demi "biar cepat jalan".** Kalau validasi terasa menghambat, laporkan ke Chandra — jangan diakali.
- Snapshot nilai kriteria pada undangan tidak boleh bisa diubah dari client; snapshot ditulis server-side saat undangan dibangkitkan.
- **Token QR WAJIB deterministik dan diturunkan server-side dari nomor anggota memakai HMAC dengan `APP_KEY`.** Jangan membuat token acak, jangan menerima token buatan client, jangan menyimpan `APP_KEY` di kode/database/log/QR, dan jangan menampilkan derivasi token di pesan error. Endpoint scan wajib memvalidasi HMAC, rapat aktif, serta status verifikasi peserta; hanya status `verified` yang boleh melewati pintu.
- **`APP_KEY` bersifat sakral setelah aplikasi hidup.** Dilarang menjalankan rotasi/ganti `APP_KEY`, memindahkan aplikasi ke `APP_KEY` lain, atau membuat fitur regenerate token. Perubahan `APP_KEY` dianggap memutus seluruh undangan dan tidak boleh dilakukan dalam alur proyek.
- Pembagian uang tidak boleh dicampur: **scanner menyerahkan tunai di titik scan** sesuai nominal hitungan sistem dan tidak boleh mengubah nominal; **keuangan mengelola aturan transport, kas awal per meja, sisa kas, dan rekonsiliasi**. Selisih rekonsiliasi tidak boleh ditutup tanpa catatan keuangan. Helpdesk dan admin tidak menyerahkan tunai dari alur scan.
- **Sinkronisasi offline scanner WAJIB idempoten dan anti-duplikat.** Setiap entri offline memiliki identitas operasi unik; retry sinkronisasi tidak boleh membuat presensi atau serah terima kedua. Jika anggota sudah tercatat hadir dari jalur lain, entri offline ditolak sebagai duplikat dan dilaporkan, bukan dipaksakan masuk.
- **Akses arsip foto wajah dan tanda tangan HANYA `admin` + `keuangan`.** Helpdesk wajib ditolak 403; scanner hanya menangkap media pada alur aktif dan tidak boleh membuka arsip.

## 5. Aturan Test

- **Setiap fitur baru wajib disertai Pest test.** Tidak ada fitur yang dianggap selesai tanpa test.
- Test berjalan di **SQLite `:memory:`** — jangan bergantung pada MySQL di test.
- **Jalankan test sampai semua hijau sebelum lapor selesai ke Chandra.** Kalau ada test merah, perbaiki dulu. Jangan lapor "sudah selesai" dengan test merah, dan laporkan hasil test apa adanya (jumlah lulus/gagal), bukan diklaim.
- Test harus mencakup kasus bahagia, kasus batas, dan **test regresi keamanan**, minimal:
  - field sensitif anggota tidak berubah lewat request/parameter dari user tak berizin;
  - daftar undangan hasil bangkitan cocok dengan kriteria (Mode A dan Mode B) pada data uji;
  - status kuorum mengikuti target `floor(persen × populasi) + tambahan`, hanya menghitung jenis anggota `biasa`, dan berubah sah tepat saat target tercapai pada basis populasi `total` maupun `terverifikasi`;
  - nominal transport cocok dengan konfigurasi rapat uji (mulai 08:00, dasar Rp100.000, tenggang 15 menit, potongan Rp25.000 pada 16–30 menit, hangus setelah 120 menit): datang 08:10 menghasilkan Rp100.000, 08:20 menghasilkan Rp75.000, dan 10:30 menghasilkan Rp0;
  - serah terima tunai scanner tercatat sekali pada mode 1x saat datang dan mode 2x saat pulang + resi; keuangan dapat merekonsiliasi kas awal per meja dengan sisa kas, dan selisih tanpa catatan tidak dapat ditutup;
  - undian doorprize tidak pernah memilih peserta tidak hadir atau pemenang sebelumnya;
  - login dibatasi setelah percobaan gagal berurutan (rate limiting aktif);
  - foto wajah/tanda tangan tidak bisa diakses tanpa otorisasi;
  - koreksi presensi selalu menghasilkan catatan log audit;
  - hanya ada 4 role login (`admin`, `helpdesk`, `scanner`, `keuangan`) dan tidak ada akun peserta;
  - token QR yang dibentuk berulang dari nomor anggota + `APP_KEY` yang sama selalu identik; pengiriman ulang undangan memakai token yang sama; tidak ada endpoint/fitur regenerate token; token tidak dikenal dan token untuk peserta `pending`/`rejected` ditolak di pintu;
  - verifikasi pra-rapat mencakup `pending`/`verified`/`rejected`, verifikator + waktu tercatat, hasil Tidak Layak wajib catatan, `rejected` tanpa undangan dan dapat diverifikasi ulang setelah koreksi, serta peserta `verified` Tidak Layak tetap dapat masuk;
  - scanner berhasil scan QR datang dan pulang pada rapat aktif; scanner tidak bisa presensi manual, koreksi presensi, mengubah nominal transport, maupun membuka master anggota/SHU/doorprize/laporan/audit (ditolak di server, bukan cuma menu disembunyikan);
  - entri presensi offline yang disinkronkan untuk anggota yang sudah hadir ditolak sebagai duplikat, dilaporkan, dan tidak membuat presensi/serah terima ganda; retry sinkronisasi yang sama tetap idempoten;
  - helpdesk presensi manual hanya untuk peserta terundang berstatus `verified` dengan alasan wajib dan tercatat audit; tanpa alasan ditolak; helpdesk tidak bisa mengubah field sensitif, konfigurasi, pembayaran, data SHU, dan arsip foto/tanda tangan (403);
  - keuangan mengelola aturan transport dan data SHU termasuk balas jasa saham & pinjaman (koreksi tercatat audit), mencatat kas awal/sisa kas/rekonsiliasi; keuangan tidak bisa mengubah kriteria rapat, presensi, doorprize, dan pengaturan/user;
  - cek SHU publik memakai form nomor anggota tanpa login, menghasilkan audit lookup, dan dibatasi throttle anti-enumerasi; papan skor publik tidak memuat data personal; undangan publik hanya cocok dengan tokennya; verifikasi resi publik menerima kode resi valid dan menolak kode lain;
  - akses arsip media hanya admin + keuangan; helpdesk ditolak 403 dan scanner tidak dapat membuka arsip di luar alur scan aktif.
- Jangan menghapus atau melemahkan test supaya hijau. Kalau test gagal karena aturan bisnis berubah, konfirmasi ke Chandra dulu.

## 6. Aturan Git

- **JANGAN push ke GitHub tanpa izin eksplisit dari Chandra.** Commit lokal boleh, push tidak.
- Repo untuk proyek ini adalah **repo BARU khusus eRAT** yang akan dibuat terpisah. Jangan memakai atau mengubah repo aplikasi lama maupun repo ERP.
- Commit message dalam **Bahasa Indonesia yang jelas**, menjelaskan apa yang berubah. Contoh: `Tambah bangkitan undangan rapat berdasarkan kriteria simpanan wajib dan kolektibilitas`.
- Satu commit untuk satu perubahan yang logis. Jangan campur fitur presensi dan perbaikan doorprize dalam satu commit.
- Sebelum commit, pastikan: test hijau, tidak ada file sampah (dump, file sementara), tidak ada kredensial yang ikut ter-commit.

## 7. Lingkungan Pengembangan

- Pengembangan berjalan di **PC Chandra: Windows + Laragon, PHP 8.3.36**. Agent lokal (di PC Chandra) yang menjalankan perintah di sana; agent di sesi ini tidak bisa menjalankan perintah di PC Chandra secara langsung.
- Setiap langkah pengerjaan lokal dibuat **copy-safe**: perintah ditulis persis untuk disalin-tempel, satu langkah dalam satu waktu, dan sebutkan tanda keberhasilan yang harus terlihat sebelum lanjut.
- Chandra menyalin perintah persis apa adanya — jangan menulis placeholder tanpa penjelasan yang jelas mana bagian yang harus diganti.
- Laporkan hasil test dan error **apa adanya**. Kalau gagal, sampaikan pesan errornya dan usulan langkah berikutnya; jangan mengklaim berhasil sebelum ada buktinya.

## 8. Aturan Destruktif & Alur Kerja

- **Konfirmasi ke Chandra dulu sebelum menghapus file, menghapus data, atau menghapus/rollback migration.**
- Pilih pendekatan non-destruktif: pakai **soft delete** untuk data anggota dan data master; nonaktifkan (flag aktif/nonaktif) daripada menghapus data referensi.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, atau perintah sejenis di database yang berisi data nyata tanpa izin eksplisit.

Ikuti urutan ini untuk setiap modul, tanpa lompat:

1. **Bangun** — tulis kode + migration + seeder + FormRequest + test mengikuti dokumen desain.
2. **Test hijau** — jalankan Pest sampai 100% hijau, termasuk test regresi keamanan yang relevan.
3. **Lapor ringkas ke Chandra** — apa yang dibangun, apa yang bisa dicoba, dan ringkasan alur kalau ada UI.
4. **Tunggu review Chandra** — jangan lanjut ke modul berikutnya sebelum Chandra memberi lampu hijau atau feedback.
5. **Iterasi** — perbaiki berdasarkan feedback, test lagi sampai hijau, lapor lagi.

**Satu modul harus selesai 100% (kode + test hijau + review Chandra) baru pindah ke modul berikutnya.**

## 9. Aturan Vibe Coding

- Chandra mengarahkan, agent menulis kode + test, Chandra mereview, lalu iterasi.
- Jangan menulis kode "sekalian" di luar yang diminta. Kerjakan sesuai arahan; kalau melihat peluang perbaikan di luar scope, catat sebagai usulan di laporan, jangan langsung dikerjakan.
- Kalau stuck lebih dari yang wajar, berhenti dan jelaskan ke Chandra apa masalahnya dan opsi solusinya — jangan muter-muter sendiri.
