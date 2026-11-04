# ALUR PROSES SEMUA ROLE — eRAT Koperasi Kredit Rukun

> **Status dokumen:** 4 November 2026. Database MySQL `erat_rukun` dapat direset dari aplikasi; setelah reset hanya akun Admin yang tersedia dan akun petugas lain dibuat melalui menu **Pengguna**.
> **Fase 3 aktif:** peserta rapat, penilaian kelayakan, keputusan Verified/Rejected, dan token QR kini **tersimpan di database**.
> **Fase 5 aktif:** presensi dibuka Admin, Scanner memindai QR (kamera atau token manual), mencatat kehadiran beserta foto dan tanda tangan, transport dipotong dari kas Scanner. Mode Presensi 2x (Datang-Pulang) tersedia per rapat dan membayar transport saat pulang (D-034); offline tidak dikerjakan (D-032).
> **Tujuan:** panduan langkah demi langkah untuk mencoba aplikasi dan menguji tiap role tanpa menebak tombol mana yang harus diklik.
> **Aturan bisnis acuan:** `docs/01-business/BUSINESS_RULES.md` dan `DECISION_LOG.md`. Dokumen ini hanya menjelaskan alur, bukan mengganti aturan.

## 1. Cara membaca status fitur

| Tanda | Arti |
|---|---|
| ✅ | Berfungsi dan tersimpan di database. Aman untuk diuji serius. |

## 2. Akun petugas

Setelah reset total, hanya akun Admin bawaan yang tersedia dengan sandi awal `password`. Admin membuat akun petugas lain dan menentukan sandi awal minimal 8 karakter melalui menu **Pengguna**.

| Role | Username | Bisa membuka |
|---|---|---|
| Admin | `admin` | Semua menu |
| Helpdesk | Dibuat Admin | Anggota & Undangan, Riwayat Undangan; tidak dapat melakukan presensi |
| Scanner | Dibuat Admin | Kas Saya, Status Koneksi, presensi datang/pulang |
| Keuangan | Dibuat Admin | Kas Scanner, Rekonsiliasi, Transport |

Login dibatasi 5 percobaan per menit. Anggota koperasi **tidak punya akun login**.

> Akun petugas dikelola Admin lewat **Pengaturan - Pengguna** (tambah, ubah, hapus, ganti sandi). Role hanya empat pilihan tetap; anggota koperasi tidak dibuatkan akun. Setelah reset total hanya akun Admin yang tersisa.

## 3. Gambaran besar satu siklus rapat

```
Persiapan          Verifikasi           Undangan            Kas                Hari-H                 Penutupan
Admin              Admin/Helpdesk       Admin/Helpdesk      Keuangan           Scanner                Admin
  │                    │                    │                  │                  │                      │
  ├─ buat parameter    ├─ cek kelayakan     ├─ kirim WA        ├─ alokasi kas     ├─ scan QR             ├─ kuorum
  ├─ input anggota     ├─ Verified/Rejected ├─ cetak QR         ├─ monitor saldo   ├─ bayar transport     ├─ SHU
  └─ kunci saat mulai  └─ QR terbit          └─ log petugas     └─ rekonsiliasi    └─ foto & TTE          └─ doorprize
```

Satu rapat hanya punya satu set parameter. Parameter dikunci otomatis begitu rapat berstatus **Berlangsung**.

## 4. Alur Admin

Admin adalah satu-satunya role yang boleh mengubah parameter rapat, master anggota, dan SHU.

### 4.1 Menyiapkan rapat

1. Masuk sebagai Admin → menu **Manajemen Rapat**.
2. Pada kunjungan pertama, sistem membuat rapat **Draf** dengan nilai awal.
3. Isi tab **Informasi Umum**: nama rapat, jenis, tanggal, dan lokasi.
4. Tab **Kriteria Kelayakan**: isi Tahun Acuan, pilih Mode A atau B, simpanan minimum setahun, tarif per bulan, dan kolektibilitas maksimum. Tanggal acuan saldo otomatis 31 Desember Tahun Acuan.
5. Tab **Presensi**: jam mulai presensi dan jam mulai acara diisi terpisah. Mode Presensi 1x atau Mode Presensi 2x (Datang-Pulang); mode 2x membayar transport saat pulang (D-034).
6. Tab **Uang Transport**: nominal dasar, toleransi, dan tier potongan. Tier harus bersambung mulai menit ke-1; celah dan tumpang tindih ditolak.
7. Tab **Kuorum**: basis populasi (`semua_biasa` = seluruh peserta berjenis biasa dan aktif, `terverifikasi` = hanya yang sudah Verified), persentase, tambahan.
8. Tab **Doorprize**: siapkan nama hadiah, jumlah unit, dan keterangan opsional. Daftar tetap dapat diperbarui dengan pengaman arsip D-044.
9. Klik **Simpan Pengaturan**. Muncul pesan "Pengaturan rapat disimpan."
10. Klik **Mulai Verifikasi** saat parameter final. Status berubah menjadi **Verifikasi**.
11. Klik **Siapkan Peserta** pada kartu rapat. Sistem menilai seluruh anggota (Layak/Tidak Layak) dan menyimpan snapshot parameter saat itu. Aman diklik berulang: peserta yang sudah ada dilewati, keputusan lama tidak ditimpa.
12. Klik **Mulai Rapat** pada hari-H. Status menjadi **Berlangsung** dan parameter utama terkunci.
13. Setelah presensi dibuka, pantau menu **Kuorum** (hadir vs target, status sah, jam tercapai). Angka dihitung ulang dari data presensi, bukan dari counter terpisah.
14. Setelah acara, klik **Selesaikan Rapat**.

| Aksi | Syarat status | Akibat |
|---|---|---|
| Mulai Verifikasi | Draf | Status Verifikasi, parameter masih bisa diubah |
| Mulai Rapat | Verifikasi | Status Berlangsung, parameter utama terkunci |
| Selesaikan Rapat | Berlangsung | Status Selesai |
| Batalkan | Draf / Verifikasi | Status Dibatalkan |

### 4.2 Mengisi master anggota

Pilih salah satu:

**A. Input satu per satu**
1. Menu **Anggota & Undangan** → **Tambah Anggota**.
2. Isi NBA 15 digit (boleh dengan titik, sistem menormalkan), nama, WhatsApp, tanggal jadi anggota, simpanan wajib, kolektibilitas, status pinjaman.
3. Simpan. NBA tampil sebagai `020006.000.010.339`.

**B. Impor massal**
1. Klik **Impor XLSX** → unduh **template** bila belum punya.
2. Kolom NBA harus berformat Teks di Excel agar nol awal tidak hilang.
3. Unggah. Baris yang salah dilaporkan per baris; baris valid tetap masuk.
4. NBA yang sama pada impor berikutnya **memperbarui**, tidak menggandakan.

### 4.3 Memeriksa dan mengoreksi anggota

1. Cari dengan nama atau NBA (dengan/tanpa titik).
2. Klik **Kelola Anggota**. Yang muncul lebih dulu hanya informasi:
   - status keanggotaan,
   - simpanan wajib per 31 Desember,
   - minimum proporsional,
   - hasil kelayakan otomatis,
   - status verifikasi rapat.
3. Klik **Edit Data** (hanya Admin) untuk membuka form. **Batal** menutup form tanpa menyimpan.
4. Setiap perubahan master dicatat di `log_audit` dan tampil di **Aktivitas Terakhir** dashboard.

> ✅ Hasil kelayakan dan status verifikasi pada modal berasal dari tabel `peserta`. Setelah **Siapkan Peserta**, angka pada modal adalah snapshot saat penyiapan; mengubah master anggota **tidak** mengubah hasil rapat yang sudah disiapkan.

### 4.4 Memantau

- **Dashboard** menampilkan jumlah anggota nyata, status rapat, dan 5 aktivitas terakhir.
- **Audit** sudah membaca `log_audit` sungguhan; riwayat pencarian SHU masih kosong sampai Fase 7c.
- **SHU** dikelola per tahun buku dan tidak bergantung pada Tahun Acuan rapat. **Doorprize D-044** akan memakai master hadiah per rapat dan pilihan hadiah pada halaman undian.

### 4.5 SHU

1. Masuk → **SHU**. Pilih tahun buku (isian angka), lalu **Tampilkan**.
2. Unduh **Template XLSX** berisi kolom `no_anggota`, `balas_jasa_saham`, `balas_jasa_pinjaman`.
3. Klik **Impor Data SHU**, pilih tahun buku dan berkasnya. Baris yang ditolak dilaporkan per baris beserta alasannya; baris valid tetap tersimpan.
4. Periksa total setahun di baris bawah tabel. Impor ulang memperbarui angka anggota yang sama tanpa membuat baris ganda.
5. Setelah data final, klik **Buka cek publik** pada tahun buku itu. Anggota bisa mencari SHU dengan NBA + CAPTCHA. Tombol **Tutup cek publik** menghentikan pencarian lagi.
6. Catatan: cek publik hanya bisa dibuka bila tahun buku itu sudah punya data, dan saklar ini tidak bergantung pada status rapat.

### 4.6 Doorprize

1. Pada **Manajemen Rapat → tab Doorprize**, tambah seluruh hadiah dengan nama, jumlah unit, dan keterangan opsional. Tidak ada urutan undian.
2. Setelah presensi dibuka dan peserta mulai hadir, buka menu **Doorprize** pada status Verifikasi atau Berlangsung. Draf, Selesai, dan Dibatalkan tidak dapat menjalankan undian.
3. Pilihan hanya memuat hadiah yang masih memiliki sisa unit. Klik **Undi** untuk memilih satu peserta hadir yang belum memiliki hasil doorprize.
4. Klik **Masih ada** bila peserta ada di ruangan: pemenang disimpan untuk hadiah terpilih dan sisa berkurang secara terhitung. Klik **Sudah pulang** untuk menggugurkan peserta tanpa mengurangi sisa. **Batal** tidak menyimpan.
5. Hadiah dengan sisa nol hilang dari pilihan aktif dan tampil di bagian **Habis** bersama pemenangnya. Satu peserta hanya boleh memperoleh satu hadiah.
6. Hadiah tetap dapat diperbarui, tetapi jumlah tidak boleh di bawah pemenang dan hadiah dengan hasil tidak boleh dihapus. Semua perubahan dan hasil tercatat di `log_audit`.

## 5. Alur Helpdesk

Helpdesk melayani anggota: mencari, memeriksa, memverifikasi, dan mengirim undangan. Helpdesk **tidak boleh** mengubah master anggota maupun parameter rapat.

1. Masuk → **Anggota & Undangan**.
2. Cari anggota lewat nama atau NBA.
3. Klik **Kelola Anggota** dan periksa kelima baris informasi.
4. Tentukan sikap:
   - **Verifikasi** → peserta berstatus Verified.
   - **Tolak** → peserta Rejected.
   - **Kirim WhatsApp** / **Cetak Undangan** → hanya aktif setelah Verified.
5. Bila hasil otomatis **Tidak Layak** tetapi petugas tetap memberi undangan, isi **alasan** terlebih dahulu. Tanpa alasan, sistem menolak.
6. Setiap tombol menampilkan status proses: "Memverifikasi...", "Menolak...", "Mengirim...", "Menyiapkan...".

> ✅ Tombol **Verifikasi**, **Tolak**, **Kirim WhatsApp**, dan **Cetak Undangan** sudah aktif dan menyimpan ke `peserta`/`log_undangan`. Peserta harus sudah **Verified** sebelum undangan bisa diproses. Bila rapat sudah Berlangsung, keputusan tidak dapat diubah.
>
> ✅ **Kirim WhatsApp** memakai Fonnte dari **Pengaturan → Integrasi** (v2.2, selesai 3 November 2026). Bila Fonnte belum aktif atau token belum diisi, pengiriman tetap tercatat sebagai `gagal` dengan alasannya — tidak ada pesan yang dikirim dan status Verified tidak berubah. Status `diterima_api` berarti vendor menerima permintaan, bukan bukti pesan sudah dibaca anggota.

## 6. Alur Scanner

Scanner memegang kas dan melakukan presensi. Kas terikat pada **akun Scanner**, bukan pada meja.

1. Masuk → **Kas Saya**.
2. Periksa kas awal yang dialokasikan Keuangan. Kas awal hanya diberikan **sekali** per rapat; tidak ada top-up atau transfer antar Scanner. Setelah uang dihitung, tekan **Saya sudah menerima kas**; sebelum konfirmasi itu pemindaian ditolak (D-026).
3. Admin membuka presensi lebih dulu (status rapat tetap **Verifikasi**). Sebelum dibuka, kolom token menolak pemindaian.
4. Pindai QR undangan peserta dengan kamera (**Nyalakan kamera**, menyala otomatis bila izin sudah pernah diberikan), atau tempel token secara manual bila kamera tidak tersedia.
5. Ambil foto peserta dari kamera perangkat dan minta peserta menggambar tanda tangan di layar. Keduanya wajib; bila salah satu kosong atau tidak valid, presensi tidak dicatat. Bukti disimpan privat dan tidak pernah dihapus.
6. **Mode Presensi 1x:** sistem memotong transport dari kas Scanner yang sedang login dan mencatat pembayaran. Nominal dihitung server dari jam datang terhadap jam mulai acara; Scanner tidak dapat mengubahnya.
7. Serahkan uang dan buka **resi** (kode resi, nama, nominal, waktu). Resi bisa dibuka lagi dari tabel pembayaran di **Kas Saya** dan dicetak dengan tombol **Cetak resi**. Hanya Scanner yang menyerahkan, Admin, dan Keuangan yang dapat membukanya.
8. Bila sisa kas di bawah ambang peringatan, muncul banner **Kas menipis** beserta perkiraan jumlah peserta yang masih bisa dilayani. Peringatan ini tidak memblokir.
9. Bila kas tidak cukup, presensi yang memerlukan pembayaran **ditolak**. Arahkan anggota ke Scanner lain.
10. Bila transport menjadi **Rp0 (hangus)**, presensi tetap tercatat, tidak ada uang diserahkan, tidak ada resi, dan kas tidak berkurang.

**Bila rapat memakai Mode Presensi 2x (Datang-Pulang):** tab **1. Datang** mencatat kehadiran, foto, tanda tangan, dan mengunci nominal tanpa menyerahkan uang. Tab **2. Pulang & Bayar** hanya memerlukan token QR; uang diambil dari kas Scanner yang mencatat pulang (boleh Scanner berbeda) dan resi terbit saat itu. Peserta yang tidak pernah pulang tetap dihitung hadir untuk kuorum, tetapi transportnya hangus (D-034).

**Kebijakan koneksi:** aplikasi berjalan online. Halaman **Status Koneksi** menjelaskan bahwa pemindaian berhenti total saat koneksi putus; tidak ada antrean offline (D-032).

## 7. Alur Keuangan

Keuangan mengalokasikan dan mengawasi kas, lalu merekonsiliasi.

1. Masuk → **Kas Scanner**.
2. Pilih akun Scanner, isi nominal kas awal, klik **Serahkan & catat alokasi**. Satu alokasi per Scanner per rapat; alokasi kedua ditolak.
3. Pantau sisa kas tiap Scanner selama acara. Sisa = kas awal − pembayaran transport yang sudah tercatat.
4. Buka menu **Kuorum** bila perlu memantau angka agregat: hadir, populasi, target, persentase, dan status sah. Hanya angka, tanpa data pribadi; halaman memperbarui sendiri setiap 10 detik.
5. Bila nominal salah input, klik **Koreksi** pada baris kas: isi nominal benar + alasan. Nominal diganti (bukan ditambah) dan jejaknya masuk audit. Koreksi ditutup setelah kas dipakai membayar.
6. Setelah acara, buka **Rekonsiliasi**. Tabel menampilkan kas awal, terbayar, sisa seharusnya, dan sisa fisik. Klik **Tutup kas** pada baris Scanner, isi sisa uang fisik hasil hitung bersama Scanner.
7. Selisih bukan nol wajib diberi catatan alasan dan masuk `log_audit`; tidak ada persetujuan Admin dan tidak ada status tambahan (D-031). Kas yang sudah ditutup tidak dapat diubah atau ditutup ulang.

> ✅ Alokasi kas tersimpan di `kas_scanner` dan tercatat di audit. Admin dapat membuka halaman yang sama untuk memantau, tetapi form alokasi hanya muncul untuk Keuangan.

## 8. Skenario uji coba yang bisa dijalankan sekarang

Urutan ini memakai fitur yang benar-benar tersimpan.

| # | Sebagai | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 1 | Admin | Buka **Manajemen Rapat**, ubah nominal transport, klik Simpan | Pesan "Pengaturan rapat disimpan", nilai bertahan setelah reload |
| 2 | Admin | Ubah Tahun Acuan di Kriteria Kelayakan, simpan | Teks acuan saldo berubah menjadi 31/12 tahun baru |
| 3 | Admin | Klik **Mulai Verifikasi** | Badge status menjadi Verifikasi |
| 4 | Admin | Klik **Mulai Rapat** | Status Berlangsung, form parameter utama terkunci |
| 5 | Admin | Tambah anggota dengan NBA 14 digit | Ditolak dengan pesan NBA harus 15 digit |
| 6 | Admin | Tambah anggota NBA valid, lalu cari tanpa titik | Anggota ditemukan, NBA tampil bertitik |
| 7 | Admin | Impor XLSX berisi satu baris salah | Baris salah dilaporkan, baris benar tetap masuk |
| 8 | Admin | Edit anggota lewat **Kelola → Edit Data → Simpan** | Perubahan tersimpan dan muncul di Aktivitas Terakhir |
| 9 | Helpdesk | Buka **Anggota & Undangan**, klik Kelola | Hanya informasi, tanpa tombol Edit Data |
| 10 | Helpdesk | Coba akses `/rapat` langsung | Ditolak (403) |
| 11 | Admin | Isi 3 anggota, klik **Mulai Verifikasi**, lalu **Siapkan Peserta** | Muncul "Peserta disiapkan: 3 baru, 0 sudah ada"; badge Layak/Tidak Layak terisi otomatis |
| 12 | Admin | Klik **Siapkan Peserta** lagi | "0 baru, 3 sudah ada"; keputusan verifikasi yang sudah ada tetap utuh |
| 13 | Helpdesk | Kelola anggota → **Verifikasi** | Status menjadi Verified, tombol Kirim/Cetak aktif, waktu terbit token QR tampil |
| 14 | Helpdesk | Verifikasi anggota **Tidak Layak** tanpa mengisi alasan | Ditolak: "Peserta Tidak Layak hanya boleh Verified dengan alasan tertulis" |
| 15 | Helpdesk | **Cetak Undangan** pada peserta Verified, lalu klik **Buka & cetak undangan** | Kartu undangan berisi QR dan kode cadangan tampil; baris baru muncul di **Riwayat Undangan** |
| 16 | Helpdesk | **Kirim WhatsApp** pada peserta Verified | Tercatat di Riwayat Undangan: `Diterima API` bila Fonnte menerima, `Gagal` + alasan bila belum aktif atau nomor HP kosong |
| 17 | Scanner | Buka **Kas Saya** | Kas awal Rp0, kolom token menampilkan "Presensi belum dibuka Admin" |
| 18 | Keuangan | Buka **Kas Scanner**, pilih Scanner, isi kas awal, klik **Serahkan & catat alokasi** | Muncul "Kas awal … dicatat dan diaudit", baris masuk tabel Posisi Kas |
| 19 | Keuangan | Alokasikan lagi ke Scanner yang sama | Ditolak: kas sudah dialokasikan, top-up tidak diizinkan |
| 20 | Keuangan | Klik **Koreksi** pada baris kas, isi nominal benar + alasan | Nominal berubah, alasan & nominal lama tercatat di Audit |
| 21 | Keuangan | Alokasikan ke Scanner kedua | Baris baru, total kas awal bertambah |
| 22 | Scanner | Buka **Kas Saya** setelah dialokasikan | Hanya kas miliknya tampil, Scanner lain tidak terlihat |
| 23 | Admin | Buka **Kas Scanner** | Tabel terlihat, form alokasi tidak muncul (hanya pemantauan) |
| 24 | Keuangan | Buka **Rekonsiliasi** | Daftar kas per Scanner dengan sisa seharusnya dan tombol **Tutup kas**; selisih bukan nol ditolak bila catatan kosong |
| 25 | Admin | Klik **Buka Presensi** saat rapat Verifikasi | Status tetap Verifikasi, badge "Presensi dibuka" muncul, audit `buka_presensi` tercatat |
| 26 | Admin | Klik **Buka Presensi** lagi | Ditolak: presensi hanya dapat dibuka sekali |
| 27 | Scanner | Masukkan token QR peserta Verified | Presensi tercatat, sisa kas berkurang sesuai nominal transport |
| 28 | Scanner | Masukkan token yang sama lagi | Ditolak: peserta sudah tercatat presensi |
| 29 | Scanner | Anggota Nonaktif, peserta belum Verified, atau token rapat lain | Ditolak dengan pesan masing-masing |
| 30 | Scanner | Kas tidak cukup untuk nominal transport | Ditolak, arahkan anggota ke Scanner lain |
| 31 | Scanner | Transport hangus (Rp0) | Presensi tetap tercatat, tidak ada pembayaran, kas tidak berkurang |
| 32 | Scanner | Scan lengkap dengan foto + tanda tangan | Presensi tercatat, resi terbit, tautan **Buka resi** tampil |
| 33 | Scanner | Buka tautan resi lalu klik **Cetak resi** | Resi tampil dan tercetak tanpa menu samping |
| 34 | Scanner lain / Helpdesk | Buka tautan resi milik Scanner lain | 403 |
| 35 | Admin / Keuangan | Buka **Presensi & Bukti**, klik **Foto** atau **Tanda tangan** | Bukti tampil dari penyimpanan privat, tanpa URL publik |
| 36 | Helpdesk / Scanner | Buka halaman **Presensi & Bukti** atau tautan bukti | 403 |
| 37 | Scanner | Pindai QR undangan dengan kamera | Token terisi otomatis, kamera berhenti, presensi berjalan seperti biasa |
| 38 | Scanner | Kamera tidak tersedia atau izin ditolak | Petunjuk muncul, token tetap bisa diisi manual |
| 39 | Scanner | Sisa kas di bawah ambang peringatan | Banner **Kas menipis** muncul; scan tetap boleh sampai kas habis |

## 9. Batas yang harus diketahui saat menguji

| Hal | Kondisi | Menunggu |
|---|---|---|
| Presensi & pembayaran transport | ✅ presensi tercatat, pembayaran memotong kas Scanner | — |
| Alokasi kas per Scanner | ✅ satu alokasi per Scanner per rapat, tercatat & diaudit | — |
| Pembayaran transport & penutupan kas | OK pembayaran tercatat; tutup kas per Scanner dengan sisa fisik dan selisih | - |
| Rekonsiliasi kas | OK halaman berisi kas nyata dan form Tutup kas; selisih beralasan + audit (D-031) | - |
| Konfirmasi penerimaan kas & ganti Scanner | OK konfirmasi terima kas (D-026) aktif; ganti Scanner = alokasi baru, saldo tidak dipindah | - |
| Verifikasi tersimpan per rapat | ✅ keputusan, snapshot, dan token QR tersimpan di `peserta` | — |
| QR undangan asli | ✅ QR dicetak dari token rapat (undangan.cetak) dan dibaca kamera atau token manual | — |
| Kirim WhatsApp sungguhan | ✅ lewat Fonnte dari Integrasi v2.2; status maksimal `diterima_api` | — |
| SHU Admin | ✅ input manual, koreksi beralasan, impor XLSX per tahun buku, total setahun, audit sebelum/sesudah | — |
| Cek SHU publik | OK halaman `/cek-shu`: NBA + Turnstile, rate limit 10/menit, log 14 hari, purge harian | - |
| Doorprize | Revisi D-044 direncanakan: master hadiah, pilih hadiah saat undi, stok terhitung, satu peserta satu hadiah | Implementasi & UAT |
| Laporan per rapat | OK `/laporan` + ekspor XLSX; bagian dibatasi role (Admin semua, Keuangan kas & transport, Helpdesk terbatas) | - |
| Audit lengkap | OK daftar `log_audit` nyata + riwayat cek SHU 14 hari; filter belum ada | belum diputuskan |
| Mode Presensi 2x | OK tab Datang/Pulang di halaman pindai; datang mengunci nominal, pulang membayar dari kas Scanner pencatat pulang (D-034) | - |
| Offline / sinkron | tidak dikerjakan: pemindaian berhenti total saat koneksi putus (D-032) | - |
| Transport Rp0 (hangus) | ✅ presensi tetap tercatat, tanpa pembayaran, kas tidak berkurang | — |
| Ambang kas menipis | ✅ banner di Kas Saya & badge di Kas Scanner, tidak memblokir (D-025) | — |
| Status saat presensi dibuka sebelum acara | ✅ status tetap Verifikasi + penanda `presensi_dibuka_at` | — |
| Konfirmasi kas awal & ganti Scanner | OK konfirmasi terima kas aktif; ganti Scanner memakai alokasi baru | - |
| Batas minimum proporsional | OK: tgl_ja kosong = Tidak Layak; masuk setelah tahun buku = minimum 0 (D-033) | - |

## 10. Mengosongkan ulang data

Untuk memulai pengujian dari nol (akun petugas dibuat ulang, data bisnis dikosongkan):

**Lewat aplikasi (disarankan):** Admin membuka **Pengaturan Aplikasi**, menekan **Hapus semua data**, dan mengetik frasa konfirmasi `HAPUS SEMUA DATA`. Sistem menghapus seluruh data bisnis, akun petugas, log audit, dan berkas foto/tanda tangan, lalu membuat ulang akun Admin (sandi `password`); akun petugas lain dibuat lewat menu **Pengguna** dalam satu transaksi. Admin otomatis keluar dan diarahkan ke halaman masuk. Skema database tidak dijatuhkan.

**Lewat terminal** (bila aplikasi tidak dapat dibuka):

```
php artisan migrate:fresh --seed
```

Perintah ini menghapus **semua** tabel lalu membuat ulang. Jangan dijalankan bila sudah ada data yang ingin disimpan.

Bila hanya ingin mengosongkan data bisnis tanpa menyentuh akun:

```
php artisan migrate:fresh
php artisan db:seed --class=UserSeeder
```

Data contoh anggota dan rapat lama masih tersedia bila diperlukan:

```
php artisan db:seed --class=AnggotaSeeder
php artisan db:seed --class=RapatSeeder
```

## 11. Lokasi data

| Hal | Nilai |
|---|---|
| Database | MySQL `erat_rukun` |
| Host / port | `127.0.0.1:3306` |
| User | `root` tanpa kata sandi (Laragon lokal) |
| Tabel aktif | `users`, `anggota`, `log_audit`, `rapat`, `rapat_transport_tier`, `peserta`, `log_undangan`, `kas_scanner`, `presensi`, `transport_pembayaran` |
| Tabel sesi & antrean | `sessions`, `cache`, `jobs` (bawaan Laravel) |

Database pengujian otomatis tetap SQLite di memori agar cepat; itu tidak memengaruhi data MySQL Anda.
