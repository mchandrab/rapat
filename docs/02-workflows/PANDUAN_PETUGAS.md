# PANDUAN PETUGAS — Satu Halaman per Role

> **Status:** Fase 10, 3 November 2026. Ditulis untuk petugas lapangan, termasuk yang belum terbiasa memakai komputer. Alur lengkap ada di `ALUR_PROSES_SEMUA_ROLE.md`.

## Sebelum hari-H

- Pastikan HP/laptop terhubung internet dan waktu perangkat benar (transport dihitung dari jam server, bukan jam HP).
- Admin membuka **Manajemen RAT**, mengisi parameter, lalu **Siapkan Peserta**.
- Helpdesk menyelesaikan verifikasi; hanya peserta **Verified** yang punya QR.
- Keuangan mencatat kas awal tiap Scanner. Scanner menekan **Saya sudah menerima kas** sebelum memindai apa pun.
- Uji sekali: scan satu peserta uji, lalu koreksi bila perlu (koreksi diaudit).
- Uji kamera QR sekali di laptop: jalankan `php artisan serve`, buka `http://localhost:8000/kas-saya`, izinkan kamera. Alternatif: `http://rapat.localhost` setelah menekan **Apache → Reload** di Laragon. Kamera hanya aktif di `localhost` atau HTTPS, **tidak** di `http://rapat.test`.

## Admin

| Ingin | Buka |
|---|---|
| Menyiapkan RAT dan parameter | **Manajemen RAT** |
| Melihat seluruh peserta | **Anggota & Undangan** |
| Memantau kas dan menutup kas | **Kas Scanner**, **Presensi & Bukti** |
| Melihat kuorum | **Kuorum** |
| Input, koreksi, impor SHU dan membuka cek publik | **SHU** |
| Mengundi doorprize | **Doorprize** |
| Mengunduh rekap | **Laporan** → pilih rapat → **Ekspor XLSX** |
| Melihat jejak aktivitas | **Audit** |
| Mengatur CAPTCHA dan WhatsApp | **Pengaturan** → **Integrasi** |
| Menghapus semua data (mulai dari nol) | **Pengaturan** → **Aplikasi** - ketik `HAPUS SEMUA DATA` |
| Mengelola akun petugas | **Pengaturan** → **Pengguna** |

Catatan: parameter rapat terkunci saat status **Berlangsung**. Perubahan hanya lewat koreksi teraudit.

## Helpdesk

1. **Anggota & Undangan** → cari NBA (boleh dengan atau tanpa titik).
2. Periksa hasil kelayakan. **Tidak Layak** tetap boleh diberi undangan, tetapi wajib alasan.
3. Tetapkan **Verified**, lalu **Cetak** atau **Kirim WhatsApp**.
4. Setiap kirim/cetak tercatat di **Riwayat Undangan**; laporan per petugas ada di **Laporan**.

Helpdesk tidak dapat melakukan presensi dan tidak dapat membuka kas, foto/TTE, SHU, atau doorprize. Setelah menyelesaikan masalah verifikasi/undangan, arahkan peserta kembali ke Scanner.
Informasi jadwal, status, dan kriteria kelayakan tersedia baca-saja di Dashboard. Helpdesk tidak dapat mengubah master anggota atau parameter rapat (D-039).

## Scanner

1. Buka **Scan QR** di HP. Kamera aktif otomatis bila izin sudah pernah diberikan; kolom token manual selalu tersedia.
2. Arahkan kamera ke QR undangan peserta. Sistem menampilkan nama, nominal transport, dan sisa kas.
3. Jika muncul peringatan **Kas menipis**, lanjutkan sampai kas habis; jangan meminjam kas Scanner lain.
4. Jika saldo tidak cukup untuk nominal peserta, scan ditolak — arahkan peserta ke Keuangan, jangan mencatat manual.
5. Cek **Kas Saya** untuk melihat kas awal, terpakai, dan sisa.
6. **Status Koneksi** hanya informasi; aplikasi berjalan online saja dan pemindaian berhenti total saat koneksi putus (D-032).
7. **Rapat Mode Presensi 2x (Datang-Pulang):** pilih tab **1. Datang** untuk mencatat kehadiran + foto/TTE (uang belum diserahkan), lalu tab **2. Pulang & Bayar** saat peserta pulang. Uang diambil dari kas Scanner yang mencatat pulang, jadi pastikan kas Anda cukup. Peserta yang tidak pulang tetap dihitung hadir, tetapi transportnya hangus (D-034).
8. Lihat **Ringkasan Kuorum** di Dashboard Scanner. Angka hadir, target, populasi, persentase, dan kekurangan diperbarui otomatis setiap 10 detik.
9. Dashboard juga menampilkan jadwal, mode, dan status gerbang presensi secara baca-saja.

Papan untuk layar umum tersedia di `/papan-kuorum`. Halaman ini dapat dibuka tanpa login dan hanya menampilkan angka agregat, bukan nama/NBA peserta.

## Keuangan

1. **Alokasi & Monitor** → catat kas awal per akun Scanner. Satu Scanner satu alokasi per rapat; tidak ada top-up atau transfer.
2. Pantau sisa kas. Bila Scanner diganti, tutup kas lama lebih dulu; saldo tidak dipindahkan.
3. **Presensi & Bukti** untuk memeriksa foto dan tanda tangan (hanya Admin dan Keuangan).
4. **Rekonsiliasi** setelah acara: hitung uang fisik bersama Scanner, isi **sisa fisik**, lalu **Tutup kas**. Selisih bukan nol wajib diberi alasan.
5. **Laporan** → rekap presensi, kas, dan kuorum; **Ekspor XLSX** untuk arsip.
6. Dashboard menampilkan jadwal, mode, dan konfigurasi transport secara baca-saja. Data master anggota tetap tidak dapat dibuka.

Keuangan tidak dapat mengubah SHU, doorprize, atau parameter rapat.

## Kalau ada masalah

| Gejala | Tindakan |
|---|---|
| Scan ditolak "kas belum diterima" | Scanner menekan **Saya sudah menerima kas** di **Kas Saya** |
| Scan ditolak "kas tidak cukup" | Catat di kertas, arahkan ke Keuangan; jangan memindai peserta lain sebagai pengganti |
| QR tidak terbaca | Minta token manual dari petugas; token hanya berlaku untuk rapat itu |
| Anggota Nonaktif | Undangan tidak dapat dipakai; arahkan ke Helpdesk untuk verifikasi status |
| Halaman lambat | Laporkan ke Admin; jalankan `php artisan erat:benchmark` untuk mengukur |
| Data perlu dipulihkan | Hentikan input, hubungi Admin teknis; jangan menjalankan restore sendiri |
