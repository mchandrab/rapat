# DEPLOYMENT RUNBOOK — Pemasangan & Pemulihan

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Pra-produksi
Pastikan PHP 8.3, MySQL, web server HTTPS, backup, konfigurasi mail/WA bila digunakan, .env aman, permission storage, scheduler, monitoring. Jangan upload `.env` atau token WA ke repo.

## Deploy
1. Backup kode/DB yang berjalan. 2. Deploy release teruji. 3. `composer install --no-dev --prefer-dist`, build aset npm/Vite, jalankan migration setelah review. 4. Cache config/routes, validasi storage privat. 5. Jalankan smoke test login empat role, impor contoh, verifikasi, QR, presensi dummy, laporan kas Scanner. 6. Buka aplikasi setelah approval Admin.

## Backup & restore
Backup MySQL + private media + konfigurasi penting secara terenkripsi, diuji restore berkala di lingkungan nonproduksi. Token QR terenkripsi bergantung kunci aplikasi; perubahan `APP_KEY` harus direncanakan migrasinya agar undangan lama tak rusak. Retensi masing-masing jenis data mengikuti kebijakan; untuk `log_cek_shu` telah ditetapkan 14 hari.

**Perintah nyata (Fase 10):**
1. `php artisan erat:backup` menulis `storage/app/backup/erat-<waktu>.sql` dan `media-<waktu>.zip` (isi `storage/app/private`). Bila `mysqldump` tidak ada di PATH, tambahkan `--binary="C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe"` atau isi `DB_DUMP_BINARY` di `.env`.
2. Retensi bawaan 7 hari (`--keep=7`); hanya berkas berawalan `erat-`/`media-` yang dihapus.
3. Simpan hasil cadangan di luar server aplikasi, terenkripsi, dan batasi aksesnya karena berisi ciphertext credential integrasi serta data anggota.

**Uji restore (wajib berkala, jangan di database produksi):**
1. `mysql -u root -e "CREATE DATABASE erat_restore_uji"`
2. `mysql -u root erat_restore_uji < storage/app/backup/erat-<waktu>.sql`
3. Bandingkan `SELECT COUNT(*)` tabel `users`, `rapat`, `log_audit`, `log_cek_shu` dengan database asal; angkanya harus sama.
4. Jalankan `php artisan migrate:status` pada database uji, lalu hapus database uji.

Uji restore 3 November 2026: cadangan 40 KB, jumlah baris `users`/`rapat`/`log_audit`/`log_cek_shu` identik (4/1/11/0).

## Monitoring
Login gagal beruntun, kegagalan pengiriman WA, error QR, transaksi presensi gagal, kas menipis, saldo anomali, koneksi Scanner terputus, dan hasil job cadangan. Tidak ada antrean/sinkronisasi offline (D-032).

## Rollback
Kembali ke release sebelumnya hanya jika skema DB kompatibel. Jika tidak, gunakan restore backup + prosedur kehilangan data yang disetujui. Catat insiden dan audit.


### Scheduler dan retensi SHU (FINAL)
Pasang Laravel Scheduler agar berjalan tiap menit sesuai mekanisme deployment, lalu jadwalkan **pembersihan log pencarian SHU harian**. Job melakukan delete bertahap untuk `created_at < now() - 14 days`; pantau jumlah row yang terhapus, kegagalan job, dan waktu eksekusi (tanpa menyimpan NBA dalam log teknis job). Retensi ini khusus `log_cek_shu`; data SHU dan audit perubahan Admin tidak ikut dihapus. Evaluasi retensi pada backup/arsip.

**Baris cron yang wajib ada (Linux):**
```
* * * * * cd /path/ke/rapat && php artisan schedule:run >> /dev/null 2>&1
```
Tanpa cron ini, `cek-shu:purge` (00.00) dan `erat:backup` (23.00) **tidak pernah berjalan**.

Verifikasi setelah dipasang: `php artisan schedule:list` harus memuat kedua perintah, lalu jalankan `php artisan schedule:run` sekali dan pastikan keluarannya "No scheduled commands are ready to run." (bukan error). Uji 3 November 2026 di mesin pengembangan: `schedule:run` berjalan normal dan kedua jadwal terdaftar.

Untuk Windows (mis. Laragon), buat Scheduled Task yang menjalankan perintah yang sama tiap menit, atau biarkan `php artisan schedule:work` menyala selama acara.


## Operasional CAPTCHA/Fonnte dari Pengaturan (v2.2)
- Deploy Laravel dengan `.env` minimum (`APP_KEY`, DB, queue dan konfigurasi lingkungan). Jangan tempatkan token Fonnte atau CAPTCHA secret di repository, log deploy, maupun `.env` jika mode DB ini digunakan.
- Admin login dan membuka Pengaturan → Integrasi; simpan, lakukan uji nyata yang disengaja, lalu aktifkan fitur.
- Backup database mengandung ciphertext credential: batasi akses dan enkripsi backup. Restore wajib mencakup `APP_KEY` yang cocok agar ciphertext dapat didekripsi.
- Jika provider down, nonaktifkan fitur terkait; Cek SHU tidak melewati CAPTCHA; cek riwayat kegagalan tanpa membocorkan token.
- Saat rotasi APP_KEY, rencanakan dan verifikasi re-enkripsi credential integrasi.
