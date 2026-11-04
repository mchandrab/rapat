# eRAT — Panduan Proyek

> **Status:** Rancangan terintegrasi v2.2 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

eRAT adalah sistem manajemen Rapat Anggota Tahunan Koperasi Kredit Rukun (web; Laravel 13, PHP 8.3, MySQL, Blade, Livewire, Tabler; pengujian Pest). Aplikasi mandiri, tidak terhubung langsung dengan SIKOPDIT Online. Data diimpor dari file keluaran sisi client.

## Mulai di sini
1. Baca `AGENTS.md`. 2. Baca `docs/01-business/DECISION_LOG.md` dan `BUSINESS_RULES.md`. 3. Baca `docs/01-business/PRD.md`. 4. Untuk modul spesifik, baca alur, izin, skema, arsitektur, testing, dan acceptance terkait.

## Peta folder
- `docs/01-business`: keputusan, aturan bisnis, dan kebutuhan.
- `docs/02-workflows`: alur pengguna, perizinan, SOP pelayanan, presensi, kas, serta sinkronisasi offline.
- `docs/03-technical`: arsitektur, database, kamus data, UI, keamanan.
- `docs/04-development`: fase pengerjaan & matriks penelusuran.
- `docs/05-quality`: pengujian, penerimaan, dan uji lapangan.
- `docs/06-operations`: pemasangan, backup, pemulihan dan insiden.
- `docs/archive`: versi sumber sebelum rekonsiliasi; **bukan aturan yang digunakan untuk coding**.

## Prinsip utama
NBA 15 digit disimpan tanpa titik; kas melekat ke user Scanner (bukan meja); satu alokasi kas awal per Scanner per rapat, tanpa top-up; QR unik per peserta per rapat setelah Verified; anggota Nonaktif diblokir saat presensi; SHU dikelola Admin; rentang waktu RAT dan presensi terpisah.

## Hal terbuka
Mekanisme detail Mode Presensi 2x untuk menjamin pembayaran saat pulang, terutama jika hadir lewat Scanner berbeda; kebijakan operasional kasus transport Rp0; batas pasti peringatan saldo; skenario offline yang diperbolehkan; kapan status Berlangsung diaktifkan relatif terhadap pembukaan presensi. Semua ini harus diputuskan sebelum implementasi modul terkait.


**Perubahan v2.1:** riwayat pencarian SHU publik disimpan maksimal **14 hari**; data SHU dan audit perubahan oleh Admin tidak ikut dihapus. Lihat `docs/01-business/BUSINESS_RULES.md` dan `docs/03-technical/SECURITY_PRIVACY.md`.


## Perubahan v2.2 — Pengaturan Integrasi
Admin mengonfigurasi CAPTCHA dan Fonnte dari **Pengaturan Sistem → Integrasi**; rahasia layanan disimpan terenkripsi di database, tidak di `.env`. Baca `docs/03-technical/INTEGRATION_SETTINGS.md`. Pemakaian CAPTCHA tetap melindungi endpoint Cek SHU dan tidak membuktikan kepemilikan NBA.

## Menjalankan shell UI
Kerangka Laravel 13 + Blade + Livewire sudah ada di root proyek.

1. `composer install`
2. `php artisan migrate --seed`
3. `php artisan serve`

Akun demo (kata sandi `password`): `admin@erat.test`, `helpdesk@erat.test`, `scanner@erat.test`, `keuangan@erat.test`.

Aset Tabler disalin lokal ke `public/vendor/tabler` (core 1.7.1 dan icons-webfont 3.34.0), jadi UI tidak bergantung CDN. Menu per role ada di `config/menu.php`; data contoh ada di `app/Support/Demo.php`. Belum ada transaksi tersimpan dan belum ada halaman publik. Modul yang menunggu keputusan — Mode Presensi 2x, offline, transport Rp0, ambang kas — tampil nonaktif dengan catatan O-001 sampai O-004.
