# ROLE PERMISSION MATRIX

> **Status:** Dokumen aktif, diperbarui 7 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

| Aksi | Admin | Helpdesk | Scanner | Keuangan |
|---|---|---|---|---|
| Master anggota & data sensitif | Kelola seluruh kolom | Lihat seluruh data; edit hanya WhatsApp, email, jenis anggota, status keanggotaan, dan alamat dengan audit (D-050) | — | Identitas hanya di presensi/laporan |
| Setting rapat, mode, tier dan kuorum | Kelola | Jadwal/status/kriteria | Jadwal/status/mode/gerbang | Jadwal/status/mode/transport |
| Pantau kuorum (hadir, populasi, target, status sah) | Ya | Tidak | Ringkasan dashboard | Ya, baca saja |
| Verifikasi anggota dan pengecualian | Ya | Ya | — | Lihat |
| Kirim/cetak undangan & rekam log | Ya | Ya | — | — |
| Scan, foto/TTE, presensi dan bayar | Koreksi teraudit | — | Ya, kas sendiri | — |
| Pembukaan presensi dan status rapat | Ya | — | — | — |
| Kas awal & serah ke Scanner | Pantau/audit | - | Konfirmasi terima wajib (D-026) | Catat alokasi |
| Pantau/rekonsiliasi kas Scanner | Pantau & audit | - | Lihat kas sendiri | Tutup kas + alasan selisih (D-031) |
| Konfigurasi peringatan saldo | Ya | — | Lihat | Lihat |
| SHU input/impor/koreksi | Ya | — | — | — |
| Buka/tutup cek SHU publik | Ya | — | — | — |
| Doorprize | Ya | — | — | — |
| Foto/TTE lama | Ya | — | Hanya buat dalam sesi aktif | Untuk audit kas |
| Laporan semua | Ya | Terbatas | Ringkasan diri | Kas & transport |
| Pengaturan user & audit | Ya | — | — | — |

Semua hak dicek di server melalui policy/action, bukan hanya menyembunyikan tombol. **Helpdesk tidak dapat melakukan presensi dalam keadaan apa pun**; QR/kamera bermasalah ditangani Scanner dengan token cadangan yang tetap melalui validasi server. Tidak boleh ada Scanner membayar dari kas akun Scanner lain. Semua pembatasan mengikuti BUSINESS_RULES.md.

Papan `/papan-kuorum` dapat dibuka publik tanpa login, tetapi hanya menampilkan angka agregat; data pribadi dan baris presensi individu dilarang tampil.


### Matriks retensi log cek SHU
Hanya **Admin** dapat membaca log pencarian SHU; Helpdesk, Scanner, Keuangan dan publik tidak dapat membaca log. Proses Laravel Scheduler terotorisasi sistem boleh menghapus hanya entri `log_cek_shu` yang melampaui **14 hari**. Hak mengelola record `shu` tetap Admin saja.


## Hak Akses Pengaturan Integrasi (v2.2)
| Aksi | Admin | Helpdesk | Scanner | Keuangan |
|---|---|---|---|---|
| Lihat status integrasi dan site key | Ya | Tidak | Tidak | Tidak |
| Tambah/ganti/hapus CAPTCHA secret atau token Fonnte | Ya | Tidak | Tidak | Tidak |
| Uji CAPTCHA / uji Fonnte dengan penerima eksplisit | Ya | Tidak | Tidak | Tidak |
| Kirim undangan melalui Fonnte dari alur anggota | Ya | Ya, jika berizin | Tidak | Tidak |
| Baca credential mentah yang telah disimpan | **Tidak, termasuk Admin** | Tidak | Tidak | Tidak |
