# UAT — Skenario Petugas di Lapangan

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Peserta pengujian
Admin, Helpdesk, Scanner A, Scanner B, Keuangan, pengamat panitia. Gunakan dataset dummy (tidak memakai data anggota nyata).

## Langkah UAT
1. Admin membuat RAT tahun buku 2026; jam presensi 07.00, acara 08.00, toleransi 60 menit, transport Rp100.000.
2. Impor NBA `020006000010339` dengan saldo per 31 Desember; cek UI memakai titik yang benar.
3. Helpdesk memeriksa anggota Layak serta Tidak Layak, menetapkan Verified dengan alasan jika diperlukan, cetak lalu WA, cek dua catatan petugas.
4. Ubah status satu peserta menjadi Nonaktif setelah undangan; scan harus ditolak.
5. Keuangan memberikan Scanner A kas awal Rp1.000.000; coba memberi lagi harus ditolak.
6. Scanner A scan 10 peserta sah dalam Mode Presensi 1x dengan nominal Rp100.000: total dibayar Rp1.000.000, saldo nol, scan berikutnya ditolak; Scanner B tetap dapat melayani jika punya kas.
7. Tes waktu 09.00 (tanpa potongan) dan 09.01 (menit potongan ke-1).
8. Keuangan hitung kas fisik vs sistem dan lakukan penutupan; tes kasus selisih.
9. Admin impor ulang saldo tahun baru; riwayat snapshot 2026 tidak berubah.
10. Validasi SHU hanya Admin, papan skor agregat, doorprize dan ekspor laporan.

## Form bukti
Setiap kasus: ID, waktu, petugas, screenshot/log bukti, hasil aktual, expected, status Pass/Fail, catatan, penanggung jawab perbaikan.

Uji offline tidak dilakukan karena pemindaian berhenti total saat koneksi putus (D-032); Mode Presensi 2x diuji pada kasus 23.


### UAT — Log Cek SHU 14 Hari
Uji NBA dan CAPTCHA, pastikan log terbentuk tanpa nominal SHU dan hanya Admin dapat melihat. Siapkan data uji log berusia 13, 14, dan 15 hari (gunakan waktu terkontrol); jalankan scheduler/command dan pastikan hanya baris yang benar-benar lebih tua dari 14 hari dihapus. Pastikan data `shu` tahun buku lama serta audit koreksi Admin tidak berubah.


## UAT Integrasi (v2.2)
1. Admin membuka Pengaturan → Integrasi, memasukkan CAPTCHA site/secret, menyimpan, menguji challenge yang nyata; setelah itu Cek SHU dapat dilanjutkan.
2. Admin memasukkan token Fonnte lalu menyimpan: tidak ada pesan terkirim. Uji dengan nomor pribadi yang dipilih Admin, konfirmasi, dan lihat status percobaan.
3. Logout dan login sebagai Helpdesk: menu integrasi tidak tampil, direct URL juga ditolak.
4. Admin menonaktifkan CAPTCHA: halaman Cek SHU tidak lagi memproses lookup, dengan pesan layanan sedang tidak tersedia.
5. Ganti token Fonnte, pastikan token lama tak terlihat dan aktivitas audit mencatat rotasi tanpa nilainya.

## UAT fitur Fase 7-8 (sudah dibangun)

| # | Kasus | Petugas | Hasil yang diharapkan |
|---|---|---|---|
| 11 | Papan kuorum `/kuorum` | Admin, Keuangan | Angka hadir, populasi, target, dan status sah sama dengan hitungan manual; Helpdesk dan Scanner mendapat 403 |
| 12 | Konfirmasi terima kas | Scanner | Sebelum menekan **Saya sudah menerima kas**, scan ditolak dengan pesan konfirmasi; setelah konfirmasi, scan berjalan dan status kas `aktif` |
| 13 | Tutup kas tanpa selisih | Keuangan | Status `ditutup`, sisa fisik tersimpan, tidak perlu catatan |
| 14 | Tutup kas dengan selisih | Keuangan | Selisih bukan nol ditolak bila catatan kosong; dengan catatan tersimpan dan masuk Audit |
| 15 | Impor & buka SHU | Admin | Impor XLSX per tahun buku, tombol **Buka cek publik**, halaman `/cek-shu` melayani NBA + CAPTCHA |
| 16 | Cek SHU publik | Publik | Hasil menampilkan NBA, dua komponen, dan total tanpa nama anggota; log 14 hari terbentuk tanpa nominal |
| 17 | Doorprize | Admin | Pop-up **masih ada** = menang, **sudah pulang** = gugur; jumlah pemenang mengikuti parameter rapat |
| 18 | Laporan per rapat | Admin, Keuangan, Helpdesk | Admin melihat semua bagian; Keuangan hanya presensi, kas, kuorum; Helpdesk hanya peserta dan undangan; Scanner 403 |
| 19 | Ekspor XLSX laporan | Admin | Berkas `laporan-rat-<tanggal>.xlsx` terunduh dan angkanya sama dengan halaman |
| 20 | Cadangan & pulihkan | Admin teknis | `php artisan erat:backup` menghasilkan `.sql` + arsip media; restore ke database uji menghasilkan jumlah baris yang sama |
| 21 | Benchmark | Admin teknis | `php artisan erat:benchmark` menampilkan waktu kuorum; target maksimal 10 detik terpenuhi |
| 22 | Uji kamera QR di laptop | Scanner | Buka `http://localhost:8000/kas-saya` (hasil `php artisan serve`) atau `http://rapat.localhost`, izinkan kamera, arahkan ke QR undangan; kolom token manual tetap bisa dipakai bila kamera ditolak |
| 23 | Mode Presensi 2x (Datang-Pulang) | Scanner A, Scanner B | Tab **1. Datang** mencatat hadir + foto/TTE tanpa uang; tab **2. Pulang & Bayar** dibayar dari kas Scanner B; peserta yang tidak pulang tetap dihitung kuorum tetapi transportnya hangus (D-034) |
| 24 | Manajemen pengguna | Admin | Tambah akun Scanner dengan username, ubah nama/username/role/sandi akun uji, lalu hapus; perubahan tercatat di Audit tanpa sandi; akun Admin sendiri tidak dapat dihapus/diturunkan |
| 25 | Helpdesk tidak dapat presensi | Helpdesk | Menu/route presensi tidak tersedia dan request langsung ditolak; Helpdesk hanya menyelesaikan masalah verifikasi lalu mengarahkan peserta kembali ke Scanner (D-036) |
| 26 | Input dan koreksi SHU manual | Admin | Input NBA untuk tahun buku, lalu koreksi dua komponen dengan alasan; total berubah dan Audit memuat nilai sebelum/sesudah serta alasan |
| 27 | Papan kuorum publik | Publik | `/papan-kuorum` terbuka tanpa login, angka sama dengan `/kuorum`, diperbarui otomatis, dan tidak memuat nama/NBA/foto/TTE/nominal atau daftar peserta |
| 28 | Ringkasan kuorum Scanner | Scanner | Dashboard menampilkan hadir, target, populasi, persentase, kekurangan, dan status sah yang sama dengan hitungan Admin/Keuangan |
| 29 | Ringkasan rapat sesuai role | Helpdesk, Scanner, Keuangan | Helpdesk melihat kriteria; Scanner melihat mode/gerbang; Keuangan melihat konfigurasi transport; tidak ada aksi ubah |
| 30 | Pembatasan master anggota | Helpdesk, Keuangan | Helpdesk dapat mencari/verifikasi tetapi request perubahan master ditolak; Keuangan tidak dapat membuka halaman master |
| 31 | Navigasi dan navbar Pengaturan | Admin | Sidebar terbagi Utama/Operasional/Pelaporan/Sistem dan hanya memuat satu tautan Pengaturan; halaman Pengguna, Integrasi, Aplikasi memakai pills horizontal yang sama dan bisa digeser di layar kecil |
| 32 | Login username | Semua petugas | Login berhasil dengan username dan sandi; input email tidak diterima; username tidak membedakan huruf besar/kecil |

## Form bukti UAT (isi saat hari-H)

| ID | Waktu | Petugas | Bukti (screenshot/log) | Hasil aktual | Expected | Status | Catatan | PIC perbaikan |
|---|---|---|---|---|---|---|---|---|
|  |  |  |  |  |  |  |  |  |

