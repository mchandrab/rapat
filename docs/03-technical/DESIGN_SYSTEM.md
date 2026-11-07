# DESIGN SYSTEM — Tabler

> **Status:** Pedoman UI aktif, diperbarui 7 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Prinsip UI
Tabler penuh, Indigo primer `#4263eb`, font Inter, Blade + Livewire, mobile-friendly, tema terang/gelap, bahasa Indonesia, pesan jelas, tombol dan teks terbaca untuk pengguna lanjut usia.

- Skala tipografi mengikuti bawaan Tabler (`0.875rem`, line-height `1.4285`); jangan menetapkan ukuran global baru per halaman.
- Font Inter Variable disajikan dari aset lokal agar tampilan konsisten tanpa bergantung koneksi internet.
- Ikon menggunakan Tabler Icons melalui kelas `ti ti-*`; ukuran mengikuti komponen Tabler, kecuali ikon ilustrasi utama yang memang membutuhkan ukuran lebih besar.
- Pilihan tema disimpan di browser. Jika belum ada pilihan, gunakan preferensi sistem. Sidebar mewarisi tema aktif: terang pada tema terang dan gelap pada tema gelap.

## Navigasi berdasarkan role
- **Admin:** Utama (Dashboard), Operasional (anggota/undangan, rapat, kas, presensi, kuorum, SHU, doorprize), Pelaporan (Laporan, Audit), dan Sistem. Sidebar bagian Sistem hanya memuat **Pengaturan**.
- **Helpdesk:** Utama, Pelayanan (Anggota & Undangan, Riwayat Undangan), dan Pelaporan.
- **Scanner:** Utama (Scan QR) dan Operasional (Kas Saya, Status Koneksi).
- **Keuangan:** Utama, Kas & Transport, dan Pelaporan; **tidak ada menu SHU**.
- Judul span menggunakan teks kecil kapital dengan jarak yang jelas. Halaman Pengaturan memakai satu navbar horizontal `nav-overflow` mode pills berisi Pengguna, Integrasi, dan Aplikasi; item aktif mengikuti route dan navbar dapat digeser pada layar sempit. Penyembunyian menu bukan otorisasi; route tetap dilindungi middleware role.

## Pola layar
- NBA input menerima 15 digit atau pola bertitik; tampil hasil 6.3.3.3; helper menjelaskan panjang tepat.
- Panel verifikasi menampilkan Layak/Tidak Layak dari sistem dan keputusan petugas Verified/Rejected dengan alasan khusus.
- Kriteria Kelayakan hanya menampilkan Kolektibilitas Maksimum pada Mode A; Mode B menyembunyikannya dan menampilkan switch Wajib pinjaman lancar yang otomatis aktif. Mode A selalu menyimpan `wajib_pinjaman_lancar = false`, Mode B selalu `true`, sehingga kriteria pinjaman tidak pernah menyaring peserta tanpa terlihat Admin. Nilai kolektibilitas tetap dikirim saat Mode B aktif agar berpindah mode tidak menghapus konfigurasi.
- Tidak ada kolom batas hangus pada form rapat; hangus adalah konsekuensi tier. Transport dibayarkan = max(0, nominal dasar − potongan tier), jadi transport menjadi Rp0 saat potongan tier mencapai nominal dasar (D-022); presensi tetap tercatat hadir dan ditandai Hangus (Rp0) beserta tier penyebabnya, tanpa baris pembayaran dan tanpa mengurangi kas Scanner. Kedatangan setelah tier terakhir memakai potongan tier terakhir (D-047), bukan nominal penuh.
- Informasi Umum Rapat memuat nama lokasi, alamat lengkap, dan tautan titik lokasi Google Maps opsional. Tautan tampil sebagai aksi **Buka Google Maps** pada ringkasan dan undangan, bukan sebagai URL mentah di UI (D-048).
- Dialog impor anggota tetap ringkas: satu aksi menonjol untuk mengunduh template dan satu input berkas. Aturan rinci tidak diduplikasi di modal karena template XLSX telah memiliki sheet **Petunjuk**, format teks untuk NBA/nomor HP, dan dropdown bagi nilai terbatas. Hasil impor menampilkan paling banyak 50 kesalahan pertama beserta jumlah total baris ditolak agar halaman tetap dapat digunakan pada berkas besar.
- Saat impor anggota dikirim, dialog berubah menjadi status proses indeterminate, menampilkan nama berkas dan peringatan bahwa sekitar 11.000 baris dapat memerlukan beberapa menit. Tombol kirim, batal, tutup, backdrop, dan Escape dikunci sampai respons selesai untuk mencegah pengiriman ganda atau anggapan bahwa proses sudah berhenti. Persentase palsu tidak ditampilkan karena pemrosesan masih berlangsung dalam satu request sinkron.
- Aksi **Siapkan Peserta** memakai dialog konfirmasi yang menampilkan jumlah master anggota, jumlah peserta yang sudah ada, dan peringatan untuk menyimpan konfigurasi rapat terlebih dahulu. Setelah dikirim, dialog berubah menjadi status proses indeterminate dan dikunci sampai respons selesai; peserta Pending dihitung ulang dari konfigurasi terbaru, sedangkan Verified/Rejected dilewati.
- Undangan memiliki tombol WA/cetak dan riwayat petugas, waktu, kanal serta status.
- Scanner menampilkan nama & NBA seperlunya, saldo kas Scanner, nominal pasti yang dihitung, tombol konfirmasi bayar, pesan tegas jika saldo tidak cukup.
- Dashboard Keuangan kartu per Scanner, bukan per meja: kas awal, dibayar, sisa, status menipis, rekonsiliasi.
- Peringatan saldo menipis bersifat nonblocking; kas tidak cukup blocking. Jangan menampilkan alur 'Bayar Nanti' atau 'Belum Dibayar' untuk presensi sukses.
- Publik: papan skor agregat, undangan token, cek SHU terproteksi. Tidak mengekspos media pribadi. Resi transport tidak dapat diverifikasi publik; hanya Scanner pembayar, Admin, dan Keuangan (D-027).
- `/doorprize` memakai tabel master hadiah dengan nama, jumlah, terisi, sisa, dan keterangan; tidak ada kontrol urutan. `/doorprize/undian` adalah layar acara minimalis berwarna gelap dengan panel kesiapan, panggung kandidat, riwayat pemenang, pemilih hadiah aktif, dialog konfirmasi, daftar gugur, dan hadiah habis. Hadiah habis tidak tampil pada pemilih tetapi tidak disembunyikan dari hasil (D-046).

## Respons operasional
Tampilkan hasil sukses/gagal segera, status memuat sinkron dan status pembayaran tanpa ambigu; print/WA tercatat sebagai aksi, bukan jaminan pesan diterima. Mode Presensi 1x / Mode Presensi 2x (Datang–Pulang) ditulis lengkap.

## Fondasi visual terpadu
- Shell aplikasi memakai sidebar 256 px pada desktop, topbar ringkas, lebar konten Tabler `container-xl`, kartu dengan radius dan bayangan lembut, serta latar permukaan sekunder. Navigasi aktif memakai latar Indigo lembut dan tetap mengikuti pengelompokan D-040.
- Dashboard setiap role hanya menampilkan metrik dari database. Nilai yang belum ada ditampilkan sebagai nol atau empty state yang jelas, bukan placeholder angka.
- Form, tombol, tabel, badge, modal, alert, loading state, dan halaman publik memakai skala jarak serta fokus yang sama dari `public/css/erat.css`; aturan per halaman hanya dipakai untuk kebutuhan khusus seperti layar undian dan cetak.
- Semua shell menyediakan tautan lewati-ke-konten, target sentuh yang cukup, fokus keyboard yang terlihat, dan dukungan `prefers-reduced-motion`.
- Pada layar kecil, tindakan header boleh membungkus, sidebar menjadi menu yang dapat digulir, dan tabel tetap berada di dalam `table-responsive`. Header mobile memakai grid `1fr auto 1fr` agar brand tepat di tengah, tidak bergeser karena lebar tombol menu dan aksi kanan yang berbeda.
- Halaman Scanner dirancang untuk HP karena seluruh pemindaian dilakukan dari perangkat: area kamera QR selebar layar, tombol aksi minimal 44 px, tombol **Catat Presensi** menempel di bawah selama form terlihat, kanvas tanda tangan 180 px, dan tabel pembayaran menyembunyikan kolom waktu pada layar kecil. Input memakai `font-size: 1rem` pada layar kecil agar iOS tidak memperbesar halaman saat fokus.
- Form panjang bertab memakai `nav nav-pills erat-form-tabs` di card header; setiap panel diawali `erat-section-head` (judul + satu kalimat tujuan bagian) dan subbagian memakai `erat-subsection-title`. Panel yang berisi isian tidak valid dibuka otomatis saat validasi gagal, dan tombol simpan berada di `card-footer`.


### Cek SHU publik — pesan & privasi
Halaman meminta NBA, tahun buku dan CAPTCHA; tampilkan pesan umum jika pencarian tidak tersedia. Hindari menampilkan log, IP, nama pengunjung atau detail internal. Di halaman Admin Audit dapat tampilkan keterangan **“Riwayat pencarian SHU tersedia selama 14 hari”**. Jangan tampilkan 365 hari. CAPTCHA bukan verifikasi kepemilikan NBA.


## UI Pengaturan → Integrasi (v2.2)
Menggunakan layout Tabler dan dua kartu: **CAPTCHA** dan **Fonnte WhatsApp**. Masing-masing memiliki switch aktif, badge `Belum dikonfigurasi / Tersimpan / Uji berhasil / Uji gagal`, tombol `Simpan`, dan `Uji Koneksi`. CAPTCHA menampilkan input site key dan field secret baru `••••••` tanpa menunjukkan credential tersimpan; Fonnte menampilkan label akun (bila disediakan), field token baru tersamarkan dan penerima pengujian. Perubahan disertai konfirmasi untuk penghapusan/rotasi secret. Tampilkan pesan gagal aman dan hindari menyalin secret ke notifikasi. Hanya Admin melihat menu.
