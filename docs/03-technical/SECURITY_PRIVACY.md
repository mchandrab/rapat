# SECURITY & PRIVACY

> **Status:** Pedoman aktif, diperbarui 4 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Proteksi identitas dan data
NBA tetap 15-digit string, batasi akses master; endpoint pencarian publik tidak boleh memungkinkan enumerasi NBA. Foto dan tanda tangan wajib private disk, only Admin / Keuangan untuk audit kas terotorisasi; Helpdesk dilarang mengaksesnya. Bukti disajikan lewat rute ber-otorisasi `presensi.bukti` (middleware role), bukan URL publik, dan berkas tidak pernah disalin ke disk publik.

## Token undangan
Token cryptographically secure per peserta per rapat dan hanya dibuat setelah Verified. Simpan hash untuk lookup dan token terenkripsi untuk kebutuhan cetak ulang token sama; endpoint undangan dibatasi informasi dan diproteksi terhadap brute-force. Catat upaya tidak valid.

## Hak akses
Role tepat empat. Seluruh endpoint/action cek permission server-side; login throttle/CSRF/session aman; aplikasi tidak mempercayai nominal, status anggota, atau saldo dari browser scanner.

## Kas
Kas tidak boleh negatif; lock row dan commit atomic untuk pembayaran; satu pembayaran per peserta; akun Scanner hanya dapat memakai kas sendiri; audit setiap serah terima dan rekonsiliasi.

## Status keanggotaan
Saat scan, baca status anggota terkini di server. QR yang pernah diterbitkan tidak otomatis memberikan hak masuk bagi anggota Nonaktif. Saat offline, tidak boleh menganggap snapshot lama sebagai fakta mutakhir.

## Penyimpanan & pemulihan
Backup terenkripsi, akses minimal, enkripsi transit, rotasi credential, audit integritas backup. Retensi **log pencarian SHU publik adalah 14 hari (FINAL)**. Retensi **foto/TTE adalah permanen, tanpa penghapusan otomatis (D-023, FINAL)**. Retensi log audit selain log pencarian SHU **belum diputuskan** dan perlu dasar kebijakan koperasi serta ketentuan hukum yang berlaku. Reset total Admin adalah pengecualian eksplisit yang menghapus semua arsip untuk memulai dari nol (D-037), bukan kebijakan retensi rutin.


### Kebijakan FINAL — Log Cek SHU Publik
Retensi **14 hari** sejak `created_at` masing-masing percobaan pencarian; gunakan job terjadwal harian untuk hapus bertahap log kedaluwarsa. Admin saja dapat mengakses, tanpa nominal SHU, dilengkapi rate limiting dan verifikasi CAPTCHA di server. Jangan menganggap CAPTCHA sebagai bukti bahwa pengunjung pemilik NBA. Penghapusan **hanya** `log_cek_shu`; retensi `shu`, log koreksi SHU Admin, dan log audit umum menggunakan kebijakan masing-masing. Foto/TTE **tidak pernah dihapus otomatis** (D-023) dan tetap ikut tercakup dalam siklus backup.


## Pengamanan Credential Integrasi (v2.2)
- Hanya Admin dapat memperbarui konfigurasi, dan hanya melalui route CSRF-protected + otorisasi server-side. Tidak ada endpoint untuk membaca kembali secret mentah, termasuk bagi Admin.
- Enkripsi at rest menggunakan layanan enkripsi Laravel dengan `APP_KEY` aman; batasi backup, log, error reporting, dan hak database. Saat `APP_KEY` dirotasi, wajib ada prosedur re-enkripsi/migrasi credential yang tervalidasi.
- Secret CAPTCHA hanya dikirim oleh backend ke layanan verifikasi; site key CAPTCHA boleh berada di frontend. Token Fonnte hanya digunakan backend ke API; jangan tempatkan dalam HTML atau JavaScript.
- Form ubah token: input kosong berarti credential lama dipertahankan; ganti via aksi terpisah, hapus via konfirmasi eksplisit. Audit metadata admin, tindakan, layanan, waktu, dan status tanpa nilai credential.
- Untuk tes Fonnte, harus ada nomor penerima jelas + konfirmasi Admin; batasi frekuensi dan catat sebagai pesan percobaan. Verifikasi hasil API tidak boleh diasumsikan sebagai status WhatsApp 'terkirim ke penerima' tanpa bukti.
- Jika CAPTCHA tidak aktif/tidak terkonfigurasi/gagal, layanan Cek SHU berhenti sementara, bukan melewati CAPTCHA. Rate limiting tetap berlaku; retensi log cek SHU tetap **14 hari**.
