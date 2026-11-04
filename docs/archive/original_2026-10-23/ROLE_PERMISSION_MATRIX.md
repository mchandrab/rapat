# Matriks Peran & Izin — eRAT (Electronic Rapat Anggota Tahunan)

Versi 1.0 — 23 Oktober 2026
Status: Draf awal hasil diskusi kebutuhan — pembagian role diperbarui 23 Oktober 2026

Dokumen ini mendefinisikan siapa boleh melakukan apa di eRAT. Izin ditegakkan di server (policy Laravel + package spatie/laravel-permission) — bukan sekadar menyembunyikan tombol di tampilan.

**Perubahan mendasar dari rancangan awal:** eRAT hanya punya **4 role login** — `admin`, `helpdesk`, `scanner`, `keuangan`. **Anggota koperasi / peserta rapat BUKAN role login**: peserta tidak punya akun, tidak punya portal, dan tidak pernah login. Kredensial kehadiran peserta adalah **QR undangan** yang berisi token kehadiran **deterministik per anggota** — dihitung server-side dengan HMAC dari nomor anggota + `APP_KEY`. Token yang sama dipakai pada semua pengiriman dan cetakan undangan anggota tersebut; token **tidak pernah di-regenerate**.

## Prinsip Segregation of Duties

Empat peran di eRAT dipisahkan berdasarkan tugas nyata di lapangan:

- **Admin mengatur, yang lain menjalankan bagiannya.** Admin memegang data induk, setup rapat, undangan & QR, koreksi, doorprize, pengaturan, dan audit. Helpdesk, scanner, dan keuangan hanya mengoperasikan wilayah tugasnya masing-masing.
- **Yang memindai tidak bisa mengoreksi.** Scanner menjalankan alur scan/ketik kode pada rapat aktif dan menyerahkan uang transport di titik scan. Semua pengecualian tanpa kredensial yang valid (QR/kode gagal, peserta bermasalah) keluar dari stasiun scanner dan diselesaikan helpdesk; koreksi atas presensi yang sudah tercatat hanya boleh admin.
- **Yang menentukan nominal tidak menyerahkan uangnya, yang menyerahkan tidak menentukan nominalnya.** Admin mengatur tier transport di pengaturan rapat; sistem menghitung nominal dari waktu presensi. Scanner menyerahkan uang sesuai nominal sistem dan menerbitkan resi. Keuangan tidak mengedit tier — keuangan menyiapkan kas meja, memantau penyerahan, dan merekonsiliasi kas.
- **Data sensitif anggota hanya di tangan admin berizin.** Status anggota, saldo simpanan wajib, kolektibilitas/NPL, dan status kelancaran pinjaman menentukan siapa terundang — field ini dikelola server-side oleh admin, tidak pernah ditentukan dari input client (pelajaran dari celah aplikasi lama).

## Legenda

| Simbol | Arti |
|---|---|
| ✓ | Boleh — sesuai cakupan kolom "Catatan" bila ada |
| view-only | Boleh melihat saja; tidak ada tombol/tindakan ubah |
| terbatas | Boleh hanya dalam batas yang tertulis pada sel matriks (mis. hanya hasil scan, hanya mengambil media, atau hanya untuk pengawasan) |
| — | Tidak boleh sama sekali |

## Daftar Role

| Role | Siapa | Deskripsi |
|---|---|---|
| `admin` | Panitia RAT / petugas IT koperasi | Pengelola penuh: master anggota & field sensitif, verifikasi anggota, setup rapat & kriteria, tier transport, bangkitkan/kunci daftar undangan, koreksi presensi, doorprize, pengaturan, user & role, semua laporan, log audit. Mengawasi kas meja, penyerahan transport, dan SHU — tetapi tidak mengisi kas/rekonsiliasi kas meja. |
| `helpdesk` | Petugas meja bantuan & kelola anggota | Panel Kelola Anggota: verifikasi anggota (`pending` → `verified`/`rejected`), koreksi data anggota non-sensitif, cari peserta, lihat undangan/status hadir/status transport, kirim WA/kirim ulang & cetak undangan setelah verifikasi identitas, dan presensi manual pengecualian untuk peserta terundang yang kredensialnya gagal (alasan wajib, tercatat audit). Tidak mengubah field sensitif atau nomor anggota. Peserta tidak terundang **diteruskan ke admin**, bukan diputuskan sendiri. |
| `scanner` | Petugas stasiun pemindai | Hanya layar scanner rapat aktif: tab Scan dan tab Ketik Manual untuk presensi datang/pulang, ambil foto & tanda tangan peserta dalam alur, lihat hasil presensi minimal dan ringkasan stasiun/kuorum, lalu serahkan uang transport sesuai nominal sistem (mode 1x saat datang; mode 2x saat pulang) dan terbitkan resi. Tidak ada akses ke data master, konfigurasi, SHU, kas/rekonsiliasi, doorprize, laporan, atau audit. Kasus tanpa kode valid → peserta diarahkan ke helpdesk. |
| `keuangan` | Petugas keuangan koperasi | Kas dan SHU: panel anggota baca-saja, memantau tier transport yang diatur admin (tidak mengedit tier), mengisi kas awal per meja scanner, memantau penyerahan & resi, menginput sisa kas dan melakukan rekonsiliasi, melihat foto/tanda tangan untuk audit kas, mengelola data SHU (impor/input balas jasa saham & pinjaman, koreksi tercatat audit), dan membuka laporan termasuk laporan keuangan detail. Tidak mengubah field sensitif kriteria, kriteria rapat, presensi, doorprize, atau pengaturan/user. |

## Peserta Bukan Role Login

- Peserta **tidak punya akun dan tidak login**. Tidak ada portal anggota di eRAT. Halaman Undangan Publik, Papan Skor, Form Cek SHU, dan Verifikasi Resi adalah halaman publik terbatas — bukan portal dan bukan akun peserta.
- Satu-satunya kredensial peserta adalah **QR undangan** berisi token deterministik per anggota (HMAC dari nomor anggota + `APP_KEY` server). Token dihitung server-side saat dibutuhkan, selalu sama untuk anggota yang sama, dan **tidak pernah di-regenerate**.
- Undangan dapat dikirim lewat WA, dikirim ulang dengan token yang sama, dan dicetak oleh admin/helpdesk. Kehilangan salinan undangan tidak mengubah token.
- QR/kode hanya berfungsi untuk presensi pada rapat di mana anggota tersebut terundang. Sistem mencocokkan token ke anggota, lalu memeriksa daftar undangan rapat aktif.
- Peserta berinteraksi langsung dengan petugas di stasiun scanner (difoto dan menandatangani) dan helpdesk (verifikasi/kelola data). Cek SHU dilakukan peserta sendiri lewat **form publik**, bukan lewat role petugas.

## Matriks

| Kemampuan | Admin | Helpdesk | Scanner | Keuangan |
|---|---|---|---|---|
| Kelola Data Induk Anggota | ✓ | terbatas | — | view-only |
| Verifikasi Anggota (`pending` → `verified`/`rejected`) | ✓ | ✓ | — | view-only |
| Koreksi Data Anggota Non-sensitif | ✓ | ✓ | — | — |
| Kelola Field Sensitif Anggota | ✓ | — | — | — |
| Kelola Rapat & Kriteria Undangan | ✓ | — | — | — |
| Kelola Tier Transport (pengaturan rapat) | ✓ | — | — | view-only |
| Bangkitkan/Kunci Daftar Undangan | ✓ | — | — | — |
| Lihat Data Peserta | ✓ | ✓ | hasil scan/kode saja | view-only |
| Kirim WA / Kirim Ulang Undangan (token sama) | ✓ | ✓ | — | — |
| Cetak Undangan | ✓ | ✓ | — | — |
| Scan / Ketik Kode Presensi | ✓ | — | ✓ | — |
| Presensi Manual Pengecualian (tanpa kredensial valid) | ✓ | ✓ | — | — |
| Koreksi Presensi | ✓ | — | — | — |
| Serahkan Uang Transport di Titik Scan | view-only | — | ✓ | view-only |
| Kas Meja & Rekonsiliasi | ✓ pengawasan | — | — | ✓ |
| Lihat Foto & Tanda Tangan | ✓ | — | ambil saja | ✓ audit kas |
| Pantau Kuorum (internal) | ✓ | view-only | ringkasan | view-only |
| Kelola Data SHU | view-only | — | — | ✓ |
| Doorprize | ✓ | view-only | — | view-only |
| Laporan | ✓ semua | — | — | ✓ |
| Log Audit | ✓ | — | — | — |
| Pengaturan | ✓ | — | — | — |
| User & Role | ✓ | — | — | — |

Dua layanan berikut **bukan hak role mana pun** karena dapat diakses publik tanpa login: **Papan Skor Kuorum** (hanya agregat) dan **Form Cek SHU** (form nomor anggota, dilindungi throttle dan tercatat di audit).

Keterangan sel yang perlu perhatian:

- **Verifikasi Anggota — Admin & Helpdesk "✓":** keduanya mengubah **status verifikasi** dari `pending` menjadi `verified` atau `rejected`. Indikator **Layak/Tidak Layak** adalah hasil penilaian kriteria otomatis (informasi, bukan status): memverifikasi anggota yang indikatornya Tidak Layak **wajib disertai catatan petugas**; tanpa catatan, sistem menolak menyimpan — anggota itu tetap berstatus `verified` dan boleh masuk. Status verifikasi ini berbeda dari field sensitif `status_anggota`: helpdesk hanya mengubah status verifikasi, bukan status keanggotaan.
- **Koreksi Data Anggota Non-sensitif — Admin & Helpdesk "✓":** keduanya dapat memperbaiki data profil non-sensitif (mis. kontak, alamat, dan data layanan lain yang ditetapkan policy). **Nomor anggota terkunci untuk semua role, termasuk admin.** Field sensitif — `status_anggota`, simpanan wajib, dan kolektibilitas/NPL — hanya admin; helpdesk tidak dapat mengubahnya.
- **Lihat Data Peserta — Keuangan "view-only":** panel anggota dan daftar peserta bersifat baca-saja, sebatas kebutuhan kas, penyerahan transport, rekonsiliasi, dan audit. Keuangan tidak mengubah data anggota dari panel ini.
- **Lihat Data Peserta — Scanner "hasil scan/kode saja":** scanner tidak bisa mencari atau menelusuri data peserta. Yang tampil hanyalah data minimal anggota yang QR/kode kehadirannya **baru saja diproses** di stasiun itu (nama, nomor anggota, status undangan dan status hadir).
- **Kirim WA/Kirim Ulang & Cetak Undangan — Admin & Helpdesk "✓":** hanya **setelah verifikasi identitas** untuk tindakan yang dilakukan dari panel petugas (mis. mencocokkan KTP/kartu anggota dan data di sistem). Semua pengiriman ulang dan cetakan memakai **token yang sama**; tidak ada tombol regenerate, nonaktifkan, atau ganti QR/token. Setiap kirim/cetak tercatat di log.
- **Scan/Ketik Kode Presensi — Admin "✓":** admin boleh mengoperasikan stasiun scanner bila diperlukan (mis. menggantikan petugas), dengan alur dan batasan tampilan yang sama seperti scanner.
- **Presensi Manual Pengecualian — Helpdesk "✓" bersyarat ketat:** hanya untuk peserta **terundang** yang tidak dapat memakai QR/kode (undangan hilang, QR rusak/tidak terbaca, kode tidak dapat diketik). Ini berbeda dari tab **Ketik Manual** di scanner: tab tersebut tetap memakai kode kehadiran yang valid. Presensi manual helpdesk wajib memilih/mengisi **alasan** dan selalu tercatat di log audit (siapa, kapan, peserta siapa, alasannya). Helpdesk **tidak** bisa presensi manual untuk peserta tidak terundang — kasus itu diteruskan ke admin.
- **Koreksi Presensi — Helpdesk, Scanner & Keuangan "—":** mengubah presensi yang **sudah tercatat** (jam datang/pulang, status hadir, membatalkan kehadiran) adalah koreksi, dan itu wilayah admin saja. Presensi manual helpdesk adalah *pencatatan baru* untuk kasus kredensial gagal, bukan koreksi.
- **Serahkan Uang Transport — Scanner "✓":** pada rapat **mode 1x**, uang diserahkan pada sesi datang setelah presensi dan tanda tangan selesai; pada rapat **mode 2x**, uang diserahkan pada sesi pulang setelah presensi pulang tercatat. Nominal selalu dihitung sistem dari tier dan waktu presensi — scanner tidak mengetik atau mengubah nominal. Setelah petugas menekan tombol serah uang, sistem mencatat penyerahan dan **menerbitkan resi** berkode unik untuk peserta.
- **Kelola Tier Transport — Admin "✓", Keuangan "view-only":** admin mengatur batas tier dan nominalnya di pengaturan rapat. Keuangan hanya melihat/memantau tier untuk menghitung kebutuhan kas, memantau penyerahan, dan rekonsiliasi — keuangan **tidak mengedit tier**. Helpdesk boleh melihat status transport per peserta untuk menjawab pertanyaan, tetapi tidak melihat kewenangan mengubah tier atau menyerahkan uang.
- **Kas Meja & Rekonsiliasi — Keuangan "✓", Admin "✓ pengawasan":** sebelum acara, keuangan mengisi **kas awal per meja scanner**. Selama/sesudah acara, sistem menjumlahkan penyerahan berdasarkan resi per meja. Keuangan kemudian menginput **sisa kas fisik** dan menjalankan rekonsiliasi: sisa seharusnya = kas awal − total diserahkan. Bila ada **selisih, catatan penjelasan wajib diisi** sebelum rekonsiliasi dapat disimpan/dikunci. Admin melihat hasil dan selisih untuk pengawasan, tetapi tidak mengisi kas atau mengubah rekonsiliasi.
- **Lihat Foto & Tanda Tangan:** Admin "✓" untuk arsip dan audit kehadiran. Keuangan "✓" khusus **audit kas** — membuka media yang terhubung ke resi/penyerahan yang sedang diperiksa. Helpdesk "—": tidak ada akses melihat foto/tanda tangan. Scanner "ambil saja": dapat mengambil foto dan tanda tangan dalam alur presensi aktif, tetapi tidak dapat membuka arsip/galeri media sesudahnya. Foto dan tanda tangan disimpan di disk privat dan hanya disajikan lewat route berotorisasi — tidak pernah sebagai file publik.
- **Pantau Kuorum:** Admin "✓" penuh (papan internal + detail). Scanner sebatas **ringkasan** di layar stasiun. Keuangan dan helpdesk "view-only" sebatas angka agregat yang relevan dengan tugasnya. Di luar matriks role, **Papan Skor Kuorum publik** menampilkan agregat yang sama tanpa login dan tanpa nama/detail peserta.
- **Kelola SHU — Keuangan "✓":** impor dan input data SHU, termasuk komponen **balas jasa saham** dan **balas jasa pinjaman**, serta koreksi yang wajib beralasan dan tercatat di log audit (siapa, kapan, nilai sebelum → sesudah). Admin hanya view-only untuk pengawasan.
- **Cek SHU bukan hak role:** peserta memakai **halaman publik Form Cek SHU** dengan memasukkan nomor anggota. Akses publik ini dilindungi throttle dan setiap percobaan tercatat untuk audit. Karena itu helpdesk tidak diberi menu lookup SHU dan tidak membuka SHU peserta dari panelnya.
- **Doorprize:** Admin adalah operator undian (kelola hadiah & menjalankan undian). Helpdesk dan keuangan "view-only" sebatas daftar pemenang yang sudah terkonfirmasi, sesuai kebutuhan masing-masing. Scanner "—" sama sekali.
- **Laporan:** Admin "✓ semua" — kehadiran, kuorum, transport, kas, SHU, doorprize, dan laporan lain. Keuangan "✓" — termasuk **laporan keuangan detail** serta laporan operasional yang terhubung ke kas/SHU. Helpdesk dan scanner "—": panel kerja mereka bukan laporan, dan tidak ada menu laporan untuk kedua role ini.
- **Log Audit — khusus Admin:** satu-satunya role yang membuka log audit. Role lain tidak melihat log, tetapi **tindakan mereka tetap tercatat** di dalamnya (verifikasi/koreksi anggota dan presensi manual oleh helpdesk, kirim/cetak undangan, scan & penyerahan transport oleh scanner, kas/rekonsiliasi & koreksi SHU oleh keuangan, koreksi oleh admin). Percobaan Form Cek SHU publik juga tercatat sebagai jejak audit sistem tanpa aktor login.

## Aturan Khusus

- **Field sensitif tidak boleh berasal dari client.** Status anggota, saldo simpanan wajib, kolektibilitas/NPL, dan status kelancaran pinjaman hanya boleh ditulis server dari proses admin yang berizin. Nilai apa pun yang dikirim browser — termasuk lewat property publik komponen Livewire — **wajib diabaikan** untuk field-field ini. Ini aturan keras penutup celah aplikasi lama (kasus `$sensitif`).
- **QR adalah kredensial — perlakukan seperti kata sandi.** Isi QR adalah token HMAC yang **deterministik per anggota**: token yang sama selalu dihasilkan dari nomor anggota + `APP_KEY` server, sehingga tidak ada proses regenerate, rotasi, penonaktifan, atau penggantian token. Yang dapat dilakukan admin/helpdesk hanyalah **Kirim WA**, **Kirim Ulang** (token sama), dan **Cetak Undangan**. Karena token tidak berubah, undangan lama yang ditemukan kembali tetap memakai token yang sama; keamanan dijaga dari daftar undangan per rapat, status verifikasi, dan pemeriksaan stasiun petugas — bukan dari mengganti token.
- **Perhitungan kuorum hanya menghitung anggota jenis `biasa`.** Anggota dengan jenis selain `biasa` tidak masuk pembilang maupun populasi kuorum. Target kuorum per rapat dihitung dengan rumus: `target = floor(persen_kuorum × populasi) + tambahan_kuorum`. `persen_kuorum` dinyatakan sebagai pecahan (contoh 50% = 0,50), sedangkan `tambahan_kuorum` adalah angka tambahan yang diatur per rapat (lazimnya 1). Basis `populasi` dipilih admin saat setup rapat: **total** (seluruh anggota jenis `biasa`) atau **terverifikasi** (anggota jenis `biasa` dengan status verifikasi `verified`). Kuorum tercapai bila jumlah peserta hadir yang memenuhi syarat **≥ target**.
- **Layanan publik dibatasi dan diaudit.** Papan Skor Kuorum publik hanya boleh menampilkan agregat (hadir, populasi, target, persen, status) — tidak ada nama, nomor anggota, foto, atau detail peserta. Form Cek SHU publik memakai nomor anggota, wajib dilindungi throttle per IP/nomor dan pencatatan audit percobaan; hasil hanya menampilkan data SHU nomor yang dimasukkan.
- **Koreksi presensi selalu berjejak.** Setiap koreksi oleh admin wajib disertai alasan dan tercatat di log audit: siapa yang mengoreksi, kapan, dan nilai sebelum → sesudah. Koreksi tanpa jejak tidak dimungkinkan oleh sistem.
- **Presensi manual helpdesk selalu berjejak.** Sama seperti koreksi: alasan wajib, tercatat siapa-petugasnya, kapan, dan untuk peserta siapa. Daftar presensi manual per rapat bisa ditinjau admin dari log audit dan laporan kehadiran (ditandai khusus sebagai "manual").
- **Kriteria undangan dibekukan sebagai snapshot.** Saat undangan dibangkitkan, nilai kriteria setiap peserta (simpanan wajib, kolektibilitas, atau status pinjaman saat itu) disimpan sebagai snapshot. Perubahan data anggota sesudahnya tidak mengubah daftar undangan yang sudah terbit.
- **Undian doorprize hanya dari yang hadir.** Daftar peserta undian diambil dari presensi berstatus hadir pada rapat tersebut (termasuk presensi manual yang sah). Satu peserta maksimal memenangkan satu hadiah per rapat; pemenang tercatat dan tidak ikut undian berikutnya di rapat yang sama.
- **Scanner tidak pernah jadi jalan pintas.** Stasiun scanner tidak menyediakan pencarian nama peserta, presensi manual pengecualian, atau koreksi. Tab **Ketik Manual** hanya memasukkan kode kehadiran yang seharusnya terbaca dari QR; kode tetap divalidasi server persis seperti hasil scan. Setiap kegagalan (kode tidak dikenal, peserta tidak terundang, token tidak cocok dengan daftar rapat, sudah presensi) menampilkan arahan yang sama: **peserta diarahkan ke helpdesk**. Keputusan pengecualian tidak diambil di stasiun scanner.
- **Login dilindungi rate limiting.** Percobaan login (keempat role) dibatasi per akun + alamat IP; kegagalan beruntun memicu jeda. Pesan kesalahan ditulis dalam Bahasa Indonesia dan tidak membocorkan apakah yang salah username atau kata sandinya.

## Catatan Penegakan (Implementasi)

- Permission diimplementasikan memakai package **spatie/laravel-permission**. Role di-seed saat instalasi: `admin`, `helpdesk`, `scanner`, `keuangan`. Setiap user wajib punya tepat satu role. **Tidak ada role `anggota`/`peserta`** — peserta tidak disimpan sebagai user login; data peserta hidup di data induk anggota + daftar undangan rapat.
- Pola penamaan permission: `domain.aksi`, contoh:
  - `anggota.lihat`, `anggota.verifikasi` (admin & helpdesk), `anggota.koreksi-nonsensitif` (admin & helpdesk), `anggota.sensitif` (mengubah field sensitif anggota — admin saja). Policy menolak perubahan `nomor_anggota` untuk semua role.
  - `rapat.kelola`, `rapat.kriteria` (mengatur kriteria undangan, basis populasi & ambang kuorum per rapat — admin saja)
  - `undangan.bangkitkan`, `undangan.kunci`, `undangan.kirim` (Kirim WA/kirim ulang token sama — admin & helpdesk), `undangan.cetak` (admin & helpdesk). **Tidak ada permission regenerate/nonaktifkan token.**
  - `peserta.lihat` (admin & helpdesk; keuangan mendapat panel baca-saja lewat policy laporan/kas)
  - `presensi.scan` (scanner & admin; mencakup tab Scan dan tab Ketik Manual), `presensi.manual` (helpdesk & admin — wajib alasan + audit), `presensi.koreksi` (admin saja), `presensi.media.ambil` (scanner, hanya dalam alur aktif), `presensi.media` (melihat foto wajah & tanda tangan — admin untuk audit kehadiran; keuangan khusus audit kas yang terhubung ke resi)
  - `kuorum.pantau` (admin), `kuorum.ringkasan` (scanner, helpdesk, keuangan — angka agregat saja). Papan skor publik tidak memakai permission login; route publik hanya mengembalikan agregat.
  - `transport.tier` (admin, di pengaturan rapat), `transport.serahkan` (scanner), `transport.kas` (keuangan: kas awal, sisa kas, rekonsiliasi), `transport.lihat` (admin & keuangan untuk pengawasan/pemantauan; helpdesk sebatas status per peserta)
  - `shu.kelola` (keuangan). **Tidak ada permission cek SHU untuk role login**; Form Cek SHU adalah route publik dengan middleware throttle + pencatatan audit.
  - `doorprize.kelola`, `doorprize.undi` (admin), `doorprize.pemenang` (lihat daftar pemenang — admin, helpdesk, keuangan)
  - `laporan.lihat` (admin semua laporan; keuangan laporan yang menjadi kewenangannya termasuk laporan keuangan detail). Helpdesk & scanner tidak mendapat permission laporan.
  - `audit.lihat` (admin saja)
  - `pengaturan.kelola`, `user.kelola` (admin saja)
- Pengecekan izin dilakukan di **dua lapis**: middleware route (`permission:...`) dan **policy per model/aksi** untuk aturan yang bergantung pada konteks (status verifikasi anggota, verifikasi identitas sebelum kirim/cetak undangan, status rapat aktif untuk scanner, dan keterkaitan resi saat keuangan membuka media untuk audit kas). Menyembunyikan tombol di Blade/Livewire **bukan** pengamanan dan tidak pernah dihitung sebagai penegakan izin.
- Akun `scanner` dibatasi ganda: selain permission `presensi.scan`, route di luar layar scan rapat aktif **tidak terdaftar** untuk role ini — stasiun scanner yang tertinggal dalam keadaan login tidak bisa dipakai menelusuri modul lain.
- Komponen Livewire wajib memanggil otorisasi policy di setiap aksi tulis (`$this->authorize(...)` atau gate setara). **Dilarang keras** memutuskan boleh/tidaknya suatu aksi — atau nilai field sensitif — dari property publik komponen, karena property publik bisa diubah dari browser oleh siapa pun. Public property hanya untuk tampilan dan input non-sensitif yang tetap divalidasi ulang di server.
- Semua aksi tulis pada data sensitif dan operasional (verifikasi/koreksi anggota, ubah kriteria, bangkitkan/kunci undangan, kirim/cetak undangan, presensi manual, koreksi presensi, kelola tier oleh admin, penyerahan transport & penerbitan resi oleh scanner, kas awal/sisa/rekonsiliasi oleh keuangan, ubah data SHU, jalankan undian) dicatat ke **Log Audit**: siapa, kapan, aksi apa, nilai sebelum dan sesudah (bila ada). Percobaan Form Cek SHU publik dicatat dengan waktu, IP, dan nomor yang dicari, tanpa aktor login.
- File foto wajah dan tanda tangan disimpan di **disk privat** (bukan `public`), disajikan hanya melalui route yang memeriksa permission `presensi.media` beserta konteksnya.
