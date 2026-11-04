# USER FLOW — eRAT (Electronic Rapat Anggota Tahunan)

Dokumen ini menjelaskan alur kerja (user flow) setiap peran di aplikasi eRAT. eRAT dibangun ulang dari nol di Laravel 13 + PHP 8.3 + MySQL, memakai Blade + Livewire dengan UI Tabler penuh, berbahasa Indonesia. eRAT berdiri sendiri dan tidak terhubung ke ERP.

## Peran dan wewenang

| Peran | Wewenang |
|---|---|
| **Admin** | Pengelola sistem & koordinator panitia: kelola data anggota (termasuk field sensitif, server-side), buat & atur rapat dan kriteria penilaian, verifikasi peserta, koreksi data dan presensi (tercatat audit), kelola & jalankan undian doorprize, pantau kuorum, kelola pengaturan, user & role, ekspor laporan, dan telusuri log audit. Admin dapat melihat foto/tanda tangan untuk audit kehadiran. |
| **Helpdesk** | Meja bantuan & verifikasi: verifikasi peserta satu per satu termasuk verifikasi susulan hari-H, koreksi data **non-sensitif**, cetak/kirim ulang undangan, dan layani token/QR tak dikenal. Helpdesk **tidak bisa melihat foto/tanda tangan**, tidak mengubah field sensitif, tidak membayar transport, dan tidak mengoreksi presensi selesai. |
| **Scanner** | Operator stasiun scan & titik pembayaran tunai: scan QR datang/pulang, ambil foto wajah & tanda tangan dalam alur, serahkan uang transport di titik scan sesuai mode rapat, dan terbitkan resi setelah pembayaran. Scanner dapat memakai mode offline terbatas. Scanner tidak memverifikasi peserta, tidak mengoreksi data/presensi, dan tidak membuka arsip foto/tanda tangan. |
| **Keuangan** | Atur konfigurasi transport (nominal dasar, masa tenggang, batas hangus, tier potongan), isi kas awal per meja scanner, pantau kas keluar, input sisa kas, lakukan rekonsiliasi, dan kelola data SHU lewat input/impor. Keuangan dapat melihat foto/tanda tangan hanya untuk audit kas; keuangan tidak membayar peserta di meja terpisah. |

**Anggota koperasi/peserta bukan peran login.** Peserta tidak punya akun atau portal login di eRAT. Setiap anggota memiliki **token QR deterministik** yang dibangkitkan server-side sebagai HMAC dari nomor anggota/NBA + `APP_KEY`. Token melekat pada data anggota, tidak kedaluwarsa, tidak di-regenerate, dan selalu sama pada setiap undangan, cetakan, atau pengiriman ulang.

Setiap alur di bawah ditulis sebagai langkah bernomor: siapa melakukan apa, urutannya, dan apa yang terjadi di sistem.

---

## 1. Login & logout petugas

1. Petugas membuka halaman login aplikasi.
2. Petugas mengisi email dan kata sandi, lalu menekan tombol "Masuk".
3. Sistem memeriksa email dan kata sandi. Percobaan login dibatasi (**rate limiting**): terlalu banyak percobaan gagal dalam waktu singkat membuat percobaan berikutnya ditahan sementara.
4. Jika benar, sistem mengarahkan petugas ke halaman sesuai perannya (admin → dashboard admin; helpdesk → panel verifikasi/layanan; scanner → layar scan rapat aktif; keuangan → dashboard kas, transport, rekonsiliasi, dan SHU).
5. Sistem menampilkan menu sesuai peran: scanner hanya melihat layar scan dan status sinkronisasi perangkatnya, helpdesk melihat panel Kelola Anggota/verifikasi dan layanan undangan, keuangan melihat transport/kas/SHU dan laporannya, dan hanya admin yang melihat pengaturan, user & role, serta log audit.
6. Untuk logout, petugas menekan menu nama akun di header lalu "Keluar". Sistem menghapus sesi dan mengembalikan ke halaman login. Logout tidak menghapus antrean offline yang belum sinkron pada perangkat scanner; antrean tetap terikat pada perangkat dan harus disinkronkan oleh petugas berwenang.

---

## 2. Admin: setup rapat dan konfigurasi penilaian, kuorum, serta transport

1. Admin membuka menu **Rapat** lalu menekan **Buat Rapat**.
2. Admin mengisi data dasar rapat: **jenis rapat** (RAT atau rapat biasa), judul, tanggal dan jam mulai/selesai, lokasi.
3. Admin memilih **mode presensi** untuk rapat ini:
   - **1x** — peserta presensi datang, menerima transport pada titik scan, dan menerima resi pada kedatangan.
   - **2x** — peserta presensi datang dan presensi pulang; transport diserahkan dan resi diterbitkan pada presensi pulang.
4. Admin memilih **mode kriteria penilaian undangan**:
   - **Mode A (simpanan & kolektibilitas):** admin mengisi batas minimum simpanan wajib dan batas maksimum kolektibilitas/NPL. Indikator Layak juga mensyaratkan status anggota aktif.
   - **Mode B (kelancaran pinjaman):** admin memilih status kelancaran pinjaman yang menjadi indikator Layak.
5. Admin mengatur **kuorum**:
   - basis populasi: **total anggota biasa** atau **anggota biasa terverifikasi/terundang** untuk rapat ini;
   - persen kuorum (bawaan **50%**);
   - tambahan kuorum (bawaan **1**).
   Sistem menampilkan pratinjau target dengan rumus `floor(persen × populasi) + tambahan` dan memperingatkan bila target melebihi populasi.
6. Admin atau keuangan mengatur **uang transport**:
   - nominal dasar;
   - `grace_period_menit` (bawaan 15 menit tanpa potongan);
   - `batas_hangus_menit` (bawaan 120 menit, boleh dikosongkan);
   - daftar tier potongan berisi rentang menit terlambat dan nominal potongan.
7. Admin menekan **Simpan**. Rapat tersimpan dengan status **Draf/Persiapan**.
8. Admin mengubah status rapat menjadi **Verifikasi** agar panel Kelola Anggota untuk rapat ini dapat dipakai admin dan helpdesk pada alur 3. Setup kriteria yang sudah dipakai untuk verifikasi tidak diubah diam-diam; perubahan kriteria setelah ada keputusan verifikasi harus dicatat dan snapshot keputusan lama tidak berubah.

---

## 3. Pra-rapat H-7 s.d. H-1: verifikasi undangan satu per satu di panel Kelola Anggota

Alur ini dilakukan oleh **admin atau helpdesk**. Sistem menyiapkan daftar kerja anggota untuk rapat, tetapi tidak memutuskan undangan secara otomatis.

1. Petugas membuka **Kelola Anggota → Verifikasi Undangan** dan memilih rapat yang berstatus Verifikasi.
2. Sistem menampilkan daftar calon peserta dengan status verifikasi awal **`pending`**, jenis anggota, dan **indikator Layak/Tidak Layak** berdasarkan kriteria rapat:
   - Mode A: Layak bila simpanan wajib memenuhi minimum, kolektibilitas tidak melewati maksimum, dan status anggota aktif.
   - Mode B: Layak/Tidak Layak berdasarkan status kelancaran pinjaman terpilih.
3. Petugas memakai filter status (`pending`, `verified`, `rejected`), indikator, nama, atau nomor anggota untuk menemukan anggota berikutnya. Filter hanya mengurutkan pekerjaan; keputusan tetap dibuat satu per satu.
4. Petugas membuka **satu anggota**, memeriksa identitas dan data yang ditampilkan, indikator kriterianya, serta nilai snapshot yang akan disimpan.
5. Petugas memilih keputusan:
   - **Verifikasi — indikator Layak:** petugas menekan **Verifikasi**. Status menjadi **`verified`**.
   - **Verifikasi — indikator Tidak Layak:** petugas mengisi **catatan petugas wajib** (alasan/pertimbangan mengizinkan anggota tetap diundang), lalu menekan **Verifikasi**. Status menjadi **`verified`** dan anggota tetap berhak menerima undangan serta masuk rapat.
   - **Tolak/Rejected — data bermasalah:** petugas mengisi catatan masalah wajib, lalu menekan **Rejected**. Status menjadi **`rejected`**, undangan tidak diterbitkan, dan anggota belum boleh masuk presensi.
6. Sistem menyimpan keputusan beserta **snapshot nilai kriteria saat verifikasi**, hasil indikator, mode/ambang kriteria, catatan, **verifikator**, dan **waktu verifikasi**. Snapshot ini tidak berubah oleh koreksi data sesudahnya.
7. Bila anggota berstatus `rejected` memerlukan perbaikan data:
   - Helpdesk mengoreksi data **non-sensitif** bila masalahnya berada pada data tersebut.
   - Masalah pada nomor anggota, status anggota, simpanan wajib, kolektibilitas, atau status kelancaran diteruskan ke **admin**; hanya admin yang mengoreksi field sensitif/identitas pokok.
   - Setelah koreksi selesai, status anggota untuk rapat itu dikembalikan ke **`pending`** secara tercatat.
   - Petugas membuka kembali anggota tersebut dan melakukan **verifikasi ulang** dari langkah 4.
8. Petugas mengulangi langkah 3–7 sampai daftar `pending` selesai diproses atau masa verifikasi ditutup. Hanya peserta `verified` yang muncul sebagai daftar undangan dan dapat dikirimi/dicetak undangannya pada alur 4.
9. Anggota `luar_biasa` diverifikasi dengan mekanisme yang sama bila akan diundang, tetapi sistem menandainya sebagai **tidak dihitung kuorum**.

**Verifikasi susulan hari-H:** bila ada anggota yang belum diproses atau datanya baru selesai diperbaiki pada hari-H, admin/helpdesk melakukan langkah 3–7 pada panel yang sama sebelum peserta menuju scanner. Status `verified` adalah syarat pintu satu-satunya; indikator Layak/Tidak Layak tidak menghalangi peserta yang sudah diverifikasi.

---

## 4. Kirim WhatsApp atau cetak undangan untuk peserta verified

Alur ini dilakukan satu per satu oleh admin atau helpdesk dari daftar undangan rapat.

1. Petugas membuka **Undangan** pada rapat dan memilih satu peserta berstatus `verified`.
2. Sistem menampilkan nama, nomor anggota, kanal kontak yang tersedia, QR token anggota, dan tautan undangan publik per token.
3. Bila peserta memiliki WhatsApp yang dapat dihubungi, petugas menekan **Kirim via WhatsApp** untuk peserta tersebut. Sistem menyiapkan/mengirim undangan hanya kepada peserta itu. **Tidak ada tombol atau alur broadcast** untuk mengirim ke banyak peserta sekaligus.
4. Bila peserta tidak memiliki WhatsApp atau pengiriman WhatsApp gagal, petugas menekan **Cetak Undangan**. Undangan cetak berisi informasi rapat dan QR token yang sama.
5. Sistem mencatat setiap tindakan ke **log pengiriman**: rapat, peserta, kanal (WhatsApp/cetak), petugas, waktu, dan hasil. Pengiriman ulang atau cetak ulang dicatat sebagai entri baru, tetapi **token tidak berubah**.
6. Peserta membuka tautan undangan publik dari WhatsApp/cetakan. Halaman publik memeriksa token dan status `verified` peserta untuk rapat terkait, lalu menampilkan informasi rapat dan status undangan. Halaman ini tidak menampilkan snapshot kriteria, field sensitif, data kas, foto, atau tanda tangan.
7. Petugas mengulangi langkah 1–5 untuk peserta berikutnya secara individual.

---

## 5. Keuangan: siapkan tier transport dan kas awal per meja scanner

1. Keuangan membuka rapat dan memeriksa konfigurasi transport dari alur 2 langkah 6: nominal dasar, masa tenggang, batas hangus, dan tier potongan. Kekurangan atau ketidakcocokan konfigurasi dikoordinasikan dengan admin sebelum presensi dibuka.
2. Keuangan menentukan meja/stasiun scanner yang akan bertugas.
3. Untuk setiap meja, keuangan mengisi **kas awal**, memilih/memastikan scanner penerima, dan mencatat penyerahan kas. Scanner penerima mengonfirmasi kas awal pada layarnya.
4. Sistem membuat saldo kas per meja dengan komponen: kas awal, total transport yang sudah dibayar, perkiraan sisa kas, dan status rekonsiliasi.
5. Keuangan membiarkan dashboard kas terbuka selama acara untuk memantau kas keluar per meja pada alur 6 atau 7. Keuangan tidak menerima peserta untuk pembayaran terpisah.

---

## 6. Hari-H online: scanner memindai QR, mengambil bukti, menyerahkan transport, dan menerbitkan resi

Alur ini dijalankan **scanner** pada rapat berstatus Aktif. Peserta membawa undangan WhatsApp/cetak yang memuat QR token anggotanya.

### 6A. Presensi datang

1. Peserta datang ke meja scanner dan menunjukkan QR undangannya.
2. Scanner memindai QR. Sistem menurunkan/memvalidasi token HMAC ke data anggota, mencari peserta pada rapat aktif, dan memeriksa status verifikasinya.
3. Bila token dan status valid, sistem menampilkan hasil minimum: nama, nomor anggota, jenis anggota, status `verified`, dan status hadir saat ini. Sistem tidak menampilkan snapshot kriteria atau field sensitif.
4. Scanner menekan **Presensi Datang**. Sistem mencatat **waktu datang dari jam server**.
5. Sistem membuka kamera; scanner mengambil **foto wajah peserta**. Foto diunggah ke disk privat dan terikat pada catatan presensi.
6. Sistem menampilkan kanvas tanda tangan; peserta membubuhkan **tanda tangan digital datang** sebagai validasi kehadiran, lalu scanner menekan **Simpan**.
7. Status peserta menjadi **Hadir Lengkap**. Bila peserta adalah anggota biasa dan memenuhi syarat kuorum, hitungan kuorum bertambah; anggota luar biasa dicatat hadir tetapi tidak menambah pembilang/populasi kuorum.
8. Sistem menghitung snapshot transport dari waktu datang: menit terlambat, masa tenggang, tier potongan terpilih, batas hangus, dan nominal akhir. Perhitungan mengikuti aturan:
   - dalam masa tenggang → potongan Rp0;
   - melewati batas hangus yang terisi → nominal Rp0;
   - selain itu → nominal dasar dikurangi potongan tier, minimum Rp0.
9. Langkah berikutnya bergantung pada mode rapat:
   - **Mode 1x:** lanjut ke langkah pembayaran dan resi pada bagian 6B sekarang.
   - **Mode 2x:** belum ada pembayaran. Peserta mengikuti rapat dan kembali saat pulang pada bagian 6C.

### 6B. Mode 1x: pembayaran saat datang dan resi

1. Layar scanner menampilkan nominal transport final peserta dan saldo kas meja.
2. Scanner **menyerahkan uang tunai** sejumlah nominal final kepada peserta di titik scan.
3. Scanner menekan **Sudah Dibayar**. Sistem mencatat pembayaran (peserta, rapat, meja, scanner, waktu, snapshot nominal), mengurangi kas keluar pada saldo meja, dan mengunci pembayaran agar tidak dapat dibayar dua kali.
4. Sistem menerbitkan **resi kehadiran** dengan kode verifikasi HMAC dan QR verifikasi. Resi dapat ditampilkan/dicetak untuk peserta.
5. Scanner melayani peserta berikutnya.

### 6C. Mode 2x: presensi pulang, tanda tangan sebagai tanda terima, pembayaran, dan resi

1. Saat peserta pulang, scanner memindai QR token yang sama.
2. Sistem menemukan presensi datang yang sudah hadir lengkap dan menampilkan waktu datang serta snapshot nominal transport yang sudah dikunci dari waktu datang.
3. Scanner menekan **Presensi Pulang**. Sistem mencatat **waktu pulang dari jam server**.
4. Scanner **menyerahkan uang tunai** sesuai nominal final kepada peserta di titik scan.
5. Peserta membubuhkan **tanda tangan pulang**. Tanda tangan ini sekaligus menjadi **tanda terima uang transport**.
6. Scanner menekan **Sudah Dibayar / Selesaikan**. Sistem mencatat waktu pulang, tanda tangan pulang, pembayaran, dan kas keluar meja dalam satu penyelesaian presensi.
7. Sistem menerbitkan **resi kehadiran** berkode verifikasi HMAC + QR verifikasi. Resi mode 2x mencantumkan waktu datang dan waktu pulang.
8. Scanner melayani peserta berikutnya.

**Catatan cabang penting:**

- **Token tidak dikenal atau tidak cocok.** Scanner tidak menebak identitas dan tidak membuat presensi manual. Peserta diarahkan ke **helpdesk** pada alur 8.
- **Peserta belum `verified`, berstatus `pending`, atau `rejected`.** Scanner menolak presensi dan mengarahkan peserta ke helpdesk/admin untuk verifikasi susulan atau penyelesaian data. Indikator Layak saja tidak cukup tanpa status `verified`.
- **Peserta datang tetapi tidak ada dalam daftar calon rapat.** Helpdesk memeriksa data anggota dan rapat. Bila orang tersebut memang anggota yang harus diproses, admin/helpdesk menambahkannya ke daftar kerja rapat sebagai `pending`, lalu memverifikasi sesuai alur 3 sebelum ia kembali ke scanner.
- **Peserta belum tanda tangan.** Status tertahan di **Hadir Belum Tanda Tangan**: belum menjadi hadir lengkap untuk kuorum, transport, dan doorprize sampai tanda tangan dilengkapi di stasiun scanner.
- **Kuorum belum tercapai.** Dashboard internal dan papan skor publik menampilkan status **Belum Sah** beserta kekurangan jumlah anggota biasa. Sistem tidak mengunci presensi atau doorprize, tetapi laporan akhir mencatat kuorum tidak tercapai bila rapat ditutup dalam keadaan ini.

---

## 7. Mode offline scanner: snapshot minimum dan antrean pending sinkron

Alur ini dipakai bila koneksi perangkat scanner terputus. Hanya perangkat scanner yang telah dipersiapkan untuk rapat aktif yang dapat memakai mode ini.

1. **Sebelum acara / saat masih online:** scanner login, membuka rapat aktif, lalu menekan **Siapkan Mode Offline**. Sistem mengunduh snapshot peserta rapat berisi hanya **nomor anggota, nama, dan status verifikasi/kehadiran minimum**, beserta konfigurasi presensi/transport yang diperlukan perangkat. Snapshot **tidak berisi** field sensitif, snapshot kriteria, foto arsip, tanda tangan arsip, atau rincian kas internal.
2. Scanner memastikan nama perangkat/meja, kas awal yang sudah dikonfirmasi, jumlah peserta snapshot, dan waktu unduh tampil benar. Perangkat siap dipakai offline.
3. Saat koneksi terputus, layar menunjukkan status **Offline — antrean aktif**. Scanner memasukkan/memindai token atau memilih peserta dari snapshot minimum sesuai mekanisme perangkat. Sistem mencocokkan token/identitas terhadap snapshot lokal.
4. Scanner menjalankan alur datang/pulang yang sama seperti alur 6: mencatat waktu perangkat sebagai waktu kejadian lokal, mengambil foto dan tanda tangan, menghitung transport dari konfigurasi lokal, menyerahkan tunai pada mode/tahap yang benar, dan menyimpan kejadian dalam **antrean lokal** berstatus **`pending_sync`**.
5. Foto dan tanda tangan offline disimpan hanya sebagai bagian terenkripsi dari antrean perangkat. Setiap kejadian memiliki **kunci idempotensi unik**; menekan simpan berulang untuk kejadian yang sama tidak membuat entri antrean kedua.
6. Pembayaran fisik tetap dicatat dalam antrean lokal. **Resi resmi berkode HMAC hanya diterbitkan server setelah kejadian tersinkron**, karena kode verifikasi tidak dibuat di perangkat; antrean menampilkan status **Resi Menunggu Sinkronisasi** sampai resi tersedia. Keterlambatan resi tidak memicu pembayaran kedua.
7. Peserta yang tidak ada dalam snapshot (misalnya baru diverifikasi setelah snapshot diunduh) tidak diproses offline. Peserta diarahkan ke helpdesk atau stasiun online agar status terbarunya diperiksa.
8. Saat koneksi kembali, scanner menekan **Sinkronkan**. Sistem mengirim antrean satu per satu ke server.
9. Server memvalidasi token, rapat, status `verified`, urutan datang/pulang, waktu kejadian, pembayaran, dan kunci idempotensi:
   - kejadian baru diterima dan status antrean menjadi **Sinkron**;
   - kejadian dengan kunci yang sama atau presensi/pembayaran yang sudah ada ditandai **Duplikat — diabaikan**, bukan dibuat ulang;
   - kejadian yang bertentangan dengan data server (misalnya peserta sudah presensi online di meja lain) ditandai **Konflik** untuk diperiksa admin/keuangan.
10. Setelah server mengonfirmasi sinkronisasi, media sementara pada antrean perangkat dihapus. Scanner dan keuangan memeriksa jumlah kejadian sinkron, duplikat, dan konflik sebelum meja ditutup.

---

## 8. Helpdesk: layani token tak dikenal, data bermasalah, dan verifikasi susulan

1. Peserta datang ke helpdesk karena diarahkan scanner, undangannya hilang, token tidak dikenal, statusnya belum `verified`, atau datanya bermasalah.
2. Helpdesk meminta identitas dan mencari anggota berdasarkan **nomor anggota atau nama**. Nomor anggota hanya dipakai sebagai kunci pencarian; helpdesk tidak mengubah nomor anggota.
3. Helpdesk memeriksa untuk rapat terkait:
   - apakah anggota ditemukan;
   - status verifikasi (`pending`, `verified`, `rejected`);
   - catatan verifikasi terakhir;
   - status pengiriman/cetak undangan.
4. Berdasarkan penyebabnya:
   - **Undangan hilang / QR tidak terbaca:** helpdesk menampilkan atau mencetak ulang undangan dengan **token yang sama**, mencatat cetak/kirim ulang di log pengiriman, lalu peserta kembali ke scanner.
   - **Token tidak dikenal:** helpdesk mencocokkan identitas fisik dengan data anggota. Bila cocok dan peserta `verified`, helpdesk mencetak/menampilkan QR resmi dari data anggota tersebut (token yang sama secara deterministik), lalu peserta kembali ke scanner. Bila tidak ditemukan kecocokan, kasus diteruskan ke admin dan tidak dibuatkan presensi.
   - **Masih `pending`:** helpdesk melakukan verifikasi susulan mengikuti alur 3 langkah 4–6. Setelah `verified`, peserta dapat menerima undangan dan menuju scanner.
   - **Bermasalah/`rejected`:** helpdesk membaca catatan masalah. Data non-sensitif dikoreksi langsung oleh helpdesk; data sensitif/identitas pokok diteruskan ke admin. Setelah koreksi, status dikembalikan ke `pending`, lalu diverifikasi ulang.
5. Helpdesk **tidak membuka foto atau tanda tangan** peserta dalam proses layanan ini. Bila sengketa memerlukan pemeriksaan media, kasus diteruskan ke admin (atau keuangan bila khusus audit kas).
6. Setelah masalah selesai, helpdesk mengarahkan peserta kembali ke meja scanner. Helpdesk tidak menggantikan scanner untuk mengambil foto, tanda tangan, pembayaran, atau penerbitan resi.

---

## 9. Keuangan: pantau kas keluar dan rekonsiliasi sisa kas

1. Selama rapat berjalan, keuangan membuka **Dashboard Kas** dan memilih meja scanner.
2. Sistem menampilkan per meja: kas awal, daftar/jumlah pembayaran transport yang tercatat, total kas keluar, perkiraan sisa kas, jumlah antrean offline yang belum sinkron (bila dilaporkan perangkat), dan status rekonsiliasi.
3. Keuangan memantau total kas keluar dan meja yang kasnya hampir habis berdasarkan pembayaran yang tercatat.
4. Setelah presensi dan pembayaran selesai, scanner mengembalikan sisa uang fisik dan perangkatnya untuk pemeriksaan sinkronisasi.
5. Keuangan memastikan antrean offline meja tersebut sudah disinkronkan atau konfliknya dicatat. Rekonsiliasi final tidak dilakukan dari angka yang masih menunggu sinkronisasi tanpa catatan.
6. Keuangan menghitung/menginput **sisa kas fisik** yang diterima dari scanner.
7. Sistem menghitung:
   - sisa kas seharusnya = kas awal − total transport dibayar;
   - selisih = sisa kas fisik − sisa kas seharusnya.
8. Bila **selisih = 0**, keuangan menekan **Finalkan Rekonsiliasi**.
9. Bila **selisih ≠ 0**, sistem mewajibkan **catatan rekonsiliasi**. Keuangan mengisi penjelasan selisih, lalu memfinalkan. Selisih dan catatan masuk log audit dan laporan transport.
10. Bila ada sengketa nominal atau pembayaran, keuangan dapat membuka foto/tanda tangan terkait melalui akses **audit kas** berotorisasi. Pemeriksaan ini tidak memberi keuangan hak mengubah presensi; koreksi presensi tetap melalui admin pada alur 12.

---

## 10. Halaman publik tanpa login

### 10A. Papan skor kuorum publik

1. Panitia membuka **Papan Skor Kuorum** pada perangkat/proyektor di lokasi rapat tanpa login.
2. Halaman menampilkan judul rapat, populasi kuorum, target, jumlah hadir anggota biasa, persentase, sisa kebutuhan peserta, dan status **Sah / Belum Sah**.
3. Halaman memperbarui angka agregat secara berkala mengikuti presensi yang tersinkron.
4. Halaman tidak menampilkan nama peserta, daftar hadir, data anggota, kas, foto, atau tanda tangan.

### 10B. Undangan publik per token

1. Peserta membuka tautan undangan dari WhatsApp atau memindai/membuka tautan pada undangan cetak.
2. Sistem memvalidasi token HMAC terhadap data anggota dan memeriksa status `verified` peserta untuk rapat yang ditautkan.
3. Bila valid dan `verified`, halaman menampilkan informasi rapat, nama peserta seperlunya, status undangan, dan QR presensi.
4. Bila peserta belum `verified` atau token tidak cocok, halaman menampilkan pesan generik bahwa undangan tidak tersedia dan peserta diminta menghubungi helpdesk. Snapshot kriteria dan field sensitif tidak ditampilkan.

### 10C. Cek SHU publik ber-throttle

1. Anggota membuka halaman **Cek SHU** tanpa login.
2. Anggota mengisi **nomor anggota** dan memilih **tahun buku** bila pilihan tahun tersedia.
3. Sistem menerapkan **throttle anti-enumerasi** sebelum pencarian diproses. Percobaan berurutan yang melewati batas ditahan sementara.
4. Sistem mencatat pencarian ke log audit pencarian (waktu, nomor yang dicari, tahun, hasil, dan alamat IP sesuai kebijakan privasi).
5. Bila ditemukan, halaman menampilkan komponen SHU anggota untuk tahun tersebut: **balas jasa saham**, **balas jasa pinjaman**, dan **total SHU**.
6. Bila tidak ditemukan, halaman menampilkan pesan generik tanpa membocorkan apakah nomor anggota terdaftar atau hanya data SHU tahun tersebut yang belum tersedia.
7. Cek SHU ini bukan portal login dan bukan lookup oleh helpdesk. Data SHU hanya diubah oleh keuangan melalui input/impor pada alur internal keuangan.

### 10D. Verifikasi resi kehadiran publik

1. Penerima resi atau pihak yang perlu memeriksa resi membuka tautan dari QR verifikasi resi atau memasukkan kode verifikasi HMAC.
2. Sistem memeriksa kode terhadap data resi tersimpan.
3. Bila cocok, halaman menampilkan status **Resi Valid** dan data teks resi: nama/nomor anggota, rapat, waktu datang, waktu pulang bila mode 2x, nominal transport, meja scanner, dan status pembayaran.
4. Bila tidak cocok, halaman menampilkan **Resi Tidak Valid** tanpa membuka data peserta lain.
5. Foto wajah dan tanda tangan tidak pernah ditampilkan pada halaman ini; keduanya tetap berada di disk privat.

---

## 11. Admin: undian doorprize dari peserta hadir

Doorprize adalah fitur eRAT baru berdasarkan keputusan Chandra dan tidak berasal dari aplikasi lama.

1. Pada rapat yang sedang berjalan atau sudah selesai, **admin** membuka menu **Doorprize** pada rapat tersebut. (Scanner tidak memiliki akses menu ini.)
2. Admin memastikan daftar **hadiah** sudah terisi (nama hadiah dan jumlahnya); hadiah bisa ditambah sebelum undian dimulai.
3. Admin memilih hadiah yang akan diundi, lalu menekan **Jalankan Undian**.
4. Sistem mengacak pemenang **hanya dari peserta berstatus Hadir Lengkap yang belum pernah menang pada rapat ini**. Peserta tidak hadir dan pemenang sebelumnya tidak masuk undian.
5. Sistem menampilkan nama pemenang di layar besar (nama + nomor anggota). Admin mengumumkan pemenang — **helpdesk dapat melihat daftar pemenang untuk membantu pengumuman** — lalu menekan **Simpan Pemenang**. Pemenang tercatat beserta hadiahnya dan waktu undiannya.
6. Admin mengulangi langkah 3–5 untuk hadiah berikutnya. Bila peserta yang layak sudah habis (semua sudah menang atau tidak ada yang hadir lengkap), sistem menampilkan pesan bahwa daftar kandidat undian sudah habis.
7. Daftar pemenang final tampil di laporan akhir rapat (alur 13). **Keuangan dapat melihat daftar pemenang** bila diperlukan untuk serah terima hadiah.

---

## 12. Admin: pantau kuorum, koreksi presensi, laporan, dan log audit

1. Admin login (alur 1) dan masuk ke **dashboard admin**.
2. Selama rapat berjalan, admin memantau panel **kuorum realtime**: basis populasi, target hasil rumus, jumlah hadir anggota biasa, persentase, status **Sah / Belum Sah**, dan jam tercapainya kuorum bila sudah sah. Panel ter-update tanpa admin perlu memuat ulang halaman.
3. Bila ditemukan kesalahan pada presensi yang sudah selesai, admin membuka data presensi rapat dan memilih baris peserta yang perlu dikoreksi.
4. Admin menekan **Koreksi**, melihat nilai saat ini (waktu datang/pulang, status, foto/tanda tangan terkait, snapshot transport), mengubah nilai yang salah, dan **wajib mengisi alasan**.
5. Admin menekan **Simpan**. Sistem menyimpan perubahan dan mencatatnya di **log audit** (pelaku, waktu, nilai sebelum → sesudah, alasan).
6. Kuorum, nominal transport, status pembayaran yang terdampak, dan kelayakan doorprize dihitung/ditandai ulang mengikuti hasil koreksi. Dampak pembayaran yang sudah terlanjur diserahkan tunai diteruskan ke keuangan untuk pemeriksaan rekonsiliasi.
7. Koreksi ini **hanya bisa dilakukan admin**. Helpdesk atau scanner yang menemukan kesalahan meneruskan kasusnya ke admin — mereka tidak memiliki akses koreksi.
8. Admin membuka **Log Audit** untuk menelusuri kejadian penting: perubahan field sensitif, koreksi data non-sensitif, keputusan verifikasi dan verifikatornya, penambahan peserta ke daftar kerja, pengiriman/pencetakan undangan, koreksi presensi, pembayaran, rekonsiliasi, pencarian SHU publik, dan sinkronisasi offline.
9. **Pengurus koperasi sebagai organisasi bukan pengguna eRAT** — mereka menerima laporan akhir rapat hasil ekspor (alur 13) di luar sistem, bukan lewat akun login.

---

## 13. Penutupan rapat: sinkronisasi, rekonsiliasi, status selesai, dan laporan akhir

1. Admin mengumumkan presensi ditutup. Scanner tidak lagi menerima presensi baru untuk rapat ini.
2. Setiap scanner menyelesaikan/menyinkronkan antrean offlinenya mengikuti alur 7. Admin memeriksa daftar kejadian **konflik** dan menyelesaikannya melalui koreksi tercatat bila diperlukan.
3. Keuangan menyelesaikan rekonsiliasi setiap meja scanner mengikuti alur 9. Selisih bukan nol harus sudah memiliki catatan.
4. **Admin** mengubah status rapat menjadi **Selesai/Ditutup** setelah presensi tersinkron dan rekonsiliasi kas selesai atau pengecualiannya tercatat.
5. Sistem menyusun laporan akhir berisi: rekap verifikasi/undangan, daftar kehadiran, status kuorum akhir beserta jam tercapainya, rekap transport per meja dan hasil rekonsiliasi kas, serta daftar pemenang doorprize.
6. **Admin** menekan **Ekspor Laporan** dan mengunduh laporan akhir rapat (Excel/PDF) sebagai arsip dan bahan pertanggungjawaban rapat, yang disampaikan kepada pengurus koperasi di luar sistem.
7. Keuangan dapat mengekspor laporan transport/rekonsiliasi dan laporan SHU dari wilayah kerjanya untuk arsip keuangan.
