# eRAT — Panduan Proyek

> **Status:** Dokumentasi aktif, diperbarui 7 November 2026. Keputusan FINAL berada di `docs/01-business/DECISION_LOG.md`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

eRAT adalah sistem manajemen seluruh rapat anggota Koperasi Kredit Rukun (web; Laravel 13, PHP 8.3, MySQL, Blade, Livewire, Tabler; pengujian PHPUnit). Aplikasi mandiri, tidak terhubung langsung dengan SIKOPDIT Online. Data diimpor dari file keluaran sisi client.

## Mulai di sini
1. Baca `AGENTS.md`. 2. Baca `docs/01-business/DECISION_LOG.md` dan `BUSINESS_RULES.md`. 3. Baca `docs/01-business/PRD.md`. 4. Untuk modul spesifik, baca alur, izin, skema, arsitektur, testing, dan acceptance terkait.

## Peta folder
- `docs/01-business`: keputusan, aturan bisnis, dan kebutuhan.
- `docs/02-workflows`: alur pengguna, perizinan, SOP pelayanan, presensi, kas, serta kebijakan online-only.
- `docs/03-technical`: arsitektur, database, kamus data, UI, keamanan.
- `docs/04-development`: fase pengerjaan & matriks penelusuran.
- `docs/05-quality`: pengujian, penerimaan, dan uji lapangan.
- `docs/06-operations`: pemasangan, backup, pemulihan dan insiden.

## Prinsip utama
NBA 15 digit disimpan tanpa titik; kas melekat ke user Scanner (bukan meja); satu alokasi kas awal per Scanner per rapat, tanpa top-up; QR unik per peserta per rapat setelah Verified; anggota Nonaktif diblokir saat presensi; SHU dikelola Admin per Tahun Buku; kelayakan rapat memakai Tanggal Acuan Saldo; jam acara dan presensi terpisah. Helpdesk hanya dapat mengoreksi WhatsApp, email, jenis anggota, status keanggotaan, dan alamat dengan audit.

## Pekerjaan berikutnya
Fitur sampai D-052 telah diimplementasikan. Pekerjaan tersisa adalah UAT perangkat nyata, verifikasi visual, scheduler produksi, dan persetujuan privasi SHU oleh manusia.


**Perubahan v2.1:** riwayat pencarian SHU publik disimpan maksimal **14 hari**; data SHU dan audit perubahan oleh Admin tidak ikut dihapus. Lihat `docs/01-business/BUSINESS_RULES.md` dan `docs/03-technical/SECURITY_PRIVACY.md`.


## Perubahan v2.2 — Pengaturan Integrasi
Admin mengonfigurasi CAPTCHA dan Fonnte dari **Pengaturan Sistem → Integrasi**; rahasia layanan disimpan terenkripsi di database, tidak di `.env`. Baca `docs/03-technical/INTEGRATION_SETTINGS.md`. Pemakaian CAPTCHA tetap melindungi endpoint Cek SHU dan tidak membuktikan kepemilikan NBA.

## Menjalankan shell UI
Kerangka Laravel 13 + Blade + Livewire sudah ada di root proyek.

1. `composer install`
2. `php artisan migrate --seed`
3. `php artisan serve`

Akun awal setelah reset: username `admin`, kata sandi `password`. Admin membuat akun Helpdesk, Scanner, dan Keuangan melalui **Pengaturan → Pengguna**; ganti sandi awal sebelum produksi.

Aset Tabler disalin lokal ke `public/vendor/tabler` sehingga UI tidak bergantung CDN. Menu per role ada di `config/menu.php`. Mode Presensi 1x dan Mode Presensi 2x (Datang–Pulang), kas, laporan, SHU, papan kuorum publik, integrasi, dan manajemen pengguna telah tersedia. Pemindaian tetap online-only sesuai D-032.
