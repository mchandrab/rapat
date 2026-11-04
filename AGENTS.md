# AGENTS — Instruksi AI Coding

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Prioritas wajib
1. Pertahankan stack Laravel 13 + PHP 8.3 + MySQL, Blade + Livewire + Tabler; kode Inggris dan UI Indonesia. Jangan membuat frontend terpisah, API publik baru, atau integrasi langsung SIKOPDIT.
2. Tepat empat role: Admin, Helpdesk, Scanner, Keuangan. Anggota bukan akun login. SHU hanya Admin.
3. Baca `docs/01-business/DECISION_LOG.md` dan `BUSINESS_RULES.md` sebelum coding. Arsip tidak boleh dianggap sumber aturan aktif.
4. Semua angka bisnis di konfigurasi per rapat, tidak hardcode. Kunci konfigurasi ketika rapat berlangsung.
5. Validasi dan otorisasi di server (policy, FormRequest), throttle login; foto dan tanda tangan privat. Log audit append-only.
6. NBA string tepat 15 digit di database (contoh `020006000010339`); tampilkan `020006.000.010.339`, titik hanya visual.
7. QR acak aman unik untuk kombinasi anggota–rapat, terbit hanya setelah status Verified; jangan pernah kembali memakai HMAC deterministik permanen berbasis NBA.
8. Kas berbasis `scanner_user_id`, bukan meja; alokasi kas awal sekali per Scanner per rapat, tanpa top-up/transfer; scanner tidak boleh membayar melebihi kas tersedia, dan presensi yang mengharuskan pembayaran tidak boleh sukses tanpa pembayaran terjamin.
9. Mode Presensi 1x dan Mode Presensi 2x (Datang–Pulang) harus disebut lengkap.
10. Jika ada keputusan **PERLU KEPUTUSAN** terkait proses yang sedang dikembangkan, hentikan bagian itu dan ajukan pertanyaan spesifik, tanpa mengarang perilaku.
11. Satu fase selesai (test hijau, acceptance terpenuhi, review pengguna) baru lanjut fase berikutnya. Jangan push GitHub tanpa izin eksplisit.

## Referensi modul
- Bisnis/produk: `docs/01-business/`
- Operasional & hak akses: `docs/02-workflows/`
- Implementasi: `docs/03-technical/`
- Perencanaan: `docs/04-development/`
- Uji: `docs/05-quality/`
- Operasi: `docs/06-operations/`

## Definition of Done
Tidak ada konflik aturan, migration valid, policy dan test keamanan lulus, kasus normal/tepi/penyalahgunaan diuji, dokumentasi diperbarui, dan user menyetujui hasil fase.


**Aturan final BR-SHU-LOG-01:** log pencarian SHU publik memiliki retensi 14 hari; jalankan pembersihan harian menggunakan Laravel Scheduler. Jangan menghapus record `shu` atau `log_audit` perubahan Admin. Terapkan CAPTCHA server-side dan rate limiting; status CAPTCHA bukan autentikasi pemilik NBA.


## Keputusan v2.2 — Integrasi dari Pengaturan Sistem
**FINAL:** CAPTCHA dan Fonnte WhatsApp dikonfigurasi hanya oleh Admin melalui **Pengaturan Sistem → Integrasi**, tanpa mewajibkan pengeditan `.env` untuk kredensial kedua layanan. Secret terenkripsi at-rest, tidak pernah ditampilkan kembali mentah atau ditulis ke log. Perubahan konfigurasi diaudit; uji koneksi menggunakan aksi eksplisit, tanpa mengirim pesan otomatis ketika hanya menyimpan form. `.env` tetap dipakai untuk APP_KEY, koneksi DB, dan konfigurasi infrastruktur Laravel. Detail kanonik: `docs/03-technical/INTEGRATION_SETTINGS.md`.
