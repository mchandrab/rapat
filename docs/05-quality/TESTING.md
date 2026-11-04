# TESTING — Strategi dan Kasus Uji

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Standar
PHPUnit; SQLite memory untuk unit/feature; migration dan query sudah dijalankan pada MySQL `erat_rukun` (30 Oktober 2026), validasi beban penuh tetap pada UAT. Uji normal, batas, dan serangan/pelanggaran izin. Test service sebelum implementasi; regression test untuk setiap bug.

**Implementasi 4 November 2026:** 269 tes hijau (1.101 asersi). T-INT di `tests/Feature/IntegrasiTest.php`, T-KUO di `tests/Feature/KuorumTest.php`, T-DP di `tests/Feature/DoorprizeTest.php`, T-KAS/T-REKON di `tests/Feature/KasTest.php` dan `tests/Feature/TutupKasTest.php`, T-SHU/T-SHU-LOG di `tests/Feature/ShuTest.php` dan `tests/Feature/CekShuTest.php`, T-USER di `tests/Feature/PenggunaTest.php`, T-LAP/T-OPS di `tests/Feature/LaporanTest.php` dan `tests/Feature/OpsTest.php`, T-OFF di `tests/Feature/OfflineTest.php`, serta autentikasi username, otorisasi, ringkasan akses role, dan navigasi sidebar/pengaturan di `tests/Feature/RoleAccessTest.php`. Migration username juga berhasil diterapkan pada MySQL lokal tanpa menghapus akun lama.

## Matriks kasus prioritas
| ID | Skenario | Hasil diharapkan |
|---|---|---|
| T-NBA-01 | Simpan `020006000010339` | Tersimpan 15 digit, UI `020006.000.010.339` |
| T-NBA-02 | Impor NBA sama di tahun berbeda | Update master, tidak menambah anggota |
| T-NBA-03 | Impor salah format/duplikat | Error per baris; tidak merusak data valid |
| T-NBA-04 | Cari dengan/ tanpa titik | Anggota yang sama ditemukan |
| T-KEL-01 | Masuk November, per 31 Des wajib Rp25rb/bulan, saldo Rp50rb | Komponen simpanan memenuhi |
| T-KEL-02 | Mode A saldo cukup, kolektibilitas lebih tinggi dari batas | Tidak Layak |
| T-KEL-03 | Mode B saldo cukup dan pinjaman lancar | Layak |
| T-KEL-04 | Status anggota Nonaktif tetapi parameter finansial memenuhi | Hasil penilaian tidak memasukkan status Aktif; scan tetap diblokir |
| T-KEL-05 | Saldo master diimpor ulang | Snapshot verifikasi lama tetap |
| T-RPT-01 | Tier potongan bolong (1–30 lalu 32–60) | Ditolak saat simpan |
| T-RPT-02 | Tier pertama mulai menit 2 | Ditolak saat simpan |
| T-VER-01 | Pending → Verified | Token unik untuk rapat terbit sekali |
| T-VER-02 | Tidak Layak → Verified tanpa alasan | Ditolak |
| T-VER-03 | Token anggota sama rapat lain | Berbeda dan tidak berlaku silang |
| T-UND-01 | Cetak lalu kirim WA | Dua log petugas/metode terpisah |
| T-UND-02 | Cetak ulang | QR tetap sama untuk rapat tersebut |
| T-UND-03 | WA gagal | Status gagal, jangan menyatakan diterima |
| T-SCAN-01 | QR valid Verified anggota Aktif | Terima sesuai mode & kas |
| T-SCAN-02 | Anggota Nonaktif setelah undangan | Blokir scan |
| T-SCAN-03 | Scan/pembayaran berulang | Tidak menggandakan presensi maupun kas keluar |
| T-SCAN-04 | Jam mulai 08:00 toleransi 60, datang 09:00 & 09:01 | 09:00 tanpa potongan; 09:01 tier mulai menit ke-1 |
| T-SCAN-05 | Jam presensi 07:00, acara 08:00 | Ambang keterlambatan tetap berdasarkan 08:00 |
| T-SCAN-06 | Transport menjadi Rp0 karena hangus | Presensi tetap tercatat, tanpa baris pembayaran, kas tidak berkurang |
| T-SCAN-07 | Presensi sebelum Admin membuka presensi | Ditolak dengan pesan presensi belum dibuka |
| T-SCAN-08 | Scan tanpa foto atau tanda tangan | Ditolak; tidak ada presensi dan tidak ada berkas tersimpan |
| T-SCAN-09 | Tanda tangan bukan PNG asli | Ditolak; berkas foto yang terlanjur ditulis dihapus kembali |
| T-SCAN-10 | Scan lengkap dengan foto + tanda tangan | Kedua berkas ada di disk privat; path tidak ikut serialisasi model |
| T-PULANG-01 | Mode 2x: scan datang | Presensi tercatat, nominal terkunci, `pulang_at` dan `kode_resi` kosong, tidak ada baris `transport_pembayaran`, kas tidak dipakai |
| T-PULANG-02 | Mode 2x: scan datang dua kali | Ditolak dengan pesan mengarahkan ke tab Pulang; presensi tetap satu |
| T-PULANG-03 | Mode 2x: pulang oleh Scanner berbeda | Pembayaran memotong kas Scanner pencatat pulang, resi terbit, audit `pulang` |
| T-PULANG-04 | Mode 2x: pulang dua kali | Penolakan kedua; pembayaran tetap satu |
| T-PULANG-05 | Mode 2x: pulang sebelum datang | Ditolak, tidak ada pembayaran |
| T-PULANG-06 | Mode 2x: kas Scanner pulang tidak cukup | `pulang_at` tetap kosong dan tidak ada pembayaran |
| T-PULANG-07 | Mode 1x: pindai pulang | Ditolak; Mode Presensi 1x tidak punya presensi pulang |
| T-PULANG-08 | Mode 2x: peserta belum pulang | Kuorum tetap menghitungnya hadir |
| T-PULANG-09 | Mode 2x: komponen Livewire | Tab Datang/Pulang tampil; simpan mode pulang membayar dan mengisi `pulang_at` |
| T-RESI-01 | Scanner pembayar membuka resi | Tampil kode resi, nama, NBA, nominal, waktu, dan tombol cetak |
| T-RESI-02 | Admin dan Keuangan membuka resi | Boleh (audit kas & transport) |
| T-RESI-03 | Scanner lain atau Helpdesk membuka resi | 403 |
| T-RESI-04 | Tamu membuka tautan resi | Diarahkan ke login |
| T-RESI-05 | Kode resi tidak dikenal | 404, bukan halaman kosong |
| T-BUKTI-01 | Admin dan Keuangan membuka foto & tanda tangan | Berkas tersaji dari disk privat, isi sesuai |
| T-BUKTI-02 | Helpdesk atau Scanner membuka bukti | 403 |
| T-BUKTI-03 | Tamu membuka bukti | Diarahkan ke login |
| T-BUKTI-04 | Jenis bukti tidak dikenal | 404 |
| T-BUKTI-05 | Berkas bukti hilang dari disk | 404, bukan error server |
| T-BUKTI-06 | Halaman daftar presensi | Nama, nominal, dan tautan bukti tampil; Helpdesk 403 |
| T-QR-01 | Admin dan Helpdesk membuka undangan peserta Verified | Halaman berisi nama, NBA, QR, dan kode cadangan |
| T-QR-02 | Scanner atau Keuangan membuka undangan | 403 |
| T-QR-03 | Peserta belum Verified dibuka undangannya | 404, tidak ada QR |
| T-QR-04 | Dua peserta berbeda | QR berbeda, token masing-masing |
| T-QR-05 | Halaman pindai | Kontrol kamera dan kolom token manual sama-sama ada |
| T-KAS-09 | Ambang peringatan kosong | Dipakai 5 × nominal dasar transport |
| T-KAS-10 | Sisa kas di bawah ambang | Banner "Kas menipis" di Kas Saya dan badge di Kas Scanner; scan tetap boleh |
| T-KAS-11 | Alokasi baru sebelum dikonfirmasi | Status `menunggu`, `diterima_pada` kosong; scan ditolak dengan pesan konfirmasi terima kas |
| T-KAS-12 | Scanner menekan konfirmasi terima | Status jadi `aktif`, `diterima_pada` terisi, audit `terima_kas`; konfirmasi kedua dan konfirmasi kas Scanner lain ditolak |
| T-REKON-01 | Tutup kas tanpa selisih | Status `ditutup`, `sisa_fisik` tersimpan, `ditutup_oleh`/`ditutup_pada` terisi, audit `tutup_kas` |
| T-REKON-02 | Selisih tanpa catatan | Ditolak; kas tetap terbuka |
| T-REKON-03 | Selisih dengan catatan | Tersimpan beserta `catatan_selisih`, audit memuat `selisih` negatif dan alasannya |
| T-REKON-04 | Tutup dua kali / scan setelah ditutup | Penutupan kedua ditolak; scan setelah kas ditutup ditolak |
| T-REKON-05 | Halaman Rekonsiliasi | Keuangan 200 (kas, sisa seharusnya, tombol Tutup kas); Admin/Helpdesk/Scanner 403; POST tutup oleh Scanner 403 |
| T-LAP-01 | Halaman Laporan sebagai Admin | Semua bagian tampil (kuorum, peserta, undangan, presensi, kas, SHU, doorprize) dengan angka dari tabel nyata |
| T-LAP-02 | Halaman Laporan sebagai Keuangan | Hanya presensi, kas, dan kuorum; SHU, peserta, undangan, dan doorprize tidak dikirim |
| T-LAP-03 | Halaman Laporan sebagai Helpdesk | Hanya peserta dan undangan; kas, presensi, SHU, dan doorprize tidak dikirim |
| T-LAP-04 | Scanner membuka Laporan atau ekspornya | 403 |
| T-LAP-05 | Pemilih rapat `?rapat=` | Rapat lama menampilkan datanya sendiri, bukan data rapat terbaru |
| T-LAP-06 | Ekspor XLSX Admin | Berkas `laporan-rat-<tanggal>.xlsx` berisi semua bagian dan nominal yang sama dengan halaman |
| T-LAP-07 | Ekspor XLSX Keuangan | Berkas hanya memuat bagian yang boleh dibaca Keuangan |
| T-OPS-01 | `erat:backup` pada koneksi non-MySQL | Ditolak dengan pesan jelas, tidak menulis berkas |
| T-OPS-02 | Retensi cadangan | Hanya berkas `erat-`/`media-` lebih tua dari batas yang dihapus; berkas lain tetap |
| T-OPS-03 | `erat:benchmark` | Mengukur kuorum dan laporan; gagal bila belum ada rapat atau kuorum melewati 10 detik |
| T-OPS-04 | Jadwal cadangan | `schedule:list` memuat `erat:backup` harian bersama `cek-shu:purge` |
| T-OFF-01 | Halaman pindai saat offline | Peringatan merah **Koneksi terputus** ada, tombol Catat Presensi dinonaktifkan lewat `navigator.onLine`, teks merujuk D-032 |
| T-OFF-02 | Halaman Status Koneksi | Menjelaskan aplikasi online saja, antrean offline **Tidak dipakai**, tidak lagi menyebut O-002 |
| T-OFF-03 | Role lain membuka Status Koneksi | Admin, Helpdesk, dan Keuangan mendapat 403 |
| T-INT-01 | Halaman Integrasi | Admin 200; Helpdesk/Scanner/Keuangan 403; tamu diarahkan ke login |
| T-INT-02 | Simpan secret CAPTCHA | Tersimpan sebagai ciphertext, tidak tampil lagi di halaman, versi naik |
| T-INT-03 | Simpan dengan secret kosong | Secret lama dipertahankan, site key berubah, versi tetap |
| T-INT-04 | Nonaktifkan | Aktif jadi false, secret terhapus, versi naik |
| T-INT-05 | Uji CAPTCHA | Memanggil siteverify dengan secret asli; lolos → `diterima_api`, ditolak → `gagal` dan error; tanpa secret tidak memanggil vendor |
| T-INT-06 | Simpan dan uji Fonnte | Simpan tidak mengirim apa pun; uji wajib konfirmasi, sukses → `diterima_api`, ditolak vendor → `gagal` |
| T-INT-07 | Kirim undangan WhatsApp | Fonnte aktif → `diterima_api` + id pesan; belum aktif atau tanpa nomor HP → `gagal` tanpa panggilan HTTP; status Verified tidak berubah |
| T-KUO-01 | Populasi 101 anggota biasa, persen 50, tambahan 1 | Target 51; populasi 100 juga 51 |
| T-KUO-02 | Campuran biasa, luar biasa, dan biasa nonaktif | Hanya biasa aktif masuk populasi dan pembilang |
| T-KUO-03 | Presensi anggota luar biasa | Hadir kuorum tidak bertambah; dihitung terpisah |
| T-KUO-04 | Uji batas target | Satu di bawah target belum sah; presensi yang mencapai target langsung sah |
| T-KUO-05 | Basis `terverifikasi` | Hanya peserta Verified yang jadi populasi |
| T-KUO-06 | Presensi dari rapat lain | Tidak menambah kuorum rapat ini |
| T-KUO-07 | Halaman `/kuorum` | Admin dan Keuangan 200; Helpdesk dan Scanner 403 |
| T-KUO-08 | Publik membuka `/papan-kuorum` tanpa login | 200; angka hadir, target, populasi, persentase, dan status sama dengan `Kuorum`; nama/NBA/nominal/baris individu tidak tampil |
| T-KUO-09 | `/papan-kuorum` saat belum ada rapat | Menampilkan keadaan kosong tanpa error |
| T-KUO-10 | Dashboard Scanner dan Admin | Ringkasan kuorum tampil pada Scanner dan tidak disisipkan ke bagian Admin |
| T-SHU-01 | Impor SHU satu anggota | Baris tersimpan dengan komponen dan total = saham + pinjaman; audit `buat` |
| T-SHU-02 | Impor ulang tahun buku sama | Baris diperbarui, tidak duplikat, audit `ubah` |
| T-SHU-03 | Impor tahun buku berbeda | Dua baris terpisah, tahun lama tidak tertimpa |
| T-SHU-04 | NBA belum ada di master anggota | Baris itu ditolak, baris lain tetap masuk |
| T-SHU-05 | Nominal bukan angka | Baris ditolak; sel kosong dianggap 0 |
| T-SHU-06 | Judul kolom kurang / berkas kosong | Ditolak sebelum menyimpan |
| T-SHU-07 | Hak akses SHU | Admin 200; Helpdesk, Keuangan, Scanner 403; tamu diarahkan ke login |
| T-SHU-08 | Buka cek publik | `shu_publik.aktif` benar, pembuka dan waktu tercatat, audit `buka` |
| T-SHU-09 | Buka tanpa data / tutup saat belum dibuka | Ditolak dengan pesan, tidak menulis baris |
| T-SHU-11 | Admin input SHU manual memakai NBA dengan/tanpa titik | Baris anggota–tahun buku dibuat dan audit `buat` beralasan `input manual` |
| T-SHU-12 | Input manual NBA tidak dikenal atau kombinasi ganda | Ditolak; tidak ada duplikat SHU |
| T-SHU-13 | Admin mengoreksi dua komponen SHU dengan alasan | Nilai berubah; audit menyimpan nilai sebelum/sesudah dan alasan |
| T-SHU-14 | Koreksi tanpa alasan cukup atau tanpa perubahan nominal | Ditolak; tidak ada audit koreksi |
| T-SHU-15 | Role selain Admin memanggil input/koreksi langsung | 403; data tidak berubah |
| T-SHU-10 | Tutup cek publik & unduh template | `aktif` jadi false + `ditutup_at`, audit `tutup`; template `.xlsx` terunduh |
| T-DP-01 | Kandidat undian | Hanya peserta yang punya presensi pada rapat itu |
| T-DP-02 | Peserta sudah menang atau gugur | Tidak pernah muncul lagi di undian berikutnya |
| T-DP-03 | Konfirmasi "masih ada" | Baris `menang`, urutan 1 lalu 2, dicatat Admin, masuk audit |
| T-DP-04 | Konfirmasi "sudah pulang" | Baris `gugur` tanpa urutan, jumlah menang tidak bertambah |
| T-DP-05 | Jumlah pemenang dari parameter rapat | Sisa kursi = target − menang; target 1 langsung penuh |
| T-DP-06 | Hak akses doorprize | Admin 200; Helpdesk, Keuangan, Scanner 403; tamu diarahkan ke login |
| T-DP-07 | Komponen undian | Pop-up muncul, batal tidak menyimpan, undi ditolak saat target penuh atau tanpa peserta hadir |
| T-DP-08 | Parameter jumlah pemenang | Tersimpan 25; nilai 0 dan 101 ditolak |
| T-KAS-01 | Satu Scanner satu RAT diberi kas dua kali | Pemberian kedua ditolak |
| T-KAS-02 | Kas 1jt, 10× bayar 100rb | Saldo 0 dan transaksi berikutnya ditolak |
| T-KAS-03 | Kas 75rb dan nominal 100rb | Presensi yang memerlukan bayar ditolak; tidak ada utang transport |
| T-KAS-04 | Dua request bersamaan atas kas sama | Lock/transaction cegah saldo minus |
| T-KAS-05 | Penutupan kas | Kas awal − terbayar = sisa seharusnya; bandingkan sisa fisik |
| T-KAS-06 | Scanner A mencoba memakai kas B | 403/ditolak |
| T-KAS-07 | Offline tanpa otorisasi | Tidak ada pembayaran tunai yang ditandai berhasil |
| T-KAS-08 | Konfirmasi terima kas | Hanya pemilik kas; sekali per alokasi; tercatat `terima_kas` |
| T-SHU-01 | Keuangan mencoba impor SHU | 403 |
| T-SHU-02 | Admin mengelola SHU | Berhasil dan diaudit |
| T-RAPAT-01 | Rapat Berlangsung ubah parameter | Ditolak meski request dimanipulasi |
| T-RAPAT-02 | Tahun buku diubah | `tanggal_acuan_saldo` menjadi 31 Desember tahun buku baru |
| T-RAPAT-03 | Transisi status tidak berurutan atau tier tumpang tindih | Ditolak; status dan tier tersimpan tidak berubah |
| T-RESET-01 | Helpdesk/Scanner/Keuangan membuka atau memanggil reset | 403; data tidak berubah |
| T-RESET-02 | Frasa konfirmasi salah (huruf kecil) | Ditolak dengan pesan; rapat, anggota, dan audit tetap ada |
| T-RESET-03 | Admin mengirim `HAPUS SEMUA DATA` | Semua tabel bisnis dan audit kosong, hanya akun Admin dibuat ulang, berkas `presensi` terhapus, Admin keluar ke halaman masuk |
| T-RESET-04 | Buka halaman Pengaturan Aplikasi | Jumlah data yang akan dihapus tampil per jenis |
| T-USER-01 | Helpdesk/Scanner/Keuangan membuka menu Pengguna | 403; tidak ada akun berubah |
| T-USER-02 | Admin menambah akun Scanner | Akun tampil di daftar, sandi tersimpan ter-hash, baris audit `buat` muncul tanpa sandi |
| T-USER-03 | Admin mengosongkan kolom sandi saat mengubah akun | Sandi lama tetap berlaku; nama/role berubah |
| T-USER-04 | Admin mencoba menghapus atau menurunkan role akun sendiri | Ditolak dengan pesan; akun tetap Admin |
| T-USER-05 | Admin menghapus akun petugas lain | Akun hilang dari daftar dan tidak bisa login; audit `hapus` tercatat |
| T-USER-06 | Login dengan username huruf besar/kecil | Username dinormalisasi ke huruf kecil dan login berhasil; login memakai email ditolak |
| T-USER-07 | Admin membuat username ganda atau karakter tidak valid | Ditolak; tidak ada akun atau audit baru |
| T-PRES-ROLE-01 | Helpdesk mencoba membuka/mengirim aksi presensi | 403; tidak ada presensi, pembayaran, atau audit presensi terbentuk (D-036) |
| T-ROLE-01 | Helpdesk, Scanner, dan Keuangan membuka Dashboard | Masing-masing hanya melihat ringkasan rapat sesuai tugas; tidak ada form atau aksi perubahan parameter |
| T-ROLE-02 | Helpdesk mengirim perubahan master / Keuangan membuka master anggota | 403; data anggota tidak berubah |
| T-NAV-01 | Sidebar Admin dirender | Span Utama, Operasional, Pelaporan, Sistem berurutan; hanya ada satu tautan Pengaturan tanpa submenu vertikal |
| T-NAV-02 | Halaman Pengguna, Integrasi, dan Aplikasi | Ketiganya memuat navbar horizontal `nav-overflow` mode pills yang sama dan menandai route aktif; Scanner/Keuangan tidak melihat Pengaturan |
| T-SEC-01 | Helpdesk buka foto/TTE | 403 |

## Kasus yang belum dapat disahkan
Mode Presensi 2x diuji di T-PULANG-01..09 (`tests/Feature/PresensiPulangTest.php`, D-034). Offline kini diuji di T-OFF-01..03 (D-032); ambang saldo (D-025) dan konfirmasi penerimaan kas (D-026) sudah masuk test suite di `KasTest`.


### Pengujian retensi Cek SHU publik
| T-SHU-LOG-01 | Pencarian mencapai endpoint | Log dicatat (NBA, tahun buku, IP/sumber, waktu, hasil), tanpa nominal SHU |
| T-SHU-LOG-02 | Log berusia >14 hari | Command purge menghapus log tersebut |
| T-SHU-LOG-03 | Log belum 14 hari | Tetap tersimpan, dengan boundary test cutoff |
| T-SHU-LOG-04 | Command purge berulang / log banyak | Idempoten, proses bertahap dan tidak membebani database |
| T-SHU-LOG-05 | Purge dijalankan | Record `shu`, audit koreksi Admin, dan data per tahun buku tetap utuh; akses log non-Admin ditolak |
| T-SHU-LOG-06 | CAPTCHA salah / rate limit | Request ditolak sebelum query SHU dan dicatat sesuai kebijakan pencatatan |


## TEST-INT — Konfigurasi Integrasi (v2.2)
- TEST-INT-01: Admin menyimpan CAPTCHA site key + secret dan Fonnte token di halaman **Pengaturan → Integrasi**; perubahan langsung dipakai tanpa mengubah `.env`.
- TEST-INT-02: Helpdesk/Scanner/Keuangan serta akses tanpa login mendapat 403/redirect sesuai kebijakan pada seluruh route konfigurasi/tes.
- TEST-INT-03: DB tidak mengandung secret plaintext; endpoint detail tidak mengungkap secret; log/exception/audit tidak memuat token; edit dengan input kosong mempertahankan credential lama.
- TEST-INT-04: CAPTCHA mati/tidak lengkap/gagal jaringan => lookup SHU tidak melakukan query nominal, mengembalikan pesan aman; CAPTCHA benar diuji server-side (mock response). Retensi log tetap 14 hari.
- TEST-INT-05: Simpan Fonnte tidak mengirim apa pun. Uji kirim perlu nomor dan konfirmasi; mock provider dipanggil satu kali, status request diterima tidak otomatis ditulis delivered.
- TEST-INT-06: Rotasi credential menaikkan version, audit hanya metadata dan operasi berhasil atomik; failure mempertahankan versi terdahulu. Konfigurasi dibaca langsung dari database tanpa cache aplikasi.
