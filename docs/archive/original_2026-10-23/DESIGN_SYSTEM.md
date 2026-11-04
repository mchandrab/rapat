# DESIGN SYSTEM — eRAT (Electronic Rapat Anggota Tahunan)

Versi 1.0 — 23 Oktober 2026
Status: Draf awal hasil diskusi kebutuhan — navigasi & layar per role diperbarui 23 Oktober 2026

Dokumen ini adalah acuan tunggal tampilan dan interaksi seluruh halaman eRAT. Tujuannya: siapa pun yang membangun halaman, hasilnya terlihat dan terasa seperti satu aplikasi — dan satu keluarga dengan ERP Koperasi Kredit Rukun. Dokumen ini berdiri sendiri — tidak perlu membaca dokumen lain untuk menerapkan isinya.

Stack tampilan: Blade + Livewire + **Tabler penuh** (template dashboard Bootstrap 5, lisensi MIT, 100% gratis) via npm/Vite, CSS kustom seperlunya, JavaScript vanilla. Keputusan 2026-10-23: eRAT memakai **seluruh aset Tabler seperti demo resmi** (preview.tabler.io, `theme-primary=indigo`) — CSS, JavaScript, dan ikon Tabler semua dipakai, font Inter, warna primer indigo, dan struktur dashboard Tabler asli (navbar vertikal, page-header, page-body, container-xl, card). Ada pilihan tema Terang/Gelap dan pilihan navbar Vertikal/Horizontal di bar atas. Sama seperti ERP, Tabler dipilih karena gratis penuh tanpa versi pro, komunitas besar, dan tetap memberi kontrol penuh atas kode Blade/Livewire.

**Siapa yang memakai layar eRAT:** area login hanya untuk 4 role — **admin, helpdesk, scanner, keuangan**. Di luar area login ada **halaman publik terbatas**: Papan Skor Kuorum, Undangan Publik, Form Cek SHU, dan Verifikasi Resi. Anggota koperasi/peserta rapat **tidak login dan tidak punya portal/akun**; halaman publik bukan portal. Kredensial kehadiran peserta adalah QR undangan berisi token deterministik per anggota (HMAC dari nomor anggota + `APP_KEY`) yang dipindai di stasiun scanner — token selalu sama dan tidak pernah di-regenerate. Yang membedakan eRAT dari ERP: eRAT punya **layar-layar acara langsung** (stasiun scanner, meja helpdesk, papan skor publik, undian doorprize) yang dipakai di depan banyak peserta pada hari rapat. Aturan khusus layar-layar itu ada di bagian 8 — di luar itu, semua aturan umum ERP berlaku sama di sini.

---

## 1. Prinsip Desain

1. **Jelas lebih penting dari cantik.** Tampilan sederhana tidak masalah selama pengguna langsung paham harus klik apa. Jangan mengorbankan kejelasan demi dekorasi.
2. **Konsisten di semua halaman.** Tombol simpan, tabel, form, dan badge status harus berperilaku sama di modul Anggota, Rapat, Presensi, maupun SHU. Kalau ragu, tiru pola halaman yang sudah ada.
3. **Bahasa Indonesia non-teknis.** Label dan pesan ditulis seperti bahasa kantor sehari-hari, bukan bahasa programmer. Contoh: "Cari peserta", bukan "Query participant".
4. **Mobile cukup terbaca.** Petugas helpdesk dan keuangan bisa bekerja dari tablet/HP. Layout tidak boleh rusak di layar kecil: tabel bisa di-scroll horizontal, tombol tetap bisa diklik.
5. **Setiap aksi memberi umpan balik.** Setelah simpan, presensi tercatat, uang diserahkan & resi terbit, atau pemenang terundi, selalu tampilkan hasil: toast sukses atau toast gagal dengan alasan yang jelas. Jangan biarkan pengguna menebak apakah aksinya berhasil.
6. **Cepat dipakai, sedikit klik.** Hari rapat adalah antrean panjang: alur peserta di stasiun scanner dibuat lurus tanpa menu tambahan (scan/ketik kode → foto → tanda tangan → serah uang & resi sesuai mode rapat → selesai), tanpa peserta menyentuh navigasi aplikasi.
7. **Dirancang untuk peserta sepuh.** Banyak anggota koperasi berusia lanjut. Di momen peserta berinteraksi langsung — saat difoto dan menandatangani di stasiun scanner, serta saat memakai Undangan Publik, Form Cek SHU, dan Verifikasi Resi — teks dan tombol dibuat jauh lebih besar dari standar dan kontrasnya tinggi (detailnya di bagian 8.1 dan 8.8), walaupun peserta tidak login dan tidak punya akun.

---

## 2. Warna

Warna mengikuti tema Tabler (`theme-primary=indigo`). Jangan menimpa variabel warna Tabler dengan warna kustom.

| Nama | Kode Hex | Dipakai untuk |
|---|---|---|
| Primer (indigo Tabler) | `#4263eb` | Tombol utama, link, menu aktif, header tabel penting |
| Primer gelap (hover) | `#3b5bdb` | Keadaan hover/focus tombol primer |
| Sekunder (abu-abu) | `#6C757D` | Tombol sekunder/batal, teks sekunder, ikon netral |
| Sukses (hijau) | `#198754` | Toast sukses, badge status "hadir"/"selesai"/"sudah dibayar" |
| Peringatan (kuning) | `#FFC107` | Badge status "draf"/"belum", peringatan validasi |
| Bahaya (merah) | `#DC3545` | Tombol hapus, toast gagal, badge "dibatalkan", pesan error |
| Info (biru muda) | `#0DCAF0` | Badge informasi netral, catatan/informasi tambahan |
| Latar aplikasi | `#F4F6F9` | Background halaman (abu-abu sangat muda) |
| Kartu/konten | `#FFFFFF` | Background card konten |
| Teks utama | `#212529` | Teks isi |
| Teks sekunder | `#6C757D` | Teks bantuan, placeholder, keterangan kecil |
| Garis/border | `#DEE2E6` | Border card, pemisah tabel |

Warna teks di atas badge: badge kuning (`#FFC107`) memakai teks gelap (`#212529`); badge lainnya memakai teks putih.

Jangan menambah warna baru di luar tabel ini tanpa persetujuan. Kalau butuh variasi, pakai opasitas/tint dari warna yang ada.

### 2.1 Tema Terang/Gelap

- Pengguna bisa mengganti tema **Terang** / **Gelap** lewat tombol matahari/bulan di bar atas.
- Implementasi memakai `data-bs-theme` bawaan Tabler — jangan menulis CSS dark mode sendiri.
- Pilihan tema disimpan selama sesi (tidak wajib disimpan permanen di database).
- Semua komponen (card, tabel, sidebar, modal, form) wajib terbaca jelas di kedua tema. Uji setiap halaman baru di kedua tema sebelum lapor selesai.
- **Pengecualian layar acara & publik:** layar scanner, meja helpdesk saat melayani peserta, Papan Skor publik, undian doorprize, Undangan Publik, Form Cek SHU, dan Verifikasi Resi selalu memakai tema Terang dengan kontras maksimal (dibaca dari jauh & di bawah lampu ruangan/proyektor), tidak mengikuti pilihan tema pengguna.

---

## 3. Tipografi

- **Font:** **Inter** — font bawaan Tabler. Disimpan lokal (file `.woff2` di dalam proyek, bukan CDN) agar halaman cepat dimuat dan tetap jalan offline.
- **Ukuran dasar (base):** 16px untuk teks isi.
- **Hierarki heading:**

| Elemen | Ukuran | Berat | Contoh pakai |
|---|---|---|---|
| H1 | 28px | 700 | Judul halaman di topbar (satu per halaman) |
| H2 | 22px | 600 | Judul section di dalam card |
| H3 | 18px | 600 | Sub-judul / judul modal |
| Body | 16px | 400 | Isi teks, label form, sel tabel |
| Kecil | 14px | 400 | Teks bantuan form, keterangan, footer tabel |
| Sangat kecil | 12px | 400 | Timestamp, info meta ("diperbarui 23-10-2026") |

- Judul halaman (H1) hanya satu per halaman dan diletakkan di topbar, bukan di dalam konten.
- Jangan memakai huruf miring untuk penekanan penting — pakai **tebal** atau warna.
- Ukuran di tabel ini berlaku untuk layar administrasi biasa. Layar acara (scanner, kuorum, undian) memakai skala jauh lebih besar — lihat bagian 8.

---

## 4. Komponen Standar

### 4.1 Tombol

- **Primer** (`btn-primary`, indigo Tabler `#4263eb`): aksi utama halaman — "Simpan", "Tambah Anggota", "Bangkitkan Undangan". Maksimal satu tombol primer per baris aksi.
- **Sekunder** (`btn-secondary`, abu-abu): aksi pendamping — "Batal", "Kembali".
- **Bahaya** (`btn-danger`, merah `#DC3545`): hanya untuk "Hapus". Selalu diikuti modal konfirmasi (lihat 4.5).
- **Ukuran:** default untuk form dan halaman; `btn-sm` untuk tombol di dalam baris tabel; `btn-lg` hanya untuk CTA utama yang jarang — di eRAT terutama di langkah-langkah yang disentuh peserta di stasiun scanner (foto, tanda tangan, dan serah uang, lihat 8.1), tombol simpan rekonsiliasi di layar keuangan (lihat 8.3), dan tombol kirim pada form publik (lihat 8.8).
- Tombol nonaktif (`disabled`) dipakai saat aksi belum boleh dilakukan, dengan keterangan alasannya di dekatnya.

### 4.2 Form

- **Label selalu di atas input**, rata kiri, font 16px. Tidak ada label di samping input.
- **Input wajib ditandai `*` merah** di belakang label. Contoh: `Nama Lengkap *`.
- **Pesan error tampil di bawah input** yang bermasalah, berwarna merah (`#DC3545`), font 14px. Input yang error diberi border merah.
- **Teks bantuan** (contoh format yang diharapkan) tampil di bawah input, font 14px, abu-abu (`#6C757D`). Contoh: "Format: DD-MM-YYYY".
- Urutan tombol aksi form: **[Simpan] [Batal]** — primer di kiri, sekunder di kanan. Tombol "Batal" kembali ke halaman sebelumnya tanpa menyimpan.
- Input tanggal memakai date picker bawaan browser; input angka memakai pemisah ribuan otomatis saat diketik (lihat format rupiah di bagian 7).
- **Angka bisnis tidak pernah diketik sebagai konstanta di tampilan.** Ambang kuorum, batas tier transport, dan kriteria undangan selalu dibaca dari pengaturan per rapat yang diisi admin — form setup rapat menyediakan field-nya, halaman lain hanya menampilkan nilainya.

### 4.3 Tabel

- **Striped:** baris ganjil/genap bergantian (`table-striped`) agar mudah membaca baris panjang.
- **Sticky header:** untuk tabel lebih dari 15 baris, header tetap terlihat saat scroll (`position: sticky; top: 0`).
- **Kolom aksi selalu di paling kanan**, berisi tombol kecil: Lihat (ikon mata), Ubah (ikon pensil), Hapus (ikon tempat sampah, merah). Urutan ikon: Lihat → Ubah → Hapus. Tombol yang tampil selalu mengikuti izin role yang login — role tanpa izin ubah/hapus tidak melihat tombolnya (dan server tetap menolak bila dipaksa).
- Header kolom rata kiri, kecuali kolom angka (rupiah, jumlah) rata kanan.
- Tabel panjang dibungkus div dengan scroll horizontal agar tidak rusak di HP (`table-responsive`).
- Baris kosong menampilkan teks "Belum ada data." di tengah, bukan tabel kosong tanpa keterangan.

### 4.4 Badge Status

Status **selalu** ditampilkan sebagai badge berwarna, tidak pernah teks polos. Satu data hanya punya satu status aktif dalam satu waktu. Teks badge memakai huruf kecil kecuali huruf pertama (contoh: "Menunggu", bukan "MENUNGGU").

**Status rapat:**

| Status | Warna badge | Arti |
|---|---|---|
| Draf | Kuning `#FFC107` (teks gelap) | Rapat dibuat, undangan belum dibangkitkan |
| Siap | Biru muda `#0DCAF0` (teks gelap) | Undangan & QR sudah dibangkitkan, menunggu hari pelaksanaan |
| Berlangsung | Hijau `#198754` | Presensi sedang dibuka |
| Selesai | Abu-abu `#6C757D` | Rapat selesai, data terkunci |
| Dibatalkan | Merah `#DC3545` | Rapat dibatalkan |

**Status undangan & pengiriman:**

Token QR **tidak mempunyai status aktif/nonaktif** dan tidak pernah diganti; tabel ini adalah status keanggotaan pada daftar undangan serta status pengiriman salinan undangannya.

| Status | Warna badge | Arti |
|---|---|---|
| Terundang | Biru muda `#0DCAF0` (teks gelap) | Anggota masuk daftar undangan rapat; QR/token yang sama dapat dipakai |
| Tidak terundang | Abu-abu `#6C757D` | Di luar kriteria undangan rapat ini |
| Belum dikirim | Kuning `#FFC107` (teks gelap) | Undangan belum dikirim WA dan belum dicetak |
| Terkirim WA | Hijau `#198754` | Undangan sudah dikirim atau dikirim ulang lewat WA dengan token yang sama |
| Dicetak | Biru muda `#0DCAF0` (teks gelap) | Undangan sudah dicetak dengan token yang sama |

**Status verifikasi anggota:**

| Status | Warna badge | Arti |
|---|---|---|
| Menunggu verifikasi | Kuning `#FFC107` (teks gelap) | Data anggota berstatus `pending`, belum diputuskan admin/helpdesk |
| Terverifikasi | Hijau `#198754` | Status verifikasi `verified` |
| Tidak layak | Merah `#DC3545` | Status verifikasi `rejected`; wajib ada catatan alasan |

**Status kehadiran (per peserta per rapat):**

| Status | Warna badge | Arti |
|---|---|---|
| Belum hadir | Abu-abu `#6C757D` | Terundang, belum presensi datang |
| Hadir | Hijau `#198754` | Presensi datang + tanda tangan tercatat (mode 1x: lengkap) |
| Hadir — belum pulang | Kuning `#FFC107` (teks gelap) | Mode 2x: sudah datang & tanda tangan, presensi pulang belum |
| Lengkap | Hijau `#198754` | Mode 2x: datang, tanda tangan, dan pulang semua tercatat |
| Manual — helpdesk | Biru muda `#0DCAF0` (teks gelap) | Hadir lewat presensi manual pengecualian helpdesk (QR gagal) — selalu ada alasannya di log audit |
| Dikoreksi | Biru muda `#0DCAF0` (teks gelap) | Kehadiran hasil koreksi admin — selalu ada jejaknya di log audit |

**Status penyerahan uang transport:**

| Status | Warna badge | Arti |
|---|---|---|
| Belum diserahkan | Kuning `#FFC107` (teks gelap) | Hadir dan berhak, uang belum diserahkan di titik scan |
| Sudah diserahkan | Hijau `#198754` | Uang sudah diserahkan scanner dan **resi terbit** |
| Menunggu sinkron | Biru muda `#0DCAF0` (teks gelap) | Penyerahan tercatat saat perangkat offline dan masuk antrean sinkron |
| Tidak berhak | Abu-abu `#6C757D` | Tidak hadir, atau di luar ketentuan transport rapat ini |

**Status rekonsiliasi kas meja:**

| Status | Warna badge | Arti |
|---|---|---|
| Belum direkonsiliasi | Kuning `#FFC107` (teks gelap) | Kas awal sudah diisi, sisa kas fisik belum diinput/diperiksa |
| Sesuai | Hijau `#198754` | Sisa kas fisik sama dengan sisa seharusnya |
| Ada selisih | Merah `#DC3545` | Sisa kas fisik berbeda dari sisa seharusnya; catatan penjelasan wajib ada |

### 4.5 Modal Konfirmasi

- **Wajib** untuk aksi yang tidak bisa dibatalkan: **Hapus** data, **Bangkitkan Undangan** (mengunci daftar peserta rapat), **Menjalankan Undian** doorprize, **Menutup Rapat** (mengunci seluruh data rapat), **Serahkan Uang Transport** di titik scan, **Menyimpan Rekonsiliasi Kas** (terutama bila ada selisih), dan **Koreksi SHU**.
- Isi modal: judul jelas ("Bangkitkan Undangan?"), kalimat konfirmasi yang menyebut dampaknya ("Undangan akan dibuat untuk **128 anggota** yang memenuhi kriteria. Daftar ini tidak berubah walau data anggota diubah sesudahnya."), tombol **[Ya, Lanjutkan]** (primer untuk undian/bangkitkan/tutup rapat/bayar; merah khusus untuk Hapus) dan **[Batal]** (abu-abu).
- Untuk koreksi presensi dan presensi manual pengecualian: modal wajib memuat field **alasan** — tanpa alasan, tombol simpan nonaktif.

### 4.6 Toast

- **Toast sukses** (hijau): muncul di kanan atas, otomatis hilang setelah 4 detik. Contoh: "Rekonsiliasi Meja 1 berhasil disimpan."
- **Toast gagal** (merah): muncul di kanan atas, tetap tampil sampai ditutup manual jika pesannya penting. Selalu sertakan alasan: "Rekonsiliasi belum bisa disimpan: selisih kas wajib diisi catatan penjelasannya." — bukan sekadar "Terjadi kesalahan."
- Toast tidak boleh menutupi tombol aksi utama halaman.
- **Pengecualian:** keberhasilan/gagal presensi di layar scanner **tidak** memakai toast kecil — memakai umpan balik layar penuh (lihat 8.1), karena petugas dan peserta harus bisa memastikan dari jarak pandang normal bahwa presensi tercatat.

### 4.7 Pagination

- Tampil di bawah tabel: "Menampilkan 1–20 dari 145 data" di kiri, tombol halaman di kanan.
- 20 data per halaman sebagai default. Pilihan jumlah per halaman: 10 / 20 / 50.
- Nomor halaman yang aktif diberi gaya sesuai Tabler (background primer indigo).

### 4.8 Breadcrumb

- Tampil di atas konten setiap halaman kecuali Dashboard. Format: `Dashboard / Rapat / RAT 2026 / Presensi`.
- Setiap segmen kecuali yang terakhir adalah link yang bisa diklik.

---

## 5. Layout

Struktur halaman mengikuti pola Tabler asli: navbar, page-header (judul halaman + breadcrumb), page-body, container-xl, card.

### Struktur Halaman (navbar vertikal)

```
+--------+--------------------------------------------------+
|        |  Topbar: judul halaman | bell | tema | menu user |
| Side   +--------------------------------------------------+
| bar    |  Breadcrumb                                      |
| kiri   |  +--------------------------------------------+  |
|        |  | Card konten                                  |  |
|        |  +--------------------------------------------+  |
+--------+--------------------------------------------------+
```

- **Sidebar kiri (navbar vertikal):** navigasi utama, bisa di-collapse (ciut menjadi ikon saja) via tombol di topbar. Menu aktif ditandai sesuai gaya Tabler. **Isi sidebar berbeda per role** — lihat bagian 6.
- **Topbar:** berisi judul halaman di kiri; di kanan: ikon lonceng notifikasi (dengan badge angka untuk yang belum dibaca), **tombol ganti tema Terang/Gelap** (ikon matahari/bulan), **tombol ganti tampilan navbar Vertikal/Horizontal**, dan menu user (nama + peran, dropdown berisi "Profil" dan "Keluar").
- **Konten:** memakai struktur Tabler — page-header, page-body, container-xl, card.
- Satu halaman boleh berisi beberapa card yang dipisahkan jarak sesuai standar Tabler.
- **Layar acara** (scanner, pantau kuorum, undian) memakai layout khusus tanpa sidebar — lihat bagian 8. Akun scanner **selalu** memakai layout khusus ini, tidak pernah layout dashboard.

### 5.1 Pilihan Tampilan Navbar

Pengguna (admin, helpdesk, keuangan) bisa memilih dua tampilan navbar lewat tombol di bar atas:

1. **Vertikal (default):** sidebar di kiri seperti struktur di atas.
2. **Horizontal:** sidebar disembunyikan, menu tampil di bar atas sebagai menu horizontal dengan dropdown per grup (sesuai grup role yang login, lihat bagian 6).

- Pilihan navbar disimpan selama sesi (tidak wajib disimpan permanen di database).
- Di HP, navbar vertikal memakai pola drawer (lihat Responsive di bawah); navbar horizontal memakai menu hamburger yang membuka daftar menu.
- Pilihan ini **tidak berlaku untuk scanner**: stasiun scanner tidak punya navbar sama sekali.

### Responsive

- **Desktop (≥ 992px):** sidebar penuh + konten seperti struktur di atas.
- **Tablet/HP (< 992px):** sidebar berubah menjadi **drawer** (panel geser dari kiri) yang muncul saat tombol hamburger di topbar diklik, dan tertutup otomatis setelah menu dipilih atau area luar diklik.
- Tabel memakai scroll horizontal (`table-responsive`); form satu kolom penuh; tombol aksi form full-width di HP.
- Layar scanner dirancang untuk tablet/PC stasiun presensi (≥ 768px) dan tetap berfungsi di HP petugas; layar kuorum & undian dirancang untuk TV/proyektor (lihat 8.4 dan 8.5).

---

## 6. Navigasi per Role

eRAT tidak punya satu menu untuk semua orang. Setiap role melihat **hanya** wilayah tugasnya. Menu yang tidak termasuk izin role **tidak dirender sama sekali** (bukan sekadar disembunyikan), dan server menolak akses langsung lewat URL.

### 6.1 Admin — dashboard penuh

1. **Dashboard**
   - Ringkasan (rapat terdekat/berlangsung, jumlah undangan, hadir saat ini, status kuorum sekilas, ringkasan penyerahan transport & SHU untuk pengawasan)

2. **Anggota** *(khusus admin)*
   - Data Anggota (data induk termasuk simpanan wajib, kolektibilitas, status pinjaman)
   - Verifikasi Anggota (daftar `pending`, putuskan `verified` / Tidak Layak dengan catatan wajib)

3. **Rapat** *(khusus admin)*
   - Daftar Rapat (RAT & rapat biasa)
   - Setup Rapat (jenis, tanggal, lokasi, mode presensi 1x/2x, mode kriteria undangan, tier transport, basis populasi & target kuorum)
   - Undangan & QR (daftar peserta hasil bangkitan + snapshot kriteria, kunci daftar, Kirim WA / Kirim Ulang dengan token yang sama, Cetak Undangan)

4. **Presensi** *(pengawasan & koreksi — admin)*
   - Daftar Kehadiran (per rapat; koreksi beralasan)
   - Arsip Foto & Tanda Tangan (untuk audit kehadiran)
   - Pantau Kuorum (pratinjau Papan Skor Publik — lihat 8.4)

5. **Transport** *(diatur admin, kas diawasi admin)*
   - Tier Tarif (diatur di Setup Rapat)
   - Penyerahan & Resi (tampilan pengawasan saja — penyerahan dilakukan scanner)
   - Kas Meja & Rekonsiliasi (tampilan pengawasan saja — pengisian oleh keuangan)

6. **SHU** *(lihat-saja untuk pengawasan)*
   - Data SHU (tampilan saja — pengelolaan di keuangan)

7. **Doorprize** *(dioperasikan admin)*
   - Hadiah (daftar hadiah per rapat)
   - Undian (layar panggung — lihat 8.5)
   - Pemenang (daftar pemenang terkonfirmasi)

8. **Laporan** *(semua laporan)*
   - Laporan Kehadiran & Kuorum
   - Laporan Transport & Kas (termasuk laporan keuangan detail)
   - Laporan SHU

9. **Administrasi** *(khusus admin)*
   - Manajemen Pengguna (akun admin/helpdesk/scanner/keuangan)
   - Role & Hak Akses
   - Pengaturan Aplikasi (nama koperasi, dsb.)
   - Log Audit

### 6.2 Helpdesk — panel Kelola Anggota & meja bantuan

1. **Beranda Helpdesk**
   - Rapat aktif hari ini, ringkasan hadir/belum hadir, antrean kasus yang diteruskan ke admin

2. **Kelola Anggota** *(panel helpdesk — lihat 8.2)*
   - Daftar & Pencarian Anggota (nomor anggota / nama; filter status verifikasi)
   - Verifikasi Anggota (putuskan `verified` / Tidak Layak; catatan wajib bila Tidak Layak)
   - Koreksi Data Non-sensitif (kontak, alamat, dsb.; nomor anggota terkunci; field sensitif tidak tampil sebagai input)
   - Undangan Peserta (Kirim WA / Kirim Ulang dengan token yang sama, Cetak Undangan — setelah verifikasi identitas)
   - Presensi Manual Pengecualian (peserta terundang berstatus `verified`, kredensial gagal, alasan wajib)

3. **Riwayat Kehadiran**
   - Riwayat peserta seperlunya untuk menjawab pertanyaan & menangani kasus

4. **Pemenang Doorprize**
   - Daftar pemenang terkonfirmasi (lihat-saja, untuk menjawab pertanyaan peserta)

Helpdesk **tidak** melihat menu: field sensitif anggota, Setup Rapat & Tier, Penyerahan/Kas Transport, Kelola SHU (SHU dicek peserta lewat form publik), Undian, Laporan, Pengaturan, User, Log Audit. Helpdesk juga **tidak** punya akses foto/tanda tangan.

### 6.3 Scanner — tanpa navigasi

- Akun scanner **tidak punya sidebar, topbar menu, atau dashboard**. Setelah login, scanner langsung masuk ke **Layar Scanner** rapat yang sedang aktif (lihat 8.1) dan tidak bisa berpindah ke modul lain.
- Satu-satunya elemen navigasi di layar scanner: indikator rapat aktif, pemilih sesi **Masuk/Pulang**, tab **Scan / Ketik Manual**, indikator koneksi & antrean sinkron, ringkasan stasiun, dan tombol **Keluar** (logout) kecil di sudut.
- Jika belum ada rapat berstatus Berlangsung, layar scanner menampilkan pesan besar "Belum ada rapat yang sedang berlangsung" — bukan dashboard kosong.

### 6.4 Keuangan — kas meja, laporan & SHU

1. **Dashboard Keuangan**
   - Ringkasan (kas awal semua meja, total sudah diserahkan scanner, sisa seharusnya, meja yang belum direkonsiliasi, total SHU per tahun buku)

2. **Kas Meja** *(dikelola keuangan — lihat 8.3)*
   - Kas Awal per Meja Scanner (input sebelum acara)
   - Pantau Penyerahan (resi & nominal per meja, lihat-saja — penyerahan dilakukan scanner; tier hanya ditampilkan, diatur admin di Setup Rapat)
   - Rekonsiliasi (input sisa kas fisik, selisih otomatis, catatan wajib bila selisih)

3. **Audit Kas**
   - Daftar Resi (per meja/per rapat; buka resi untuk memeriksa foto & tanda tangan kehadiran terkait — khusus audit kas)

4. **SHU** *(dikelola keuangan)*
   - Data SHU (per anggota per tahun buku: balas jasa saham + balas jasa pinjaman)
   - Impor SHU (unggah berkas, pratinjau, konfirmasi)
   - Koreksi SHU (dengan alasan, tercatat audit)

5. **Kehadiran** *(lihat-saja)*
   - Daftar Hadir (per rapat, dasar pemeriksaan kas)
   - Total Kuorum (angka agregat saja)

6. **Laporan**
   - Laporan Keuangan Detail (transport, kas meja & rekonsiliasi)
   - Laporan Transport
   - Laporan SHU

7. **Pemenang Doorprize**
   - Daftar pemenang terkonfirmasi (lihat-saja, bila diperlukan untuk pencatatan)

Keuangan **tidak** melihat menu: field sensitif anggota, Setup/Kriteria Rapat & edit Tier, Undangan & QR, Presensi (scan/manual/koreksi), Undian (menjalankan), Pengaturan, User, Log Audit. Panel anggota untuk keuangan bersifat **baca-saja**.

### 6.5 Aturan visibilitas

- Menu dan tombol dirender **per role** dari permission di server — daftar di 6.1–6.4 adalah hasil akhirnya, bukan aturan CSS. Role yang mencoba membuka URL di luar izinnya menerima halaman "Tidak ada akses" (403) berbahasa Indonesia.
- Nama menu memakai bahasa Indonesia yang sudah dikenal di kantor; jangan membuat istilah baru.
- Item menu yang belum ada halamannya **tidak ditampilkan** — jangan menampilkan menu mati.
- **Tidak ada Portal Anggota.** Peserta tidak login, tidak punya menu, dan tidak punya akun. Semua kebutuhan peserta dilayani lewat stasiun scanner (presensi), meja helpdesk (pertanyaan & pengecualian), dan halaman publik terbatas di 6.6.

### 6.6 Halaman Publik (tanpa login)

Empat halaman ini **bukan menu role** dan tidak membutuhkan akun. Isinya dibatasi ketat — hanya agregat, data pemilik token, atau hasil dari kode yang dimasukkan sendiri:

1. **Papan Skor Kuorum** — agregat kuorum untuk proyektor (lihat 8.4). Tanpa nama/data personal.
2. **Undangan Publik** — undangan milik satu anggota berdasarkan tokennya: QR besar + kode kehadiran teks (lihat 8.8).
3. **Form Cek SHU** — form satu field nomor anggota; hasil SHU nomor itu saja (lihat 8.8).
4. **Verifikasi Resi** — hasil teks + status resi berdasarkan kode resi (lihat 8.8).

Halaman publik tidak menampilkan navigasi aplikasi, tidak bisa menjalankan presensi, dan tidak membuka konsol petugas.

---

## 7. Aturan Konsistensi

Aturan ini berlaku di **semua** halaman, tabel, form, laporan, dan cetakan. Tidak ada pengecualian.

| Aturan | Format baku | Contoh benar | Contoh salah |
|---|---|---|---|
| Tanggal | `DD-MM-YYYY` | 23-10-2026 | 2026/10/23, 23 Okt 2026 |
| Rupiah | `Rp` + spasi + titik pemisah ribuan, tanpa desimal | Rp 150.000 | Rp150.000 / Rp 150,000 / 150000 |
| Jam | `HH:MM`, 24 jam | 07:30, 15:30 | 7:30 AM, 07.30 |
| Status | Selalu badge berwarna (lihat 4.4) | 🟡 Belum dibayar | teks polos "belum dibayar" |
| Tombol hapus | Selalu merah + modal konfirmasi | `btn-danger` + modal | tombol hapus abu-abu / langsung hapus |

Tambahan:
- **Nomor anggota** ditampilkan persis seperti tersimpan di data induk (jangan diubah formatnya oleh tampilan) — nomor ini yang dicari petugas helpdesk dan yang tercetak bersama QR undangan peserta.
- **Nama orang** ditulis lengkap apa adanya dari data (jangan disingkat otomatis).
- **Pesan kosong** memakai kalimat baku: tabel kosong → "Belum ada data."; hasil pencarian kosong → "Tidak ditemukan data yang cocok dengan pencarian."; pencarian peserta di helpdesk tanpa hasil → "Peserta tidak ditemukan. Periksa lagi nomor anggota atau namanya."
- **Konfirmasi sebelum keluar** dari form yang sudah diubah tapi belum disimpan (browser `beforeunload` atau modal "Perubahan belum disimpan").

---

## 8. Layar Khusus per Role (Hari Rapat)

Layar-layar di bagian ini dipakai **saat rapat berjalan**, di depan peserta, sering lewat proyektor atau di stasiun/meja yang sibuk. Aturannya menimpa aturan umum bila bertentangan: skala lebih besar, langkah lebih sedikit, tema selalu Terang, tanpa sidebar/topbar standar (cukup judul rapat + indikator mode + tombol keluar yang kecil dan tidak mencolok).

### 8.1 Layar Scanner (Stasiun Presensi)

Layar ini adalah satu-satunya layar role scanner. Dioperasikan petugas stasiun, dengan peserta berdiri di depannya. **Peserta tidak login dan tidak menavigasi apa pun** — peserta hanya mengikuti alur: tunjukkan QR / sebutkan kode → difoto → tanda tangan → terima uang & resi (pada sesi penyerahan) → selesai. Banyak peserta berusia lanjut, jadi momen interaksi peserta dibuat sebesar dan sejelas mungkin:

- **Dua tab input: Scan & Ketik Manual.** Di bawah pemilih sesi ada dua tab besar yang selalu terlihat:
  - **Tab Scan (bawaan):** pratinjau kamera pemindai besar di tengah layar dengan bingkai penanda area QR dan kalimat petunjuk besar: "Tunjukkan QR undangan Anda ke kamera". Pemindaian berjalan terus (tanpa tombol) selama tab aktif.
  - **Tab Ketik Manual:** kolom teks besar untuk mengetik **kode kehadiran** yang tercetak di undangan (di bawah QR), tombol **[Proses Kode]** besar di sampingnya. Kode diketik persis; hasil validasinya sama dengan scan kamera. Tab ini untuk QR yang rusak/tidak terbaca kamera — **bukan** presensi manual pengecualian (itu wilayah helpdesk). Bila kode pun tidak valid, peserta diarahkan ke helpdesk (lihat poin gagal scan).
- **Pemilih sesi Masuk/Pulang jelas:** di bagian atas layar ada dua tombol alih besar — **SESI MASUK** dan **SESI PULANG** — sesi aktif tampil dalam huruf besar dengan warna konsisten (masuk: primer `#4263eb`; pulang: info `#0DCAF0` teks gelap). Petugas mengganti sesi dari tombol ini tanpa berpindah menu. Pada rapat mode 1x hanya sesi Masuk yang relevan; sesi Pulang menonaktifkan area input dan menampilkan keterangan "Rapat ini mode 1x — tidak ada presensi pulang".
- **Hasil scan/kode = layar penuh:** begitu QR terbaca atau kode diterima, layar menampilkan **nama peserta besar (≥ 48px)**, nomor anggota, dan statusnya, lalu alur berlanjut otomatis:
  - **Masuk (semua mode):** nama terkonfirmasi → **Ambil foto wajah** → **Tanda tangan digital** → penyerahan uang (khusus mode 1x) → selesai.
  - **Pulang (mode 2x):** nama terkonfirmasi → tombol besar **[Catat Pulang]** → penyerahan uang & resi → selesai. Tanpa foto/tanda tangan ulang.
- **Nominal final & tombol serah uang:** pada sesi penyerahan (mode 1x saat masuk; mode 2x saat pulang), langkah terakhir menampilkan **nominal uang transport final sangat besar (≥ 56px)** hasil hitung sistem dari tier + waktu presensi — petugas dan peserta membacanya bersama. Di bawahnya satu tombol raksasa **[Serahkan Uang & Terbitkan Resi]** (primer, tinggi ≥ 64px). Sekali ditekan: penyerahan tercatat, **resi terbit** (kode resi tampil besar di layar dan resi tercetak bila printer terhubung), lalu layar sukses. Nominal tidak bisa diketik/diubah di layar ini.
- **Indikator offline & antrean sinkron:** di bar atas layar selalu tampil status koneksi: **ONLINE** (hijau `#198754`) atau **OFFLINE** (merah `#DC3545`) plus jumlah antrean, mis. "3 data menunggu sinkron". Saat offline, presensi dan penyerahan tetap dicatat di perangkat dan masuk **antrean sinkron**; item antrean tampil dengan badge "Menunggu sinkron" (lihat 4.4). Begitu koneksi kembali, antrean terkirim otomatis satu per satu dan angka antrean berkurang terlihat — petugas tidak menekan apa pun. Resi dari data offline tetap terbit dengan kode resi yang sama; statusnya di Verifikasi Resi terbaca "Menunggu sinkron" sampai data diterima server.
- **Area foto wajah:** pratinjau kamera tampil besar (rasio 4:3) dengan panduan siluet wajah sederhana; tombol **[Ambil Foto]** besar (tinggi ≥ 56px) di bawah pratinjau; tombol **[Foto Ulang]** selalu tersedia sebelum disimpan. Foto tidak pernah diambil diam-diam — selalu lewat tombol yang terlihat peserta.
- **Pad tanda tangan digital:** kanvas besar berbingkai jelas (garis border `#DEE2E6`, latar putih) tempat peserta menandatangani dengan mouse/touch/stylus. Dua tombol besar di bawahnya: **[Ulangi]** (sekunder) dan **[Simpan & Selesai]** (primer). Kanvas kosong tidak bisa disimpan — tombol Simpan nonaktif sampai ada goresan.
- **Umpan balik sukses layar penuh:** begitu presensi (dan penyerahan, bila ada) tersimpan, seluruh layar berubah hijau (`#198754`) selama ±3 detik menampilkan **nama peserta besar (≥ 48px)**, statusnya ("Hadir — 07:42" atau "Pulang tercatat — 15:30"), nominal yang diserahkan + kode resi bila ada, dan ikon centang besar; lalu otomatis kembali ke tab Scan untuk peserta berikutnya. Tidak ada klik tambahan untuk lanjut antrean.
- **Gagal scan selalu mengarah ke helpdesk:** token tidak dikenal/tidak cocok, peserta tidak terundang di rapat ini, peserta belum `verified`, atau peserta sudah presensi — semuanya menampilkan layar merah (`#DC3545`) dengan alasan terbaca dari jarak 1 meter **dan** kalimat arahan baku: **"Silakan ke Meja Helpdesk"** (≥ 32px). Stasiun scanner **tidak** menyediakan jalan keluar lain: tidak ada pencarian nama peserta, tidak ada presensi manual pengecualian, tidak ada koreksi di layar ini.
- **Ringkasan stasiun selalu terlihat:** di sudut layar tampil ringkasan kecil namun terbaca: "Stasiun ini: 43 presensi", "Uang diserahkan stasiun ini: Rp 2.150.000", dan "Total hadir: 87 dari 128 — Kuorum tercapai/belum" (badge sesuai 4.4). Petugas memantau laju antrean dan kas yang keluar dari mejanya tanpa meninggalkan layar.
- **Tanpa navigasi:** tidak ada sidebar, breadcrumb, atau menu modul. Hanya judul rapat, indikator mode, ringkasan stasiun, dan tombol **Keluar** kecil di sudut untuk petugas.

### 8.2 Layar Helpdesk (Panel Kelola Anggota & Meja Bantuan)

Layar helpdesk adalah **panel Kelola Anggota** sekaligus meja bantuan hari rapat — tempat verifikasi anggota dikerjakan (terutama pra-rapat) dan semua pengecualian dari stasiun scanner bermuara. Dirancang untuk menjawab cepat sambil peserta berdiri di depan meja:

- **Pencarian & filter paling mencolok:** kolom pencarian besar di bagian atas (placeholder "Ketik nomor anggota atau nama peserta…"), fokus otomatis, hasil muncul saat mengetik (Livewire). Di sampingnya ada filter badge status verifikasi: **Semua / Menunggu / Terverifikasi / Tidak layak** (warna persis 4.4) dan filter status undangan rapat aktif.
- **Kartu anggota satu layar:** hasil pencarian membuka kartu berisi nama besar, nomor anggota, dan badge yang selalu tampil bersama: **Status Verifikasi**, **Status Undangan**, **Status Kehadiran**, **Status Transport** — petugas menjawab sebagian besar pertanyaan dari kartu ini tanpa berpindah halaman. Kartu juga menampilkan riwayat singkat (jam datang/pulang, cara presensi: scan/kode/manual) seperlunya. Kartu helpdesk **tidak menampilkan foto/tanda tangan** — helpdesk tidak punya akses media.
- **Verifikasi anggota:** untuk anggota berstatus `pending`, kartu menampilkan dua tombol keputusan: **[Verifikasi — Layak]** (primer) dan **[Tidak Layak]** (bahaya). Menekan **Tidak Layak** membuka modal berisi field **catatan alasan wajib** — tombol simpan nonaktif sampai catatan terisi. Keputusan tersimpan beserta nama petugas dan waktunya; badge status langsung berubah. Anggota `rejected` menampilkan catatan penolakannya dan tombol **[Kembalikan ke Menunggu]** untuk verifikasi ulang setelah datanya dikoreksi.
- **Koreksi data non-sensitif:** tombol **[Ubah Data]** membuka form berisi hanya field non-sensitif (kontak, alamat, dan profil layanan lain). **Nomor anggota tampil terkunci** (readonly, ikon gembok, keterangan "Nomor anggota tidak dapat diubah"). Field sensitif (status anggota, simpanan wajib, kolektibilitas) **tidak dirender sebagai input sama sekali** di panel helpdesk — bukan sekadar dinonaktifkan.
- **Verifikasi identitas sebelum kirim/cetak undangan:** tombol **[Kirim WA]**, **[Kirim Ulang]**, dan **[Cetak Undangan]** baru aktif setelah petugas menandai checklist verifikasi di kartu (mis. "KTP/kartu anggota cocok", "Tanggal lahir cocok"). Pengiriman bersifat **per peserta dari kartunya** — tidak ada tombol kirim massal/broadcast di panel ini. Semua kirim/cetak memakai **QR/token yang sama**; tidak ada tombol terbitkan/ganti/regenerate QR di panel mana pun. Setiap kirim/cetak tercatat.
- **Presensi manual pengecualian:** tombol **[Presensi Manual]** hanya muncul untuk peserta **Terundang** berstatus **`verified`** yang belum hadir. Membuka modal berisi: peringatan bahwa tindakan ini tercatat di log audit, pilihan/isi **alasan wajib** (mis. "QR rusak/tidak terbaca", "Undangan tertinggal"), dan tombol **[Catat Hadir Manual]**. Setelah tersimpan, badge kehadiran peserta menampilkan "Manual — helpdesk" (lihat 4.4). Untuk peserta **tidak terundang** atau belum `verified`, tombol ini tidak ada — yang tampil tombol **[Teruskan ke Admin]** yang mencatat kasus ke antrean admin.
- **Tidak ada SHU & media di panel ini:** helpdesk tidak menampilkan nominal SHU dan tidak membuka foto/tanda tangan. Peserta yang bertanya SHU diarahkan dengan kalimat baku: "Silakan cek lewat Form Cek SHU, cukup masukkan nomor anggota."
- **Bahasa paling sederhana ke peserta:** pesan dan label yang dibacakan/ditunjukkan ke peserta memakai kalimat sehari-hari ("Undangan Bapak/Ibu kami kirim ulang ya, kodenya tetap sama"), hindari istilah sistem.

### 8.3 Layar Keuangan (Kas Meja, Laporan & SHU)

Layar kerja keuangan — dipakai **sebelum, selama, dan sesudah** rapat untuk kas meja dan SHU. Padat data seperti halaman admin biasa (skala standar bagian 3). Keuangan **tidak menyerahkan uang ke peserta** (itu terjadi di titik scan) dan **tidak mengedit tier** (tier diatur admin di Setup Rapat, di layar ini hanya ditampilkan). Elemennya:

- **Kas Meja — kartu per meja scanner:** satu kartu/tabel per meja berisi: nama meja & petugas scanner, **Kas Awal** (input rupiah oleh keuangan sebelum acara, tombol **[Simpan Kas Awal]**), **Total Diserahkan** (jumlah otomatis dari resi meja itu), **Sisa Seharusnya** (kas awal − total diserahkan, hitungan sistem, tidak bisa diketik), **Sisa Kas Fisik** (input keuangan saat penutupan), **Selisih** (sisa fisik − sisa seharusnya; Rp 0 hijau "Sesuai", selain itu merah "Ada selisih" — warna persis 4.4), dan status rekonsiliasi.
- **Rekonsiliasi:** tombol **[Rekonsiliasi]** per meja membuka modal berisi ringkasan angka di atas + field **Catatan**. Bila selisih ≠ Rp 0, field catatan **wajib terisi** dan tombol **[Simpan & Kunci Rekonsiliasi]** nonaktif sampai catatan ada; bila sesuai, catatan boleh kosong. Rekonsiliasi terkunci tidak bisa diubah dari layar ini. Strip ringkasan di atas halaman selalu terlihat: total kas awal semua meja, total diserahkan, jumlah meja sesuai / ada selisih / belum direkonsiliasi.
- **Pantau penyerahan (lihat-saja):** tabel resi masuk secara realtime: kode resi, nama peserta, nomor anggota, meja, jam, nominal, badge status penyerahan (4.4) termasuk "Menunggu sinkron" untuk data offline. Filter cepat: Semua / Per meja / Sudah diserahkan / Menunggu sinkron. Tidak ada tombol bayar/serah di layar ini.
- **Tier transport (tampilan saja):** tabel tier rapat aktif (batas waktu datang → nominal) dalam format rupiah baku, diberi label jelas "Diatur oleh Admin di Setup Rapat" — tanpa tombol ubah. Fungsinya agar keuangan dapat memperkirakan kebutuhan kas per meja.
- **Audit kas & media:** dari baris resi, tombol **[Periksa]** membuka penampil audit berisi detail presensi terkait resi itu (jam, cara presensi, meja) beserta **foto wajah dan tanda tangan** peserta — khusus pemeriksaan kas. Penampil ini hanya ada di area keuangan & admin; aksesnya tercatat. Tidak ada galeri media bebas di luar konteks resi.
- **SHU:** tabel data SHU per tahun buku (filter tahun) dengan kolom komponen **Balas Jasa Saham** dan **Balas Jasa Pinjaman** serta totalnya; tombol **Impor** dengan alur unggah → pratinjau baris → konfirmasi; tambah/input manual per anggota; koreksi nilai lewat modal berisi **alasan wajib** (tercatat audit). Nominal selalu format rupiah baku.
- **Laporan:** keuangan membuka **laporan keuangan detail** (penyerahan per meja, rekonsiliasi & selisih, SHU) plus laporan transport/SHU — semua dengan tombol ekspor/cetak standar. 
- **Daftar hadir lihat-saja:** keuangan membuka daftar hadir & total kuorum agregat sebagai dasar pemeriksaan kas — semua kontrol presensi (scan, manual, koreksi) **tidak dirender** di area keuangan. Panel anggota di area keuangan bersifat baca-saja.

### 8.4 Papan Skor Kuorum Publik (Proyektor)

Layar ini ditampilkan terus-menerus di ruangan rapat agar semua hadirin melihat sah/tidaknya rapat. Ini **halaman publik tanpa login** — cukup buka alamatnya di perangkat proyektor. Isinya **hanya agregat**: tidak ada nama, nomor anggota, foto, atau detail peserta apa pun. Dirancang dibaca dari jarak 10 meter:

- **Angka raksasa:** jumlah hadir dan populasi ditampilkan ≥ 120px (contoh: `87 / 128`), persentase di bawahnya ≥ 72px (contoh: `68%`). Populasi mengikuti basis yang dipilih per rapat (**total** anggota jenis `biasa`, atau anggota `biasa` yang **terverifikasi**) dan hanya anggota jenis `biasa` yang dihitung — keterangan basis tampil kecil di bawah angka ("Dari 128 anggota biasa terverifikasi"). Di bawah angka: keterangan target dalam kalimat biasa, mis. "Kuorum tercapai bila hadir ≥ 65 peserta" — target dihitung sistem dengan rumus `floor(persen × populasi) + tambahan` dari konfigurasi rapat.
- **Indikator warna layar:** selama kuorum **belum** tercapai, aksen layar kuning (`#FFC107`) dengan label besar **"KUORUM BELUM TERCAPAI"**; begitu tercapai, aksen berubah hijau (`#198754`) dengan label **"KUORUM TERCAPAI — RAPAT SAH"**. Perubahan status disertai perubahan warna seluruh bingkai layar, terlihat tanpa membaca.
- **Bilah kemajuan (progress bar)** setinggi ≥ 24px dari hadir menuju ambang, memakai warna primer; ambang ditandai garis penanda yang jelas.
- **Selalu segar otomatis:** angka diperbarui realtime (Livewire polling) tanpa refresh manual — layar tidak pernah menampilkan angka basi lebih dari beberapa detik.
- **Isi layar hanya itu:** judul rapat + tanggal di atas (kecil), angka kuorum, indikator status, dan jam terakhir diperbarui. Tidak ada menu, tabel, tombol, atau elemen lain yang bersaing perhatian — dan tidak ada data personal, karena layar ini publik.
- Ringkasan kuorum versi kecil (total hadir + status tercapai/belum) juga tampil di layar scanner (8.1) dan dashboard keuangan — isinya sama (agregat), hanya skalanya yang berbeda.

### 8.5 Layar Undian Doorprize (Panggung)

Layar hiburan resmi rapat — tampil di proyektor saat pengundian, **dioperasikan admin** dari perangkat yang sama/terhubung. Helpdesk dan keuangan hanya melihat daftar pemenang terkonfirmasi secara lihat-saja dari area masing-masing (bukan layar panggung ini):

- **Tampilan panggung:** latar bersih kontras tinggi, judul hadiah yang sedang diundi tampil besar (≥ 40px, mis. "Hadiah: Sepeda Lipat"), daftar peserta yang berhak ikut cukup ditampilkan sebagai jumlah ("dari 87 peserta hadir"), bukan daftar nama.
- **Animasi sederhana:** saat undian berjalan, nama-nama peserta hadir berganti cepat (efek acak nama bergulir) selama beberapa detik lalu melambat dan berhenti di satu nama. Animasi memakai JavaScript vanilla/CSS — tidak ada library animasi tambahan, tidak ada efek berlebihan yang mengganggu peserta sepuh.
- **Nama pemenang raksasa:** nama pemenang berhenti di tengah layar ≥ 72px, disertai nomor anggota dan label "PEMENANG" — cukup dibaca dari baris kursi terjauh. Tombol **[Konfirmasi Pemenang]** mengunci hasil (tercatat, tidak bisa diundi ulang untuk hadiah yang sama) dan **[Hadiah Berikutnya]** lanjut ke undian berikutnya.
- **Jaminan keadilan tampil sebagai aturan, bukan janji:** hanya peserta berstatus hadir yang masuk mesin undi, dan pemenang yang sudah terkonfirmasi otomatis keluar dari undian hadiah berikutnya di rapat yang sama (maksimal satu kemenangan per peserta per rapat).
- **Riwayat pemenang:** di sisi bawah layar tampil daftar pemenang yang sudah terkonfirmasi pada rapat ini (nama + hadiah), huruf cukup besar untuk dibaca hadirin.

### 8.6 Dashboard Admin (Penuh)

Beranda admin adalah satu-satunya dashboard yang menampilkan **seluruh** gambaran rapat dalam satu tempat:

- **Kartu ringkasan rapat aktif:** judul rapat, badge status rapat (4.4), jumlah undangan, hadir saat ini, status kuorum, dan progres penyerahan transport (sudah diserahkan dari total berhak) serta status kas meja — angka keuangan tampil sebagai ringkasan pengawasan; pengisian kas & rekonsiliasi tetap di area keuangan, penyerahan tetap di titik scan.
- **Jalan pintas operasional:** tombol ke Setup Rapat, Undangan & QR, Daftar Kehadiran, Pantau Kuorum, dan Undian — sesuai alur hari rapat.
- **Peringatan yang butuh admin:** antrean kasus teruskan dari helpdesk (peserta tidak terundang, anggota menunggu verifikasi, undangan belum terkirim), presensi manual yang tercatat hari ini, meja yang belum direkonsiliasi atau berselisih, dan koreksi terakhir — masing-masing tautan langsung ke data terkait.
- Dashboard role lain bersifat terbatas mengikuti areanya (Beranda Helpdesk di 6.2, Dashboard Keuangan di 6.4) dan **tidak** meniru kelengkapan dashboard admin.

### 8.7 Ringkasan Badge di Layar Acara

Di layar scanner, helpdesk, kuorum, dan daftar kehadiran, badge status mengikuti pemetaan bagian 4.4 persis — jangan membuat varian warna baru untuk layar acara. Perbedaannya hanya ukuran: badge di layar yang dilihat peserta dibuat ukuran besar (padding & font diperbesar setara `btn-lg`) agar terbaca sekilas.

### 8.8 Halaman Publik (Peserta, Tanpa Login)

Tiga halaman publik ini dipakai langsung oleh peserta dari HP-nya — **mobile-first, satu kolom, tanpa sidebar/menu aplikasi**, tema Terang, teks isi 16–18px, target sentuh ≥ 48px, bahasa paling sederhana. Tidak satu pun halaman ini bisa menjalankan presensi atau mengubah data.

- **Undangan Publik (berdasarkan token):** alamat undangan memuat token anggota; halaman menampilkan judul rapat, tanggal/jam, lokasi, nama + nomor anggota pemilik undangan, lalu **QR besar di tengah layar** (minimal 240×240px, kontras penuh, keterangan "Tunjukkan QR ini di meja presensi") dan **kode kehadiran dalam teks besar** di bawahnya (untuk diketik petugas bila QR tidak terbaca) + tombol **[Perbesar QR]** yang membuat QR memenuhi layar dengan kecerahan penuh. Tidak ada data lain di halaman ini — tidak ada simpanan, SHU, atau data anggota lain. QR/kode yang tampil selalu token yang sama dengan undangan WA & cetakan.
- **Form Cek SHU Publik:** satu-satunya isi halaman adalah satu field besar **"Nomor Anggota"** dan satu tombol besar **[Lihat SHU Saya]**. Hasilnya tampil sebagai kartu sederhana: nama anggota, tahun buku, rincian **Balas Jasa Saham** dan **Balas Jasa Pinjaman**, serta **Total SHU** dalam huruf besar — format rupiah baku. Bila nomor tidak ditemukan atau belum ada data SHU, tampilkan satu kalimat ramah dan netral ("Data tidak ditemukan. Periksa lagi nomor anggota Bapak/Ibu, atau tanyakan ke petugas.") tanpa membocorkan data apa pun. Tidak ada daftar/tabel anggota di halaman ini; percobaan dibatasi sistem (throttle) dan tercatat, tetapi batas itu tidak perlu ditampilkan sebagai istilah teknis — cukup pesan "Terlalu banyak percobaan, silakan tunggu sebentar" bila batas tercapai.
- **Verifikasi Resi Publik:** satu field besar **"Kode Resi"** (kode tercetak di resi peserta) dan tombol **[Periksa Resi]**. Hasilnya adalah **teks biasa yang jelas + badge status**: kalimat hasil seperti "Resi ini ASLI — uang transport Rp 75.000 untuk RAT 2026 sudah diserahkan pada 23-10-2026 pukul 08:15 di Meja 1." dengan badge status sesuai 4.4: **Sudah diserahkan** (hijau), **Menunggu sinkron** (biru muda, dengan kalimat tambahan "Data sedang dalam perjalanan ke server, resi tetap sah"), atau pesan netral "Kode resi tidak ditemukan" (merah muda sebagai teks hasil, tanpa detail tambahan). Halaman ini hanya membaca status resi — tidak menampilkan foto, tanda tangan, atau data peserta lain.
