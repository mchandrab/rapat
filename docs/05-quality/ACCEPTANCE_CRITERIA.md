# ACCEPTANCE CRITERIA — Checklist Penerimaan

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Master & verifikasi
- [ ] NBA tepat 15 digit tersimpan tanpa titik, ditampilkan 6.3.3.3, pencarian menerima kedua format.
- [ ] Impor tahun berikutnya memperbarui master tanpa menggandakan NBA maupun mengubah snapshot RAT lalu.
- [ ] `tgl_ja` dan `tgl_keluar` tersedia; perhitungan proporsional mengacu akhir tahun buku.
- [ ] Mode A memeriksa simpanan + kolektibilitas; Mode B memeriksa simpanan + kelancaran pinjaman; status Aktif bukan parameter kelayakan.
- [ ] Hasil Layak/Tidak Layak otomatis; Verified oleh Admin/Helpdesk; Tidak Layak tetapi Verified wajib alasan.
- [ ] Token QR unik untuk setiap peserta per rapat, terbit setelah Verified saja dan tetap sama pada kirim/cetak ulang.
- [ ] Seluruh kirim WA/cetak tercatat petugas, waktu, metode dan status; laporannya tersedia.

## RAT, presensi, dan keamanan
- [ ] Jam mulai presensi dapat dibedakan dari jam mulai RAT; Admin membuka operasional secara hybrid.
- [ ] Pengaturan rapat dikunci saat Berlangsung.
- [ ] Anggota Nonaktif meskipun QR pernah diterbitkan tidak bisa scan.
- [ ] Toleransi dihitung sebagai menit setelah batas toleransi; 09:01 pada acara 08:00 + toleransi 60 = 1 menit masuk tier potongan.
- [ ] Dua mode dinamai Mode Presensi 1x dan Mode Presensi 2x (Datang–Pulang).
- [ ] Hak akses foto/TTE dan transaksi diuji pada server, termasuk tampering.
- [ ] Helpdesk tidak mempunyai route/aksi presensi, presensi manual, atau koreksi presensi; request langsung tetap ditolak server (D-036).
- [ ] Helpdesk tidak dapat mengubah master anggota dan Keuangan tidak dapat membuka halaman master; request langsung ditolak server (D-039).
- [ ] Dashboard Helpdesk, Scanner, dan Keuangan hanya menampilkan ringkasan rapat baca-saja sesuai tugas masing-masing.
- [ ] Sidebar terbagi menurut alur kerja dan hanya memiliki satu tautan Pengaturan; halaman Pengguna, Integrasi, dan Aplikasi memakai navbar horizontal `nav-overflow` mode pills yang sama.

## Kas Scanner
- [ ] Kas terikat ke akun user Scanner untuk RAT, bukan meja.
- [ ] Satu kas awal per Scanner per RAT; tidak ada tambah kas, top-up atau transfer.
- [ ] Peringatan saldo menipis ditampilkan ketika ambang konfigurasi tercapai.
- [ ] Jika kas tidak cukup, scan/presensi yang memerlukan pembayaran tidak berhasil; tak ada status sukses/transport belum dibayar.
- [ ] Saldo tak pernah negatif, pembayaran ganda ditolak, laporan terperinci per Scanner.
- [ ] Rekonsiliasi sisa kas fisik per Scanner dan catatan selisih tersedia.

## Lain-lain
- [ ] SHU hanya dapat dikelola Admin; pengecekan publik dibatasi dan diaudit.
- [ ] Admin dapat input SHU manual dan mengoreksi nominal dengan alasan; audit menyimpan nilai sebelum dan sesudah tanpa menghapus riwayat tahun lain.
- [ ] Doorprize, kuorum dan laporan berjalan sesuai kriteria.
- [ ] `/papan-kuorum` dapat dibuka tanpa login, memakai angka agregat yang sama dengan layanan `Kuorum`, dan tidak menampilkan nama, NBA, foto, TTE, nominal transport, atau baris presensi individu.
- [ ] Dashboard Scanner menampilkan ringkasan kuorum yang diperbarui otomatis setiap 10 detik; keadaan belum ada rapat tetap jelas.
- [ ] Tidak ada test gagal; UAT petugas ditandatangani.

## Manajemen pengguna
- [ ] Hanya Admin dapat membuka dan mengubah akun petugas.
- [ ] Role hanya `admin`, `helpdesk`, `scanner`, dan `keuangan`; anggota bukan akun login.
- [ ] Login dan manajemen akun memakai username unik, bukan email; username dinormalisasi ke huruf kecil dan karakter dibatasi.
- [ ] Admin dapat menambah, mengubah, mengganti sandi, dan menghapus akun petugas lain; sandi tidak pernah masuk audit.
- [ ] Admin tidak dapat menghapus atau menurunkan role akun sendiri; minimal satu Admin selalu tersisa.
- [ ] Reset total hanya membuat ulang akun username `admin`, lalu akun petugas lain dibuat melalui menu Pengguna.

**Mode Presensi 2x (Datang-Pulang) berlaku: datang mengunci nominal tanpa pembayaran, pulang membayar dari kas Scanner yang mencatat pulang, dan transport hangus bila peserta tidak pulang (D-034). Offline tetap tidak dikerjakan (D-032).**


### Cek SHU — Retensi 14 hari
- [ ] Admin saja dapat membaca riwayat pencarian SHU; publik/role lain ditolak.
- [ ] Lookup publik tercatat tanpa nominal SHU, serta diberi CAPTCHA tervalidasi server-side dan rate limiting.
- [ ] Scheduler terpasang dan setiap hari menghapus **hanya log pencarian SHU** lebih tua dari **14 hari**; log lebih baru tidak terhapus.
- [ ] Riwayat edit/impor SHU oleh Admin, isi tabel `shu`, dan SHU tahun buku sebelumnya tidak terhapus oleh purge.
- [ ] Risiko privasi NBA + CAPTCHA tanpa verifikasi kepemilikan dinilai sebelum fitur dipublikasikan.


## AC-INT — Pengaturan Integrasi (v2.2)
- [ ] Admin dapat mengatur provider, site key dan secret CAPTCHA serta token API Fonnte dari menu **Pengaturan → Integrasi**, tanpa mengedit `.env`.
- [ ] Secret/token terenkripsi di DB dan tidak dapat ditampilkan kembali; UI hanya menunjukkan status tersimpan.
- [ ] Role selain Admin tidak dapat mengakses seluruh halaman/API perubahan maupun uji koneksi.
- [ ] CAPTCHA server-side wajib valid pada Cek SHU; layanan ditolak bila integrasi tidak siap, tidak bypass.
- [ ] Simpan Fonnte tidak mengirim pesan; pengujian ke nomor yang Admin tentukan harus melalui konfirmasi eksplisit.
- [ ] Pengiriman WA gagal mencatat status gagal tetapi Verified tetap; status diterima API tidak berarti delivered.
- [ ] Retensi log SHU tetap 14 hari; data SHU utama tidak ikut dihapus.
