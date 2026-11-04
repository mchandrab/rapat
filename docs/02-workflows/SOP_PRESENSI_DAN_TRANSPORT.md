# SOP Presensi dan Transport

> **Status:** SOP aktif, diperbarui 4 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Persiapan
Admin mengunci aturan saat Berlangsung, menentukan jam mulai presensi dan jam acara, tier dan toleransi. Keuangan menyerahkan satu kas awal per Scanner per rapat. Scanner memeriksa ketersediaan perangkat dan saldo.

## Mode Presensi 1x
1. Scan QR undangan dengan kamera perangkat (atau tempel token manual bila kamera tidak tersedia); server cek QR sesuai rapat, Verified, status anggota Aktif terkini, belum pernah hadir, jam buka presensi.
2. Hitung nominal dengan waktu datang relatif jam mulai acara dan toleransi. Cek kas tersedia Scanner; bila tidak cukup tampilkan penolakan tanpa menyimpan presensi sukses. Bila sisa kas di bawah ambang peringatan rapat, tampilkan banner **Kas menipis** (tidak memblokir).
3. Ambil foto peserta dari kamera perangkat dan minta peserta menggambar tanda tangan di layar; keduanya wajib. Konfirmasi bayar jumlah sistem; simpan pembayaran, catatan presensi, saldo logis, waktu, petugas, dan kedua bukti secara atomik; terbitkan resi. Bila foto atau tanda tangan tidak valid, presensi dibatalkan seluruhnya.
4. Serahkan uang dan berikan resi. Resi dapat dibuka lagi dari daftar pembayaran di **Kas Saya** dan dicetak; hanya Scanner yang menyerahkan, Admin, dan Keuangan yang dapat membukanya.

## Mode Presensi 2x (Datang–Pulang)
1. Scan datang memvalidasi peserta, mewajibkan foto + tanda tangan, dan mengunci nominal transport tanpa menyerahkan uang.
2. Pulang hanya dilayani setelah datang tercatat; nominal mengikuti waktu datang, bukan waktu pulang. Scan pulang hanya memerlukan token QR.
3. Uang transport diambil dari kas **Scanner yang mencatat pulang** (boleh berbeda dari Scanner pencatat datang); kepulangan dan pembayaran tersimpan bersama atau tidak sama sekali, resi terbit saat pulang. Peserta yang tidak pernah pulang tetap dihitung hadir untuk kuorum, tetapi transportnya hangus (D-034).
4. Pemindaian pulang masih dapat dilakukan sampai Admin menetapkan rapat **Selesai** atau **Dibatalkan**.

## Penolakan
Kas habis atau kurang, QR salah rapat, Pending/Rejected, anggota Nonaktif, duplikat, atau sesi tertutup → berhenti dan berikan alasan. Jika kas kurang, arahkan ke Scanner lain, tanpa status transport belum dibayar.

## Waktu & kunci
Contoh jam acara 08.00, toleransi 60 menit: sampai 09.00 tanpa potongan; 09.01 memasuki tier menit ke-1. Jam presensi dapat dibuka sebelum jam acara (misal 07.00).

## Penutupan
Uang kembali ke Keuangan, cocokkan total pembayaran berhasil dan sisa fisik per Scanner; selisih dicatat dan ditangani melalui SOP kas.


**Batas tanggung jawab:** retensi 14 hari hanya berlaku pada log pencarian SHU publik, bukan pada presensi, foto/TTE, pembayaran, atau rekonsiliasi. Foto dan tanda tangan **disimpan permanen tanpa penghapusan otomatis** (D-023).

> **Integrasi v2.2:** konfigurasi CAPTCHA dan Fonnte WhatsApp hanya melalui **Pengaturan → Integrasi** oleh Admin; lihat `docs/03-technical/INTEGRATION_SETTINGS.md`. Tidak perlu menyimpan credential layanan di `.env`; secret wajib terenkripsi.
