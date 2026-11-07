# ARCHITECTURE — Desain Teknis eRAT

> **Status:** Arsitektur aktif, diperbarui 7 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Stack
Laravel 13, PHP 8.3, MySQL (pengembangan lokal: MySQL `erat_rukun`), Blade + Livewire, UI Tabler penuh, role lewat kolom `users.role` + middleware (tanpa Spatie), PHPUnit dengan SQLite memory untuk test. Satu monolit web, tanpa API publik umum, tanpa ERP/core banking connection. Hanya import file hasil ekstraksi sisi client SIKOPDIT.

## Komponen
- Models: User, Anggota, Rapat, Peserta, Presensi, KasScanner, TransportPembayaran, RapatTransportTier, LogUndangan, Shu, DoorprizeHadiah/Pemenang, LogAudit, Pengaturan.
- Services: ImportAnggotaService (NBA teks), PenilaianKelayakanService, VerifikasiService, TokenUndanganService, PresensiService, TransportService, KasScannerService, KuorumService, ShuService, DoorprizeService, AuditService.
- Controllers/Livewire hanya menerima input, otorisasi, memanggil service, menampilkan pesan.

## Integritas transaksi
**Verify + QR**: kunci row `peserta` pada kombinasi rapat & anggota; Verified → buat token CSPRNG sekali, simpan hash token untuk lookup aman; undangan berulang tidak membuat QR lain.
**Scan Mode 1x**: kunci row `peserta`, `presensi`, dan `kas_scanner`; periksa status anggota mutakhir & saldo; simpan presensi, bukti, pembayaran dan resi dalam transaction database; jangan pernah mengizinkan double spending saat dua permintaan bersamaan.
**Scan Mode 2x**: `PresensiScan::catat()` mencatat kedatangan dan mengunci nominal tanpa menyentuh kas; `PresensiScan::pulang()` membayar nominal terkunci dari kas Scanner yang mencatat pulang dalam satu transaksi (D-034). Tidak ada reservasi dana dan tidak ada antrean offline.
**Rekonsiliasi**: lock sesi kas Scanner, blokir jika antrean belum tuntas, hitung pembayaran berhasil, bandingkan kas fisik, audit penutupan.
**Doorprize**: `/doorprize` mengelola master hadiah dan `/doorprize/undian` menjadi layar acara terpisah (D-046). Server lebih dulu memastikan rapat berstatus Verifikasi dengan presensi sudah dibuka atau Berlangsung. Admin memilih `doorprize_hadiah` yang masih memiliki sisa; transaksi mengunci hadiah, menghitung ulang pemenang sah, lalu mencatat hasil dan snapshot nama hadiah. Baris `jumlah` adalah stok awal dan tidak dikurangi (D-044).

## Identitas
`anggota.id` tetap sebagai FK; NBA 15 digit untuk administrasi, display 6.3.3.3. `peserta` mempunyai unique `(rapat_id, anggota_id)` dan QR token unik khusus rapat, dibuat setelah Verified. Tidak lagi memakai HMAC deterministik dari NBA.

## Waktu
Gunakan UTC di penyimpanan timestamp dan tampilkan WIB Asia/Jakarta; perhitungan kalender menggunakan zona rapat. Jam presensi terpisah dari jam acara; transport dihitung dari jam mulai acara. Tahun acuan hanya menjadi batas perhitungan kelayakan dan tidak menjadi identitas rapat (D-043). Konfigurasi utama terkunci saat berlangsung; hadiah mengikuti pengaman perubahan D-044.

## Keamanan
RBAC semua action, FormRequest, CSRF, throttle, file foto/TTE privat, token QR tak dapat ditebak, audit append-only dalam operasi normal, redaksi data pada halaman publik. Helpdesk tidak mempunyai endpoint presensi (D-036). Reset total Admin adalah satu-satunya prosedur destruktif yang boleh menghapus arsip untuk memulai dari nol (D-037).

Dashboard role membaca rapat terakhir langsung dari model `Rapat` dan merender ringkasan berbeda menurut tugas. Ringkasan tidak memberi route mutasi: pengaturan rapat tetap berada di grup middleware `role:admin`. Route pembaruan anggota menerima Admin/Helpdesk, lalu `AnggotaRequest` membatasi Helpdesk ke nomor WhatsApp, email, jenis anggota, status keanggotaan, dan alamat; Keuangan tetap melihat identitas hanya melalui presensi/laporan (D-050).


### Pencatatan pencarian SHU dan pembersihan
`CekShuController` memvalidasi CAPTCHA di server, rate limit, dan mencatat usaha lookup ke `log_cek_shu` (tanpa nominal SHU); service pemisah mengakses `shu` berdasarkan NBA/tahun buku. Scheduler menjalankan `PurgeCekShuLogs` setiap hari: hapus bertahap record lebih tua dari 14 hari, dapat diulang tanpa efek pada `shu` atau `log_audit`. Akses log hanya Admin. Penerapan NBA + CAPTCHA memiliki risiko pengungkapan nominal kepada pihak yang mengetahui NBA dan memerlukan persetujuan privasi sebelum produksi.


## Modul Integrasi Terkelola Database (v2.2)
`IntegrasiController` + `App\Services\Integrasi` (Admin-only) menangani baca-status, simpan, rotasi, disable, dan uji layanan. `Integrasi::verifikasiCaptcha()` memakai konfigurasi `captcha` aktif dan memvalidasi token pada server; kunci rahasia tidak pernah dikirim browser. `Integrasi::kirimWhatsapp()` memakai token layanan dari storage terenkripsi saat kirim undangan, dan memetakan status permintaan/delivery secara terpisah. Proses penyimpanan memvalidasi, mengenkripsi lewat cast `encrypted`, menulis audit hanya field non-rahasia. Jika konfigurasi CAPTCHA hilang atau vendor tidak tersedia, endpoint SHU **fail closed**; fallback yang melewati CAPTCHA dilarang. Kirim WA gagal tidak mengganti Verified; log menyimpan status keberhasilan sesuai bukti API, bukan mengklaim delivered tanpa bukti. Bootstrap `.env` tetap diperlukan untuk APP_KEY, akses MySQL, dan infrastruktur; pergantian credential integrasi tidak memerlukan deploy atau edit `.env`.

## Manajemen Pengguna (v2.3)
`PenggunaController` hanya berada di grup middleware `role:admin`. Admin dapat melihat, menambah, mengubah, mengganti sandi, mengaktifkan/menonaktifkan, dan menghapus akun petugas tanpa riwayat dengan role tetap `admin|helpdesk|scanner|keuangan`. Sebelum menghapus, controller mengunci akun dan memeriksa seluruh referensi operasional; akun bersejarah ditolak. FK `kas_scanner.scanner_user_id` memakai `RESTRICT` sebagai pengaman database agar kas/pembayaran tidak terhapus cascade. Identitas autentikasi adalah `username` unik yang dinormalisasi ke huruf kecil; login menambahkan syarat `active=true`, sedangkan middleware `EnsureUserIsActive` menghentikan sesi lama akun yang dinonaktifkan (D-041/D-042). Model `User` memakai cast `hashed`; sandi tidak pernah ditulis ke `log_audit`. Akun Admin yang sedang login tidak dapat dihapus, dinonaktifkan, atau diturunkan rolenya. Reset total membuat ulang akun Admin aktif saja; akun lain dibuat melalui menu Pengguna (D-035/BR-012).
