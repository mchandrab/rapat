# Kebijakan Offline & Sinkronisasi

> **Status:** FINAL, 3 November 2026 (D-032). Dokumen ini menggantikan usulan sebelumnya; tidak ada antrean offline.

## Aturan yang berlaku

- Aplikasi berjalan **online saja**. Tidak ada penyimpanan lokal, antrean draf, atau sinkronisasi tunda.
- Saat koneksi perangkat putus, **pemindaian dihentikan total**:
  - halaman pindai menampilkan peringatan merah **Koneksi terputus** dan tombol **Catat Presensi** nonaktif;
  - halaman **Status Koneksi** menjelaskan aturan yang sama untuk petugas.
- Tidak ada uang yang boleh diserahkan selama offline dan tidak ada presensi yang dicatat manual sebagai pengganti.
- Peserta diarahkan ke Scanner lain yang online. Setelah koneksi kembali, Scanner memeriksa **Kas Saya** lalu melanjutkan pemindaian.
- Server tidak menyediakan endpoint presensi offline, sehingga tidak ada konflik, duplikat, atau data usang yang perlu ditangani saat sinkron.

## Alasan

- Pembayaran tanpa jaminan kas dan status keanggotaan terkini melanggar BR-009/D-015: presensi tidak boleh sukses tanpa pembayaran terjamin.
- Rapat berlangsung dengan beberapa Scanner; antrean offline menambah risiko konflik dan kehilangan uang tanpa manfaat yang sepadan.

## Yang tidak berubah

- Kolom `presensi.status_sinkron` tetap ada dan selalu bernilai `tersinkron`; kolom ini disiapkan untuk kebutuhan lain dan tidak dipakai untuk antrean.
- Retensi 14 hari hanya berlaku untuk `log_cek_shu`, bukan untuk data presensi atau audit.

## Bila koneksi bermasalah di lokasi

1. Pastikan perangkat benar-benar kehilangan koneksi (bukan sekadar lambat): coba muat ulang halaman.
2. Jika jaringan gedung bermasalah, gunakan tethering HP petugas; tetap satu perangkat satu Scanner.
3. Bila tetap putus, hentikan pemindaian di perangkat itu dan arahkan peserta ke Scanner lain yang online.
4. Catat kejadian di buku acara; Admin mencatat ringkasannya di Audit bila diperlukan.
