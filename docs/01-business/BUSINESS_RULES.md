# BUSINESS RULES — Aturan Inti eRAT

> **Status:** Dokumen aktif, diperbarui 7 November 2026. Keputusan FINAL terbaru berada di `DECISION_LOG.md`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## BR-001 Identitas anggota
`no_anggota` tepat 15 digit ASCII 0–9, `VARCHAR(15)` UNIQUE. Formatter UI: 6.3.3.3. Cari bisa dengan/ tanpa titik; simpan selalu tanpa titik, tidak cast integer. Perubahan NBA Admin berizin dan diaudit.
Helpdesk dapat mengoreksi nomor WhatsApp, email, jenis anggota, status keanggotaan, dan alamat; kolom lain tetap Admin-only. Semua koreksi dicatat dalam log audit (D-050).

## BR-002 Rapat
Rapat memiliki nama, jenis, tanggal pelaksanaan, tanggal acuan saldo, jam mulai presensi, jam mulai acara, jam selesai opsional, nama lokasi, alamat lengkap lokasi, catatan ketentuan rapat opsional, mode presensi, konfigurasi kuorum, tier transport, dan mode kelayakan. Tanggal acuan saldo tidak boleh setelah tanggal rapat (D-051/D-052). Identitas serta ketiga jam berada pada Informasi Umum; tanggal acuan saldo berada pada Kriteria Kelayakan. Tahap Draf → Verifikasi → Berlangsung → Selesai, alternatif Dibatalkan. Admin membuka presensi secara hybrid sebelum acara bila sudah siap; sistem tidak otomatis membuka hanya karena jam tiba. Perubahan parameter dilarang saat Berlangsung; selama Verifikasi perubahan kriteria wajib prosedur tercatat dan penilaian ulang terkontrol (usulan). Tahun buku hanya digunakan oleh modul SHU.

## BR-003 Kelayakan
- Mode A = simpanan wajib memenuhi minimum proporsional DAN kolektibilitas di bawah/sama batas rapat. Kolektibilitas `0` adalah kode internal untuk anggota yang tidak memiliki pinjaman dan selalu memenuhi ambang kolektibilitas 1–5 (D-049).
- Mode B = simpanan wajib memenuhi minimum proporsional DAN `pinjaman_lancar = true`. Nilai `true` mencakup anggota dengan pinjaman lancar **atau tidak memiliki pinjaman**; nilai `false` hanya untuk anggota dengan pinjaman tidak lancar/bermasalah (D-049).
- Status anggota Aktif tidak ikut sebagai parameter Layak/Tidak Layak.
- Admin bisa mengonfigurasi parameter per rapat, tetapi setiap kriteria yang dinyalakan harus dapat diuji.
- Proporsional: tarif/bulan × jumlah bulan kewajiban sampai Tanggal Acuan Saldo, dihitung inklusif dari Januari atau bulan `tgl_ja` (mana yang lebih akhir), dibatasi 12 bulan, dan tidak pernah melebihi minimum tahunan rapat. Saldo dibandingkan adalah saldo pada tanggal acuan yang sama. `tgl_ja` kosong membuat peserta **Tidak Layak** dengan alasan `Tanggal jadi anggota belum lengkap`; `tgl_ja` setelah tanggal acuan juga **Tidak Layak** (D-033/D-051).

## BR-004 Verifikasi
Semua calon peserta pending. Petugas Admin/Helpdesk memeriksa; sistem memberi hasil Layak/Tidak Layak otomatis; petugas menetapkan Verified atau Rejected, dengan alasan jika Tidak Layak tetapi Verified. Jika Verified, buat tepat satu token QR acak aman untuk `(rapat_id, anggota_id)`; pengiriman/cetak ulang menggunakan token rapat yang sama. Semua keputusan dilog.

## BR-005 Undangan dan laporan
Simpan percobaan WA dan cetak dalam `log_undangan` berisi peserta, rapat, petugas, metode, waktu, dan hasil. Status WA 'terkirim' versus 'diterima' harus mengikuti bukti penyedia; tidak mengarang status pengantaran. Riwayat Undangan boleh mengelompokkan aksi per anggota untuk tampilan, tetapi setiap aksi tetap tersimpan sebagai baris tersendiri.

## BR-006 Gerbang scan
Syarat: QR sesuai rapat, peserta Verified, presensi sudah dibuka Admin (D-021), rapat belum Selesai/Dibatalkan, dan **status anggota terbaru Aktif**. Anggota yang menjadi Nonaktif setelah mendapat QR ditolak. Cegah presensi duplikat. Foto peserta (kamera perangkat) dan tanda tangan (canvas) **wajib** ada sebelum presensi dicatat; bila tidak valid, presensi dibatalkan seluruhnya (D-023). Presensi hanya dapat dicatat Scanner; Helpdesk tidak mempunyai aksi presensi atau presensi manual (D-036). Saat koneksi perangkat putus, pemindaian **dihentikan total**: tidak ada antrean offline, draf, atau pencatatan manual, dan tidak ada uang yang diserahkan (D-032). Pada Mode Presensi 2x, pemindaian pulang memakai gerbang yang sama dan hanya memerlukan token QR (D-034).

## BR-007 Dua mode presensi
Mode Presensi 1x: datang, foto, tanda tangan, transport dibayar saat itu juga. Mode Presensi 2x (Datang-Pulang): datang mencatat kehadiran, foto dan tanda tangan wajib, nominal transport dihitung lalu **dikunci** tanpa uang diserahkan dan tanpa memakai kas; pulang memindai token QR lagi dan membayar nominal terkunci itu dari kas Scanner yang mencatat pulang (boleh Scanner berbeda). Pulang dan pembayaran berhasil bersama atau tidak sama sekali, satu pembayaran per peserta. Peserta yang datang tetapi tidak pernah pulang tetap dihitung hadir untuk kuorum dan hak transportnya hangus; pemindaian pulang masih dapat dilakukan sampai Admin menetapkan rapat Selesai atau Dibatalkan. Admin memilih mode per rapat (D-034 menggantikan D-024).

## BR-008 Tarif transport
Nominal akhir = max(0, nominal dasar − potongan tier). `menit_kena_potongan = max(0, menit_dari_jam_mulai_acara − toleransi_menit)`. Tepat batas toleransi belum dipotong; 1 menit berikutnya masuk tier. Jam presensi tidak digunakan sebagai acuan keterlambatan. Tier, hangus, dan nominal diatur Admin per rapat. Rentang tier wajib bersambung mulai menit ke-1 setelah toleransi; celah atau tumpang tindih ditolak saat menyimpan. Kedatangan setelah menit akhir tier terakhir memakai potongan tier terakhir, sehingga penalti tidak pernah kembali ke nol (D-047). Bila nominal akhir Rp0 (D-022), presensi tetap dicatat, tidak ada baris pembayaran, dan kas tidak berkurang.

## BR-009 Kas Scanner
Satu kas awal per `(rapat_id, scanner_user_id)` dan tidak ada top-up/transfer kas. Keuangan menyerahkan, memantau dan merekonsiliasi; Scanner membayar pada alur presensi. Saldo tersedia = kas awal - pembayaran sukses. Mode Presensi 2x tidak memesan dana saat datang; kas baru berkurang saat pembayaran pulang (D-034). Jika saldo tidak cukup untuk nominal yang menjadi kewajibannya, scan/presensi diblokir sebelum pencatatan berhasil; peserta diarahkan ke Scanner lain. Tidak boleh membuat presensi sukses berutang transport. Peringatan saldo menipis muncul bila sisa < `batas_peringatan_kas` (bawaan 5 × `transport_dasar`, diatur Admin per rapat) dan bersifat informasi, tidak memblokir scan (D-025). Scanner wajib mengonfirmasi penerimaan kas sebelum memindai, dan penggantian Scanner menutup kas lama tanpa memindahkan saldo (D-026). Kas yang belum dikonfirmasi berstatus `menunggu` dan tidak dapat dipakai memindai; setelah dikonfirmasi menjadi `aktif`; setelah ditutup menjadi `ditutup` dan tidak berubah lagi. Penutupan dilakukan Keuangan per Scanner: `sisa seharusnya = kas_awal − pembayaran tercatat`, lalu dibandingkan dengan `sisa_fisik`. Selisih bukan nol **wajib diberi alasan tertulis dan diaudit**; tidak ada langkah persetujuan Admin (keputusan 3 November 2026, D-031).

## BR-010 Kuorum
Hanya anggota jenis `biasa` dihitung. Target = floor(persen × basis populasi) + tambahan, semua nilai per rapat. Basis total/terverifikasi disetel Admin. Angka agregat dari sumber hitung yang sama ditampilkan untuk Admin/Keuangan, sebagai ringkasan Dashboard Scanner, dan pada papan publik `/papan-kuorum`; tampilan publik tidak boleh memuat nama, NBA, foto, tanda tangan, nominal transport, atau baris presensi individu. Tampilan diperbarui otomatis setiap 10 detik.

## BR-011 SHU dan doorprize
SHU dikelola hanya Admin, dapat dicek publik dengan kontrol keamanan. Doorprize oleh Admin dari peserta hadir yang sesuai ketentuan rapat. Pemeriksaan kehadiran tetap lewat popup "masih ada / sudah pulang"; pemenang yang gugur tidak boleh ikut undian berikutnya, dan transport tidak dibayarkan untuk peserta yang tidak pulang (D-034).

**Keputusan 3 November 2026 (FINAL):**
- Data SHU dapat masuk lewat **input manual atau impor XLSX per tahun buku**. Koreksi manual wajib alasan dan audit nilai sebelum/sesudah (D-038). Kolom berkas: `no_anggota`, `balas_jasa_saham`, `balas_jasa_pinjaman`. Total per anggota = jumlah kedua komponen, tidak disimpan ganda.
- Impor ulang tahun buku yang sama memperbarui baris `(anggota_id, tahun_buku)` yang sudah ada; tahun buku lain tidak tersentuh dan hasil rapat lama tidak dihapus. NBA yang belum ada di master anggota ditolak per baris.
- Cek SHU publik **tidak terikat status rapat**. Admin membukanya dengan tombol eksplisit per tahun buku setelah data final, dan dapat menutupnya kembali. Selama saklar tertutup, pencarian publik tidak dilayani.
- Pencarian publik hanya memakai **NBA + CAPTCHA**; tidak ada faktor kedua.

**Doorprize (D-044, FINAL):**
- Admin menyiapkan daftar hadiah per rapat: nama, jumlah unit, dan keterangan opsional; tidak ada urutan hadiah atau parameter jumlah pemenang terpisah.
- Master hadiah dikelola pada `/doorprize`, sedangkan layar acara undian berada terpisah pada `/doorprize/undian` (D-046).
- Admin memilih hadiah yang masih tersedia pada halaman undian. Kandidat hanya peserta yang **sudah tercatat hadir** dan belum memiliki hasil doorprize pada rapat itu.
- Undian hanya aktif pada status **Verifikasi setelah presensi dibuka** atau **Berlangsung**; Draf, Selesai, dan Dibatalkan ditolak server.
- Satu peserta hanya boleh memperoleh satu hadiah. **Masih ada** = menang dan mengisi satu unit; **sudah pulang** = gugur tanpa mengurangi sisa hadiah. Keduanya tidak muncul lagi pada undian berikutnya.
- Sisa hadiah dihitung dari jumlah awal dikurangi pemenang sah, bukan dengan mengubah stok awal. Hadiah habis tidak dapat dipilih tetapi tetap terlihat pada hasil/laporan.
- Hadiah tetap dapat diedit; jumlah tidak boleh lebih kecil dari jumlah pemenang dan hadiah yang sudah memiliki hasil tidak boleh dihapus. Hasil menyimpan snapshot nama hadiah.
- Semua perubahan hadiah dan hasil undian masuk `log_audit`; transaksi dan penguncian database mencegah pembagian melebihi jumlah.

## BR-012 Audit dan arsip
Impor ulang saldo tahunan tidak menghapus hasil rapat lama. Simpan snapshot simpanan, tanggal menjadi anggota, kriteria, hasil kelayakan, dan keputusan petugas per rapat; simpan semua pembayaran secara append-only/terlacak. Pengubahan status keanggotaan/nomor, presensi, kas, atau SHU diaudit. Akun petugas yang sudah memiliki riwayat operasional tidak boleh dihapus lewat manajemen pengguna; akun tersebut dinonaktifkan untuk menghentikan akses tanpa menghilangkan identitas (D-035/D-042). Akun tanpa riwayat tetap boleh dihapus. Pengecualian satu-satunya adalah reset total yang dilakukan Admin secara eksplisit untuk memulai aplikasi dari nol sesuai D-037; reset ini bukan koreksi operasional biasa.


## BR-SHU-LOG-01 — Retensi pencarian SHU publik (FINAL)
Setiap percobaan cek SHU yang mencapai endpoint pencarian dicatat dengan waktu, NBA yang dicari (akses khusus Admin), tahun buku, sumber/IP sesuai kebijakan, dan hasil, tanpa menyimpan nominal SHU di log. Log pencarian SHU publik disimpan selama **14 hari** dan baris dengan `created_at` lebih lama dari 14 × 24 jam dibersihkan otomatis setiap hari. Retensi ini tidak menghapus tabel `shu`, data per tahun buku, ataupun audit perubahan SHU oleh Admin. CAPTCHA diverifikasi pada server dan rate limiting diterapkan **sebelum** query SHU; CAPTCHA tidak memverifikasi kepemilikan NBA. Risiko bahwa orang lain yang mengetahui NBA dapat melihat nominal SHU harus dinilai dan disetujui sebelum halaman dipublikasikan.


## BR-INT-01 — Konfigurasi CAPTCHA dan Fonnte dari aplikasi [FINAL]
Hanya Admin mengatur CAPTCHA dan Fonnte melalui **Pengaturan → Integrasi**. Fitur tidak membutuhkan pengeditan `.env` untuk mengganti kunci integrasi. CAPTCHA pada Cek SHU diverifikasi di backend dengan kunci aktif tersimpan terenkripsi; bila belum siap atau gagal, pencarian ditolak tertutup (fail closed). Pengiriman undangan WhatsApp lewat Fonnte hanya dipicu setelah tindakan petugas yang berwenang; kegagalan integrasi tidak menggagalkan status Verified dan harus tercatat sebagai upaya kirim gagal. Uji kirim pesan hanya dilakukan dengan konfirmasi eksplisit Admin. Rahasia tidak boleh tampil penuh, ter-log, atau ikut ekspor. Lihat `INTEGRATION_SETTINGS.md`.
