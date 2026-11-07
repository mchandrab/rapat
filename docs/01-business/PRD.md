# PRD — Kebutuhan Produk eRAT

> **Status:** Dokumen aktif, diperbarui 7 November 2026. Mengikuti `DECISION_LOG.md` dan `BUSINESS_RULES.md`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Tujuan & ruang lingkup
Sistem web mandiri untuk mempersiapkan dan melaksanakan seluruh rapat anggota Koperasi Kredit Rukun: impor data anggota, verifikasi layanan dan undangan, QR per rapat, presensi, kuorum, kas transport per Scanner, SHU dikelola Admin, doorprize, laporan, dan audit.

## Stakeholder
- Admin: pengaturan, master anggota, aturan per rapat, verifikasi, koreksi, SHU, doorprize, audit dan seluruh laporan.
- Helpdesk: pencarian, penilaian yang ditampilkan sistem, keputusan verifikasi, kirim/cetak undangan, dan bantuan kasus; dapat mengoreksi nomor WhatsApp, email, jenis anggota, status keanggotaan, dan alamat dengan audit. Helpdesk tidak dapat melakukan presensi atau mengubah kolom master lain, saldo, parameter rapat, maupun status sensitif (D-050).
- Scanner: presensi rapat aktif, foto/TTE, pembayaran kas yang dialokasikan ke akun sendiri; tidak dapat mengubah nominal atau mengelola master.
- Keuangan: pembagian kas awal ke akun Scanner, monitoring pembayaran, rekonsiliasi dan laporan transport; identitas anggota hanya melalui presensi/laporan, tanpa halaman master, izin SHU, atau hak mengubah tarif (D-039).
- Anggota: tanpa login; menunjukkan QR, menandatangani, dan melihat halaman publik terbatas.

## Kebutuhan fungsional (FR)
| ID | Fitur | Keluaran wajib |
|---|---|---|
| FR-001 | Master anggota | NBA 15 digit, `tgl_ja`, `tgl_keluar`, status terkini, impor berulang yang update anggota sama |
| FR-002 | Setup rapat | Informasi Umum memuat nama/jenis/tanggal, tiga jam, nama lokasi dan alamat lengkap; Kriteria Kelayakan memuat Tanggal Acuan Saldo; mode 1x/2x, kuorum, tier, status & kunci parameter |
| FR-003 | Kelayakan | Mode A atau B, minimum simpanan proporsional sampai Tanggal Acuan Saldo per rapat, indikator Layak/Tidak Layak |
| FR-004 | Verifikasi | Pending/Verified/Rejected per rapat, bukti alasan pengecualian Tidak Layak |
| FR-005 | Undangan | QR unik per peserta per rapat hanya setelah Verified, WA/cetak, log petugas & laporan |
| FR-006 | Presensi | QR valid, status anggota Aktif terkini, foto dan tanda tangan, tidak duplikat, dua mode, blokir kas tidak cukup |
| FR-007 | Kuorum | Perhitungan realtime anggota biasa, papan agregat Admin/Keuangan, ringkasan Scanner, dan papan publik tanpa data pribadi |
| FR-008 | Kas Scanner | Satu kas awal/Scanner/rapat, tanpa tambahan, peringatan menipis, pembayaran oleh Scanner, rekonsiliasi |
| FR-009 | SHU | Input/impor/edit Admin; cek publik terlindungi; audit |
| FR-010 | Doorprize | Admin mengelola daftar hadiah di `/doorprize` dan mengundi hadiah terpilih pada layar khusus `/doorprize/undian` untuk peserta hadir saat Verifikasi setelah presensi dibuka atau saat Berlangsung; satu peserta satu hadiah, stok dan hasil diaudit |
| FR-011 | Laporan | Peserta, verifikasi, undangan per petugas, presensi, kas & pembayaran per Scanner, kuorum, SHU dan doorprize |
| FR-012 | Keamanan | RBAC server, pembatasan login, file privat, log audit, pencegahan token enumeration, offline aman |
| FR-014 | Manajemen pengguna | Admin mengelola akun petugas, status aktif/nonaktif, dan empat role tetap; sandi ter-hash, perubahan diaudit, akun Admin sendiri dan akun bersejarah dilindungi |

## Non-fungsional
Bahasa UI Indonesia dan jelas untuk petugas lanjut usia; mobile-friendly; query dan transaksi presensi atomik; event kas dan audit tidak terhapus diam-diam; backup periodik; test keamanan/normal/batas. Target performa lama (alur scanner <30 detik, dashboard kuorum <=10 detik) dipertahankan sebagai target uji, perlu pembuktian di perangkat nyata.

## Tidak termasuk
Portal login anggota; multi-koperasi; integrasi database langsung SIKOPDIT; broadcast undangan otomatis; tambah/top-up atau transfer kas Scanner; akses SHU bagi Keuangan.

## Ketergantungan keputusan
Presensi bernilai Rp0 tetap dicatat tanpa pembayaran (D-022). Tidak ada implementasi offline: saat koneksi putus pemindaian dihentikan total (D-032). Mode Presensi 2x (Datang-Pulang) tersedia per rapat: datang mengunci nominal tanpa pembayaran, pulang membayar dari kas Scanner yang mencatat pulang (D-034).


### FR-013 — Retensi log cek SHU publik
Sistem menyimpan setiap upaya pencarian SHU publik dalam `log_cek_shu` dengan waktu, NBA, tahun buku, identifikasi sumber/IP sesuai kebijakan privasi, dan hasil, tanpa nominal SHU. **Retensi 14 hari**, penghapusan otomatis terjadwal sekali setiap hari dan dapat diuji. Data SHU dan log audit perubahan Admin tidak terdampak. Gunakan CAPTCHA yang diverifikasi server-side dan rate limiting. Pencarian berdasarkan NBA saja tidak membuktikan kepemilikan: persetujuan risiko privasi sebelum publikasi tetap diperlukan.


## Pengaturan: Integrasi CAPTCHA dan Fonnte [v2.2]
Admin mempunyai panel Pengaturan → Integrasi dengan dua kartu konfigurasi: CAPTCHA (provider, aktif/nonaktif, site key publik, secret terenkripsi, status konfigurasi, uji token) dan Fonnte WhatsApp (aktif/nonaktif, API token terenkripsi, identitas/label akun bila tersedia, status konfigurasi, uji kirim ke nomor penguji yang dimasukkan secara eksplisit). Menyimpan form tidak otomatis mengirim pesan. Credentials baru mengganti nilai lama secara atomik; kolom kosong saat edit berarti **pertahankan secret lama** dan tindakan khusus diperlukan untuk menghapus/mengganti. Konfigurasi hanya dapat diubah Admin dan seluruh perubahan non-rahasia tercatat dalam audit. Cek SHU wajib fail-closed saat CAPTCHA tidak tersedia. Tidak ada kewajiban menyimpan credential dua layanan pada `.env`.

## Pengaturan: Manajemen Pengguna [v2.3]
Admin mengelola akun petugas lewat **Pengaturan - Pengguna**: daftar akun, tambah, ubah nama/username/role, ganti sandi, aktif/nonaktif, dan hapus akun yang belum memiliki riwayat operasional. Akun bersejarah dinonaktifkan agar akses berhenti tetapi identitas petugas, kas, pembayaran, dan arsip lain tetap utuh (D-035/D-042/BR-012). Akun nonaktif tidak dapat login atau menerima alokasi kas baru. Login memakai username unik, bukan email (D-041). Role hanya empat pilihan tetap (`admin`, `helpdesk`, `scanner`, `keuangan`); tidak ada role buatan sendiri dan anggota koperasi tidak dibuatkan akun. Admin tidak dapat menghapus, menonaktifkan, atau menurunkan role akun sendiri, dan sistem menjaga minimal satu Admin aktif. Sandi baru minimal 8 karakter, disimpan ter-hash, dan tidak pernah ditulis ke log audit. Reset total hanya membuat ulang akun Admin aktif (D-035).
