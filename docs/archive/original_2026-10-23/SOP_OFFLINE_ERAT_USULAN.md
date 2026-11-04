# SOP Presensi Offline dan Penanganan Gangguan eRAT

**Status: USULAN untuk diputuskan, bukan aturan final.**

## 1. Tujuan

Menjamin antrean tetap tertangani ketika koneksi gagal tanpa membuat presensi ganda, pembayaran ganda, kehilangan bukti foto/tanda tangan, atau selisih kas tanpa penjelasan.

## 2. Rekomendasi kebijakan inti

**Presensi boleh dicatat offline pada perangkat yang sudah disiapkan; pembayaran tunai normal hanya dilakukan setelah server mengonfirmasi hak dan status belum dibayar.** Ini lebih aman daripada menyerahkan uang ketika dua meja tidak bisa mengetahui pembayaran satu sama lain.

Ada dua jalur operasi:

- **Jalur A (direkomendasikan):** perangkat offline mencatat presensi dengan status `Menunggu Sinkron`, mengambil foto/TTE secara lokal terenkripsi; peserta tidak menerima transport saat itu; terbitkan tanda bukti antrean lokal yang jelas *bukan resi pembayaran* jika diperlukan. Pembayaran dilakukan setelah koneksi pulih dan server mengonfirmasi presensi, nominal dan status belum dibayar. Untuk Mode Presensi 2x, pembayaran hanya setelah presensi pulang valid.
- **Jalur B (darurat, hanya bila disetujui):** kas cadangan offline dengan nomor voucher preallocated unik per meja, batas nominal/jumlah, identitas pemegang voucher, dua petugas mengesahkan, dan catatan fisik; voucher sekali pakai dan tidak dapat dipakai di meja lain. Tanpa mekanisme pengendalian pusat/penjatahan yang benar, jangan memakai jalur ini; risiko pembayaran ganda masih ada.

## 3. Sebelum hari-H

1. Admin membuka rapat dan mengunci konfigurasi operasional yang sudah disetujui.
2. Keuangan menyiapkan kas awal per meja dan mencatat serah-terima dengan scanner. Jangan membawa kas melebihi kebutuhan tanpa otorisasi.
3. Setiap perangkat scanner diberi `device_id` unik, terkunci pada satu meja/rapat dan petugas yang sedang bertugas.
4. Perangkat saat online mengunduh daftar peserta verified minimal beserta versi snapshot, data identitas minimum, token rapat yang valid dan status presensi; jangan menyimpan seluruh master anggota, SHU atau data sensitif.
5. Uji kesiapan jam perangkat, kamera, baterai/power bank, jaringan cadangan, enkripsi lokal, penyimpanan antrean dan proses sinkronisasi.
6. Catat timestamp sinkronisasi snapshot terakhir; jika terlalu lama, mode offline wajib ditolak sesuai ambang yang ditetapkan.

## 4. Saat koneksi hilang

1. Layar memberi peringatan merah/kuning `Offline — data belum tersinkron` serta jumlah kejadian lokal yang belum terkirim.
2. Scanner mencocokkan QR khusus rapat terhadap snapshot. Peserta tidak ada dalam snapshot atau status diragukan diarahkan ke meja helpdesk atau meja online.
3. Scanner mencatat peristiwa (`rapat_id`, `peserta_id`, `device_id`, `event_type`, `event_id` unik, `waktu_perangkat`, snapshot versi, foto/TTE) sebagai antrean terenkripsi.
4. Aplikasi memberi status `Menunggu Sinkron`; menekan tombol dua kali tidak membuat kejadian baru. Perangkat mencatat keterbatasan reliabilitas waktu lokal.
5. **Tidak membayar uang tunai hanya berdasarkan hasil perhitungan offline** pada Jalur A. Sampaikan secara sederhana kepada peserta bahwa hak transport akan diproses setelah verifikasi pusat.
6. Jika terjadi gangguan kamera, tanda tangan, keraguan identitas, perangkat kehilangan antrean, atau dua perangkat mengklaim kehadiran orang yang sama, arahkan ke helpdesk dan jangan melakukan bypass secara diam-diam.

## 5. Saat koneksi kembali

1. Petugas menekan `Sinkronkan` dan menjaga perangkat tetap aktif. Pengiriman dapat diulang dengan `event_id` yang sama tanpa membuat transaksi ganda.
2. Server memvalidasi token rapat, peserta verified, mode presensi, urutan datang-pulang, snapshot, waktu, tanda tangan, dan hak akses.
3. Server mencatat event secara atomik dengan batas unik untuk `(rapat, peserta, jenis_presensi)`; penguncian baris/constraint menghindari dua meja sama-sama berhasil.
4. Server mengembalikan tiap item `Berhasil`, `Duplikat (tidak diproses dua kali)`, atau `Konflik/Butuh Pemeriksaan` beserta kode alasan.
5. Admin menangani konflik presensi; Keuangan menangani konflik pembayaran/kas; keduanya tercatat audit. Scanner tidak menyelesaikan konflik dengan mengubah data master.
6. Setelah hak pembayaran dikonfirmasi belum dibayar, scanner menyerahkan uang (sesuai mode presensi) dan menyimpan tanda penerimaan. Gunakan transaksi server atomik: status pembayaran hanya berubah satu kali. Penerbitan resi setelah status berhasil.
7. Jangan menghapus media lokal sampai server mengonfirmasi bahwa lampiran sudah diterima dan diverifikasi checksum/ukuran. Setelah aman, hapus salinan lokal sesuai kebijakan retensi.

## 6. Penutupan meja dan rekonsiliasi

1. Keuangan memeriksa seluruh perangkat/meja: tidak boleh ada `pending_sync` yang tersembunyi.
2. Total kas awal - pembayaran berhasil yang benar-benar diserahkan = sisa teoritis; cocokkan dengan uang fisik.
3. Selisih dicatat dengan alasan, nominal, petugas, waktu, status investigasi, dan persetujuan sesuai otorisasi.
4. Rapat hanya ditutup final setelah antrean sinkron tuntas, konflik diselesaikan, dan kas direkonsiliasi; jika ada pengecualian, catat persetujuan tertulis dan status kasus terbuka.

## 7. Kasus uji penerimaan minimum

- Peserta A disken pada dua perangkat offline, hanya satu kehadiran final diterima; konflik jelas.
- Satu event diunggah dua kali: tidak menggandakan kehadiran/foto/pembayaran.
- QR rapat lama tidak berlaku pada rapat baru.
- Perangkat mati lalu hidup kembali: antrean belum sinkron tidak hilang.
- Waktu perangkat salah 90 menit: ditandai untuk validasi, tidak otomatis memotong transport salah.
- Peserta tidak ada pada snapshot: tidak diproses offline.
- Server menerima presensi tetapi gagal respons: retry aman dengan event_id sama.
- Pembayaran berhasil tetapi printer gagal: cetak resi ulang tanpa bayar lagi.
- Pembayaran belum diserahkan fisik: petugas tidak boleh mengonfirmasi `Sudah Dibayar` terlebih dahulu.
- Terjadi selisih kas: laporan menampilkan sebab dan penanggung jawab, bukan menyesuaikan kas otomatis agar terlihat cocok.

## 8. Butuh keputusan pengelola

- Apakah Jalur A diterima meskipun pembayaran peserta tertunda saat gangguan jaringan?
- Apakah dibutuhkan Jalur B untuk kondisi darurat? Bila ya, rinci otorisasi, kas cadangan, voucher unik, dan batas risiko.
- Berapa lama antrean offline dapat disimpan dan bagaimana proses backup perangkat rusak?
- Bagaimana menandai dan memverifikasi waktu presensi lokal saat jam perangkat keliru?
- Bagaimana pemulihan pembayaran peserta yang sudah pulang sebelum sistem online?
