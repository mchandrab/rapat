# eRAT — Keputusan Bisnis dan Rancangan Operasional Revisi

**Status:** Draf keputusan terkonfirmasi + usulan yang masih perlu persetujuan  
**Tanggal:** 28 Oktober 2026  
**Lingkup:** Koperasi Kredit Rukun  
**Tujuan:** Sumber acuan saat merevisi PRD, USER_FLOW, ROLE_PERMISSION_MATRIX, DATABASE_SCHEMA, ARCHITECTURE, DESIGN_SYSTEM, TESTING, ACCEPTANCE_CRITERIA, IMPLEMENTATION_PLAN, dan AGENTS. Dokumen ini bukan pengganti revisi penuh sepuluh dokumen tersebut.

## A. Keputusan yang sudah dikonfirmasi pengguna

1. **Hak atur transport**: Admin menetapkan nominal dasar, toleransi, tingkat potongan, dan aturan hangus. Keuangan tidak dapat mengubah aturan tersebut.
2. **Role keuangan**: Tidak mengelola SHU. Keuangan menangani kas awal per meja, pengawasan kas keluar, pengembalian uang sisa, pemeriksaan selisih, rekonsiliasi dan laporan kas/transport.
3. **Terminologi baku**: `Mode Presensi 1x` dan `Mode Presensi 2x (Datang–Pulang)`. Hindari singkatan `Mode 1x` / `Mode 2x` tanpa kata 'Presensi'.
4. **Toleransi dihitung sebelum tier potongan**: batas toleransi masih tanpa potongan; satu menit melewati batas masuk ke menit kena potongan ke-1.
5. **QR khusus per rapat**: Undangan untuk anggota yang sama pada rapat berbeda menggunakan token berbeda; kirim/cetak ulang untuk rapat yang sama menggunakan token yang sama.
6. **Verifikasi undangan adalah pelayanan langsung**: Biasanya H-30 sampai H-7, anggota meminta undangan di tempat pelayanan, petugas mencari dan memeriksa kelayakannya. Jika layak, petugas menyetujui verifikasi dan langsung mengirim/cetak undangan. Jika tidak layak tetapi diberi undangan, alasan pertimbangan wajib dicatat. Status dan jejak petugas tersimpan.
7. **Pengaturan rapat dan undangan tidak menunggu verifikasi massal**: Hasil verifikasi diselesaikan per anggota, diikuti penerbitan undangan anggota itu saat itu juga. Tidak boleh ada persyaratan bahwa seluruh daftar calon peserta sudah diverifikasi.
8. **Peserta tidak login**: Empat role login tetap Admin, Helpdesk, Scanner, Keuangan.

## B. Aturan presensi dan transport terperinci

- Jam dasar = tanggal/jam mulai rapat pada konfigurasi yang disahkan Admin.
- `terlambat_total_menit = max(0, selisih menit waktu datang dan jam mulai)`.
- `menit_kena_potongan = max(0, terlambat_total_menit - toleransi_menit)`.
- Jika `menit_kena_potongan = 0`: tidak ada potongan.
- Jika >0: cari tier yang mencakup menit kena potongan, bukan menit terlambat total. Tier harus berurutan, tidak tumpang tindih, dan menutup semua rentang yang digunakan sebelum batas hangus.
- Nominal akhir = `max(0, nominal_dasar - potongan_tier)`; bila batas hangus tercapai, nominal akhir = 0 sesuai definisi inklusivitas batas yang harus ditetapkan Admin.
- Contoh: rapat 08.00, toleransi 60 menit, nominal Rp100.000. Pukul 09.00 -> menit kena potongan 0 -> Rp100.000. Pukul 09.01 -> menit kena potongan 1 -> terapkan tier pertama. Nominal tier pertama *belum ditentukan* dalam keputusan pengguna.
- Waktu presensi yang sudah disahkan menjadi snapshot untuk pembayaran; perubahan aturan rapat setelah pembayaran tidak boleh mengubah transaksi historis secara diam-diam.
- Mode Presensi 1x: scan datang, foto, tanda tangan, penyerahan transport saat kedatangan dan resi.
- Mode Presensi 2x (Datang–Pulang): scan datang, foto, tanda tangan datang; saat pulang scan lagi, tanda tangan penerimaan, transport diserahkan dan resi diterbitkan.

**Masih perlu keputusan:** apakah batas hangus diukur dari total keterlambatan atau menit setelah toleransi; berlaku tepat pada batas atau sesudahnya; dan jadwal potongan rinci.

## C. Alur verifikasi dan undangan di tempat pelayanan

**Prasyarat:** Admin telah membuat RAT, menetapkan aturan, dan membukanya untuk pelayanan/verifikasi. Data master anggota telah diperbarui sesuai prosedur yang sah.

1. Anggota mendatangi kantor/tempat pelayanan dan meminta undangan RAT (umumnya H-30 sampai H-7).
2. Helpdesk/Admin mencari anggota menggunakan NBA atau nama; memeriksa kecocokan identitas dengan anggota yang hadir.
3. Sistem menampilkan status anggota, indikator kelayakan, serta alasan indikator `Layak` atau `Tidak Layak` berdasarkan aturan rapat; petugas tidak dapat mengubah data sensitif dari panel pelayanan.
4. Jika **Layak**: petugas memilih `Verifikasi dan Terbitkan Undangan` setelah pemeriksaan identitas. Sistem menyimpan snapshot indikator, verifikator, waktu, status verified dan riwayat keputusan; membuat undangan untuk rapat ini.
5. Jika **Tidak Layak**, tetapi petugas berwenang memutuskan tetap mengundang: petugas wajib mengisi alasan/pertimbangan, lalu `Verifikasi dengan Pengecualian dan Terbitkan Undangan`. Sistem menyimpan indikator Tidak Layak dan keputusan `verified` sebagai data berbeda; alasan, identitas verifikator, dan waktu wajib diaudit.
6. Jika data belum benar atau keputusan tidak disetujui: tidak menerbitkan undangan; catat kendala/penolakan; perubahan field sensitif dialihkan kepada Admin; bila diperbaiki, kembali ke pemeriksaan ulang dengan jejak lengkap.
7. Untuk peserta yang telah `verified`, petugas memilih `Kirim WhatsApp` atau `Cetak`; tindakan dan keberhasilannya dicatat. Pengiriman ulang untuk peserta+rapat yang sama tidak mengganti QR.
8. Sistem menampilkan hasil: undangan berhasil dikirim/dicetak atau gagal dengan alasan. Bila gagal WA, tersedia jalur cetak; bila printer gagal, bisa mencoba ulang tanpa menerbitkan token baru.
9. Anggota membawa QR rapat pada hari-H. Petugas scanner memvalidasi keanggotaan, rapat aktif, status verifikasi, dan apakah presensi telah dilakukan.

**Catatan aturan:** hasil indikator kelayakan dan keputusan petugas harus dipisahkan. `Tidak Layak` bukan penolakan otomatis ketika petugas berwenang menyetujui pengecualian. Rencana penguncian dan pembukaan kembali status verified harus mencakup rekam audit.

## D. QR unik per rapat

- Token dihasilkan oleh server berdasarkan setidaknya `rapat_id`, identitas peserta (ID stabil), dan bahan rahasia server; atau menggunakan token acak kriptografis yang disimpan sebagai hash. Pilihan implementasi final perlu dinyatakan di ARCHITECTURE + DATABASE_SCHEMA.
- Satu anggota di RAT A dan RAT B: QR berbeda. Cetak ulang RAT A: QR sama.
- QR hanya berlaku untuk RAT yang diterbitkan dan untuk peserta verified; QR rapat lain tidak dapat digunakan.
- URL publik undangan dibatasi hanya informasi yang perlu ditampilkan; tidak membocorkan data keuangan atau status sensitif.
- Siapkan prosedur token bocor/undangan salah kirim: pembatalan/penerbitan ulang yang teraudit, pemberitahuan kepada petugas, dan penolakan token lama. Ini merupakan **usulan**, belum keputusan final.
- Jangan memasukkan NBA atau informasi pribadi secara terbaca langsung ke dalam QR tanpa perlindungan.

## E. Role dan SHU

| Kewenangan | Admin | Helpdesk | Scanner | Keuangan |
|---|---|---|---|---|
| Atur ketentuan transport | Ya | Tidak | Tidak | Lihat saja |
| Verifikasi dan penerbitan undangan | Ya | Ya dalam izin pelayanan | Tidak | Tidak |
| Koreksi field sensitif anggota | Ya (teraudit) | Tidak | Tidak | Tidak |
| Presensi scan | Tidak sebagai alur utama | Pengecualian manual teraudit | Ya | Tidak |
| Serahkan transport di meja scan | Tidak | Tidak | Ya | Tidak |
| Set kas awal, rekonsiliasi kas | Pantau | Tidak | Konfirmasi penerimaan saja | Ya |
| Kelola SHU | **Belum ditetapkan** | Tidak | Tidak | Tidak |
| Lihat audit dan koreksi presensi | Ya | Sangat terbatas | Tidak | Lihat audit kas sesuai izin |

Keputusan bahwa Keuangan tidak mengelola SHU **tidak otomatis** menetapkan siapa pemilik modul SHU. Penanggung jawab penginputan/impor/koreksi SHU dan apakah fitur cek SHU publik tetap di lingkup eRAT harus diputuskan tersendiri.

## F. Kejadian khusus yang wajib ditangani

- Anggota datang tanpa nomor anggota; pencarian nama menghasilkan lebih dari satu orang.
- Anggota tidak layak, tetapi diberi pengecualian; alasan kosong harus ditolak.
- Anggota ditolak lalu datanya diperbaiki dan diverifikasi ulang.
- WA gagal, printer habis kertas, pengguna klik terbitkan dua kali.
- Undangan salah kirim, foto QR disalin, undangan hilang, QR untuk RAT lain.
- Anggota datang ke meja salah atau dua meja scanner bersamaan.
- Foto/tanda tangan gagal, peserta tidak dapat menandatangani; aturan pengecualian perlu persetujuan.
- Jam perangkat tidak tepat, pergantian hari, jaringan fluktuatif.
- Kas meja tidak cukup, pembayaran tercatat tetapi uang belum diserahkan, uang diserahkan tetapi server gagal mencatat.
- Perhitungan potongan tepat pada batas toleransi, batas tier dan batas hangus.
- Pembatalan RAT, perubahan jadwal setelah undangan terbit, rapat ditutup dengan antrean belum tersinkron.

## G. Daftar perubahan terhadap 10 file lama

1. **PRD:** role Keuangan tanpa kelola SHU; proses pelayanan tatap muka; mode presensi; perhitungan toleransi; QR per rapat; offline policy; keputusan modul SHU.
2. **USER_FLOW:** ganti verifikasi daftar massal H-7 s.d. H-1 menjadi layanan per anggota H-30 s.d. H-7 (rentang operasional bisa diatur); terbitkan undangan segera; offline/exception.
3. **ROLE_PERMISSION_MATRIX:** hilangkan kelola SHU Keuangan; perjelas pengecualian Tidak Layak; batasi pengaturan tier pada Admin.
4. **DATABASE_SCHEMA:** ubah QR per anggota menjadi per `rapat+peserta`, indeks unik, status/revokasi bila diputuskan; simpan `menit_terlambat_total`, `toleransi`, `menit_kena_potongan`, tier dan snapshot uang; log pengecualian dan offline.
5. **ARCHITECTURE:** mekanisme token per rapat, validasi atomik anti-duplikasi, transaksi kas, sinkronisasi offline, cap waktu server.
6. **DESIGN_SYSTEM:** label terminologi baku; satu halaman layanan `Cari Anggota -> Periksa -> Verifikasi -> Kirim/Cetak` dan peringatan offline/konflik.
7. **IMPLEMENTATION_PLAN:** perubahan skema token dan alur undangan sejak fase awal; pengujian aturan toleransi dan pengamanan uang sebelum rilis.
8. **AGENTS:** larang role Keuangan mengelola SHU; larang token permanen lintas rapat; wajib tier dari keterlambatan setelah toleransi; jangan menafsirkan ulang keputusan.
9. **TESTING:** tes batas 09.00 vs 09.01; kirim ulang QR sama, rapat berbeda token berbeda; bersamaan antar meja; offline payment, konflik dan idempotensi.
10. **ACCEPTANCE_CRITERIA:** hapus pernyataan hanya peserta indikator Layak menerima undangan; verifikasi dengan alasan pengecualian; tes hak akses; jalur layanan dan offline.

## H. Dokumen tambahan yang direkomendasikan

### Prioritas wajib sebelum coding
1. `BUSINESS_RULES.md`: satu sumber kebenaran aturan, rumus, batas dan contoh.
2. `SOP_PELAYANAN_UNDANGAN.md`: prosedur pelayanan H-30 sampai H-7, koreksi data dan pengiriman/cetak.
3. `SOP_PRESENSI_DAN_TRANSPORT.md`: presensi hari-H, serah uang, penolakan dan troubleshooting.
4. `OFFLINE_SYNC_POLICY.md`: prosedur perangkat offline, antrean, transaksi tunai, idempotensi, resolusi konflik, dan penutupan kas.
5. `DECISION_LOG.md`: keputusan, alasan, status konfirmasi, tanggal, dampak dokumen.
6. `TRACEABILITY_MATRIX.md`: tiap kebutuhan -> tampilan -> aturan -> skema -> tes -> kriteria penerimaan.

### Sebelum pilot/produksi
7. `SECURITY_PRIVACY.md`: akses, perlindungan data wajah/TTE, retensi, backup dan insiden.
8. `DEPLOYMENT_RUNBOOK.md`: instalasi, konfigurasi, restore, monitoring, pergantian rahasia QR, pemulihan jaringan.
9. `UAT_SCENARIOS.md`: simulasi peran dan hari-H bersama petugas nyata, form tanda terima/approval.
10. `INCIDENT_AND_EXCEPTION_PLAYBOOK.md`: penanganan internet mati, listrik mati, uang selisih, undangan bocor, duplikat, gagal WA/printer.
11. `DATA_DICTIONARY.md`: definisi kolom/data, tipe, sumber, validasi, pemilik dan kerahasiaan.

## I. Keputusan terbuka yang perlu dirumuskan sebelum implementasi

1. Apakah modul SHU tetap disediakan? Jika ya, siapa yang mengelola impor/input/koreksi, siapa yang menyetujui, dan apakah cek SHU publik dipertahankan?
2. Siapa yang boleh memberi pengecualian undangan Tidak Layak: Helpdesk langsung berdasarkan mandat, atau wajib persetujuan Admin?
3. Apa definisi tepat batas hangus (dihitung setelah toleransi atau dari jam mulai; inklusif atau eksklusif)?
4. Apakah presensi offline boleh menyerahkan **uang tunai**? Usulan aman: **tidak**, kecuali mekanisme cadangan yang disetujui dan dicatat; lihat SOP terpisah.
5. Apakah token undangan dapat dicabut dan diterbitkan ulang jika bocor? Usulan: ya, dengan audit, meskipun cetak/kirim ulang biasa memakai token tetap.
6. Masa layanan undangan H-30 s.d. H-7: hanya kebiasaan atau tanggal buka/tutup yang diatur pada setiap rapat? Usulan: konfigurasi per rapat dan override teraudit untuk hari-H.
7. Aturan pesertanya: bagaimana perlakuan anggota luar biasa untuk kuorum, transport, dan doorprize; siapa yang memutuskan pengecualian?
8. Bagaimana kebijakan anggota yang tak mampu tanda tangan/foto dan siapa yang memberi izin pengecualian?

## J. Prinsip implementasi

Jangan menganggap keputusan terbuka sebagai keputusan final. Setiap perubahan aturan harus diselaraskan lintas 10 dokumen dan dibuktikan lewat test. Semua contoh rupiah, jam, dan tier adalah fixture simulasi kecuali dinyatakan sebagai konfigurasi RAT yang telah disahkan.
