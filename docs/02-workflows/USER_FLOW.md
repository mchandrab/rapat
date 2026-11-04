# USER FLOW — Alur Pengguna

> **Status:** Dokumen aktif, diperbarui 4 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Sebelum rapat
1. Admin impor/perbarui master anggota NBA 15 digit dan saldo per 31 Desember Tahun Acuan; cek duplikat/invalid.
2. Admin membuat rapat Draf, mengisi identitas rapat, Tahun Acuan pada Kriteria Kelayakan, jam presensi terpisah dari jam acara, Mode Presensi 1x atau Mode Presensi 2x (Datang–Pulang), kriteria, kuorum, tarif transport, dan daftar hadiah.
3. Admin membuka tahap Verifikasi. Umumnya H-30 sampai H-7, anggota datang ke tempat pelayanan, Helpdesk/Admin mencari NBA, sistem menilai Layak/Tidak Layak memakai saldo proporsional, petugas menetapkan Verified/Rejected. Untuk Tidak Layak yang tetap Verified, alasan wajib.
4. Sesudah Verified, QR unik rapat terbit; petugas mengirim WA atau mencetak, dan sistem mencatat petugas/metode/waktu/status.
5. Keuangan memberikan kas awal satu kali kepada setiap akun Scanner untuk rapat; Scanner bertanggung jawab atas kasnya.

## Hari-H
6. Admin membuka presensi secara hybrid ketika sarana siap. Scanner melakukan scan dan sistem memeriksa token, status Verified, status anggota terkini Aktif, anti-duplikat, dan kecukupan kas sebelum melanjutkan transaksi yang memerlukan pembayaran.
7. **Mode Presensi 1x:** foto + TTE, catat hadir dan serahkan uang saat datang dalam transaksi terkoordinasi; gagal bila kas tidak cukup.
8. **Mode Presensi 2x (Datang-Pulang):** datang mencatat hadir + foto/TTE dan mengunci nominal tanpa uang; pulang memindai QR lagi dan transport dibayar dari kas Scanner yang mencatat pulang (boleh berbeda). Tanpa pulang, transport hangus (D-034).
9. Kas Scanner menipis → peringatan. Jika tidak cukup → presensi yang menuntut pembayaran ditolak; arahkan ke Scanner lain. Kas Scanner tidak bisa ditambah saat acara.
10. Sistem menghitung kuorum anggota biasa dan menampilkannya di menu **Kuorum** untuk Admin/Keuangan, sebagai ringkasan di Dashboard Scanner, dan pada `/papan-kuorum` untuk publik. Admin memilih hadiah yang masih tersedia lalu menjalankan **Doorprize** dari peserta hadir dengan konfirmasi masih ada/sudah pulang.
11. Dashboard menampilkan informasi rapat baca-saja sesuai tugas: kriteria untuk Helpdesk, mode dan gerbang untuk Scanner, serta konfigurasi transport untuk Keuangan. Hanya Admin dapat membuka halaman pengaturan atau mengubah parameter rapat (D-039).

## Sesudah rapat
12. Scanner mengembalikan sisa uang, lalu Keuangan menghitung pembayaran sukses dan selisih per Scanner. Tidak ada data offline karena pemindaian berhenti total saat koneksi putus (D-032).
13. Admin menutup rapat setelah pemeriksaan presensi & kas. Laporan undangan per petugas, kas per Scanner, kuorum, SHU, dan hasil doorprize tersedia.
14. Setelah SHU final, Admin membuka cek SHU publik dari menu **SHU** untuk tahun buku tersebut (tombol eksplisit, tidak menunggu rapat selesai). Selama saklar tertutup, publik tidak dapat mencari SHU.

## Pengecualian
- NBA tidak ditemukan, QR rusak, anggota Nonaktif → Helpdesk/Admin; scanner tidak memaksakan.
- QR anggota Nonaktif ditolak walaupun undangan pernah sah.
- Pembayaran offline tidak diizinkan; saat koneksi putus pemindaian dihentikan total (D-032).
- Transport Rp0 tetap mencatat presensi tanpa pembayaran dan tanpa mengurangi kas (D-022).


### Cek SHU publik dan masa log
Pengunjung memilih tahun buku, memasukkan NBA, melewati CAPTCHA, lalu server memverifikasi CAPTCHA/rate limit sebelum membaca data SHU. Setiap upaya yang mencapai endpoint dicatat sebagai log pencarian; log hanya dapat dibaca Admin dan otomatis dihapus bila lebih lama dari 14 hari. Data SHU per tahun buku tetap tersimpan. CAPTCHA membatasi bot, tetapi **bukan** bukti pemilik NBA; catat risiko tersebut sebelum publikasi.


## Pengaturan → Integrasi (v2.2)
1. Admin login dan membuka Pengaturan → Integrasi.
2. Admin pilih kartu CAPTCHA atau Fonnte; mengisi key baru hanya saat penambahan/rotasi. Secret lama tampil sebagai `Tersimpan` bukan nilainya.
3. Admin menyimpan; server memvalidasi format, mengenkripsi nilai sensitif, menulis audit metadata, dan memperbarui versi konfigurasi. Konfigurasi dibaca langsung dari database sehingga tidak ada cache konfigurasi aplikasi yang harus dibersihkan. Simpan **tidak** mengirim WA.
4. Admin melakukan uji eksplisit. Untuk CAPTCHA, lakukan challenge sungguhan lalu validasi token pada server. Untuk Fonnte, Admin memasukkan nomor WhatsApp penguji dan mengonfirmasi pengiriman satu pesan percobaan.
5. Jika tidak aktif/tidak valid, Cek SHU menolak pencarian dengan pesan layanan belum tersedia; pengiriman WA dari menu Anggota melaporkan gagal tanpa mengubah status Verified atau membuat klaim terkirim.
