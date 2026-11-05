# DECISION LOG — Keputusan Resmi

> **Status:** Sumber keputusan aktif, diperbarui 5 November 2026. Keputusan terbaru menggantikan butir lama jika dinyatakan eksplisit.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

| ID | Status | Keputusan | Dampak |
|---|---|---|---|
| D-001 | FINAL | Hanya Admin, Helpdesk, Scanner, Keuangan yang login; anggota tidak login | Hak akses |
| D-002 | FINAL | SHU tetap ada dan dikelola Admin; Keuangan tidak mengelola SHU | SHU |
| D-003 | FINAL | Mode A: simpanan wajib & kolektibilitas; Mode B: simpanan wajib & pinjaman wajib lancar; status Aktif bukan syarat kelayakan | Kelayakan |
| D-004 | FINAL | Layak/Tidak Layak otomatis; keputusan Verified petugas menerbitkan token QR unik per anggota per rapat; Tidak Layak boleh Verified disertai alasan | Verifikasi |
| D-005 | FINAL | Verifikasi layanan tatap muka H-30 sampai H-7 umumnya, undangan dikirim WA atau dicetak; petugas & waktu semua pengiriman/cetak dicatat serta dilaporkan | Undangan |
| D-006 | FINAL | Tambah `tgl_ja`, `tgl_keluar`; status Nonaktif (keluar koperasi) memblokir presensi walau sudah menerima QR | Master & pintu |
| D-007 | FINAL | NBA 15 digit string `020006000010339`; UI format `020006.000.010.339` | Master & impor |
| D-008 | FINAL, istilah diperbarui D-043 | Simpanan wajib input ialah saldo per 31 Desember Tahun Acuan; perhitungan minimum proporsional berdasar `tgl_ja`, tarif bulanan, sampai akhir Tahun Acuan | Kelayakan |
| D-009 | FINAL | Master saldo diperbarui/impor ulang untuk tahun buku berikutnya; snapshot verifikasi/rapat lama tak diubah | Implikasi data |
| D-010 | FINAL | Pengaturan kelayakan fleksibel per rapat, dikunci saat Berlangsung | Pengaturan |
| D-011 | FINAL | Hybrid: jam mulai presensi berbeda dari jam mulai acara; Admin berwenang membuka proses presensi | Alur jadwal |
| D-012 | FINAL | Toleransi tidak dipotong: menit kena potongan = max(0, menit terlambat terhadap jam acara - toleransi); 09:01 terhadap 08:00 + 60 menit = 1 menit terkena tier | Transport |
| D-013 | FINAL | Aturan transport diatur Admin; Keuangan alokasi/monitoring/rekonsiliasi; Scanner menyerahkan transport | Izin |
| D-014 | FINAL | Kas bukan per meja: alokasi awal per user Scanner, sekali per rapat; tidak ada top-up, tambah kas, atau transfer | Kas |
| D-015 | FINAL | Peringatan sisa kas menipis; jika tidak cukup untuk transport, tolak scan/presensi yang memerlukan pembayaran, arahkan anggota ke Scanner lain; tidak ada hasil presensi sukses dengan status 'transport belum dibayar' | Presensi & kas |
| D-016 | FINAL | Mode Presensi 1x dan Mode Presensi 2x (Datang–Pulang) | Penamaan |
| D-017 | FINAL | Data verifikasi dipisah per rapat dari master anggota | Skema |

| D-018 | FINAL | Log **pencarian SHU publik** disimpan selama **14 hari**; pembersihan otomatis setiap hari. Tidak berlaku untuk data SHU per tahun buku atau audit perubahan SHU oleh Admin. | Privasi, skema, operasional, uji |

## Keputusan masih terbuka
| ID | Status | Pertanyaan yang perlu diputuskan |
|---|---|---|
| O-002 | SELESAI (D-032) | Presensi saat koneksi hilang: hanya antrean sementara tanpa konfirmasi masuk, atau operasional dihentikan sampai tersambung? Jangan menganggap pembayaran offline diperbolehkan. |
| O-008 | SELESAI (D-033) | Batas masa minimum proporsional, jika anggota mulai sebelum tahun buku, pada tanggal 31 Desember, atau data `tgl_ja` tidak lengkap. Usulan: hitung bulan masuk secara inklusif, dibatasi 12 bulan. |

Setiap keputusan berikutnya diberi ID, tanggal, alasan, dan daftar berkas yang wajib diperbarui.


## D-021 — Presensi dibuka sebelum acara [FINAL, 30 Oktober 2026]
- Status rapat **tidak** bertambah. Saat presensi dibuka lebih awal, status tetap `Verifikasi`; yang aktif adalah penanda `presensi_dibuka_at` + `dibuka_oleh`.
- Hanya **Admin** yang boleh membuka presensi, dan hanya saat rapat `Verifikasi`. Membuka presensi tidak mengunci parameter (BR-002 tetap: kunci saat Berlangsung).
- Gerbang scan: presensi sudah dibuka **dan** rapat belum `Selesai`/`Dibatalkan`.
- Verifikasi susulan tetap boleh selama rapat belum `Berlangsung`; peserta yang baru Verified langsung dapat dipindai.
- Setiap presensi menyimpan nominalnya sendiri, sehingga perubahan tier hanya memengaruhi scan berikutnya.
- Keputusan ini menutup **O-005**. Berkas yang wajib diperbarui: `docs/01-business/BUSINESS_RULES.md`, `docs/03-technical/DATABASE_SCHEMA.md`, `docs/05-quality/TESTING.md` (T-SCAN-05), `docs/04-development/TRACEABILITY_MATRIX.md`, `docs/02-workflows/ALUR_PROSES_SEMUA_ROLE.md`.

## D-023 — Foto dan tanda tangan presensi [FINAL, 30 Oktober 2026]
- **Foto peserta** diambil langsung dari kamera perangkat (input `capture="environment"`); bila perangkat tidak mendukung `capture`, browser menampilkan pemilih berkas biasa.
- **Tanda tangan** digambar di layar (canvas) dan dikirim sebagai PNG data URL; bukan foto tanda tangan di kertas.
- Foto dan tanda tangan **wajib** ada sebelum presensi dicatat. Bila salah satu tidak valid, presensi dibatalkan dan tidak ada baris `presensi`, `transport_pembayaran`, atau pengurangan kas.
- Keduanya disimpan di disk **privat** (`storage/app/private/presensi/{rapat_id}/{peserta_id}/foto.<ext>` dan `tte.png`), tidak pernah disajikan sebagai URL publik, dan disembunyikan dari serialisasi model.
- **Retensi: disimpan terus tanpa penghapusan.** Tidak ada pembersihan otomatis; penghapusan hanya mungkin melalui tindakan manual yang diaudit, di luar lingkup aplikasi ini.
- Batas: foto maksimal 5 MB; tanda tangan PNG maksimal 2 MB dan wajib berisi PNG asli (magic byte diperiksa server).
- Berkas yang wajib diperbarui: `docs/03-technical/SECURITY_PRIVACY.md`, `docs/03-technical/DATABASE_SCHEMA.md`, `docs/02-workflows/SOP_PRESENSI_DAN_TRANSPORT.md`, `docs/02-workflows/ALUR_PROSES_SEMUA_ROLE.md`, `docs/05-quality/TESTING.md`, `docs/04-development/IMPLEMENTATION_PLAN.md`.

## D-022 — Transport Rp0 akibat hangus [FINAL, 30 Oktober 2026]
- Presensi **tetap dicatat** walau nominal transport menjadi Rp0 (hangus atau `potongan >= nominal dasar`).
- Tidak dibuat baris `transport_pembayaran`, kas Scanner tidak berkurang, dan pemeriksaan "kas cukup" dilewati karena tidak ada uang yang wajib diserahkan.
- Presensi ditandai **Hangus (Rp0)** beserta tier penyebabnya agar terlihat di laporan.
- Tidak ada status "transport belum dibayar"; larangan BR-009/D-015 tetap berlaku untuk nominal lebih dari nol.
- Keputusan ini menutup **O-003**. Berkas yang wajib diperbarui: `docs/01-business/BUSINESS_RULES.md` (BR-008), `docs/05-quality/TESTING.md` (T-SCAN-06), `docs/04-development/TRACEABILITY_MATRIX.md`.

## D-019 — Pengaturan API CAPTCHA dan Fonnte [FINAL, 29 Oktober 2026]
- Menu **Pengaturan → Integrasi** berisi pengaturan CAPTCHA dan Fonnte WhatsApp, hanya Admin yang dapat melihat/mengubahnya.
- Site key CAPTCHA dapat ditampilkan sesuai fungsi publiknya; secret CAPTCHA dan token API Fonnte disimpan terenkripsi dan hanya tampil dalam keadaan tersamarkan, tidak dikembalikan dalam respons baca.
- Pengubahan key/token tidak memerlukan modifikasi `.env`; `.env` tetap mengelola APP_KEY, database, queue, dan infrastruktur.
- Ada aksi **Uji CAPTCHA** (validasi token challenge nyata) dan **Uji Fonnte** (permintaan pengujian dengan penerima yang dimasukkan Admin dan konfirmasi eksplisit); status hasil tidak boleh membocorkan credential.
- Jika integrasi belum valid/aktif, pengiriman WA ditolak dengan pesan jelas; Cek SHU tidak boleh melewati CAPTCHA secara diam-diam.
- Audit konfigurasi wajib tanpa menyalin rahasia. Rincian kanonik di `docs/03-technical/INTEGRATION_SETTINGS.md`.

## D-020 — Format impor master anggota [FINAL, 29 Oktober 2026]
- Impor master anggota memakai satu berkas **XLSX** dengan baris judul dan satu baris per anggota.
- Kolom `no_anggota` wajib berformat Teks 15 digit. Baris yang terbaca sebagai angka karena nol awal hilang **ditolak** dan dilaporkan dengan nomor barisnya; sistem tidak menambal nomor secara otomatis.
- Baris tidak valid lain juga dilaporkan per baris; baris valid dalam berkas yang sama tetap diimpor dan data lama tidak rusak.
- Anggota dengan NBA yang sama diperbarui, bukan digandakan; anggota lain tidak dihapus (D-009).
- Koreksi nomor anggota dilakukan Admin melalui form master dan tercatat di `log_audit` (BR-001).
- Keputusan ini menutup **O-007**. Berkas yang wajib diperbarui: `docs/03-technical/DATABASE_SCHEMA.md`, `docs/05-quality/TESTING.md` (T-NBA-01..04), `docs/04-development/TRACEABILITY_MATRIX.md`.

## D-024 - RAT ini memakai Mode Presensi 1x [DIGANTIKAN D-034, 31 Oktober 2026]
- RAT memakai **Mode Presensi 1x**: datang, foto, tanda tangan, transport dibayar saat itu juga.
- **Mode Presensi 2x (Datang–Pulang) tidak dikerjakan.** Fase 6 ditandai tidak berlaku, bukan dikerjakan sebagian. Kolom `pulang_at` tetap ada tetapi tidak dipakai.
- Alasan: tidak ada pembayaran di tahap pulang, kuorum dihitung dari kehadiran saat datang, dan tiga hal terberat Mode 2x (pencadangan hak transport, Scanner berbeda saat pulang, pelepasan hak peserta yang tidak pulang) tidak dibutuhkan.
- Bila kelak Mode 2x benar-benar dibutuhkan, wajib keputusan baru; jangan mengaktifkannya diam-diam.
- Keputusan ini menutup **O-001**. Berkas yang wajib diperbarui: `docs/01-business/BUSINESS_RULES.md` (BR-006), `docs/04-development/IMPLEMENTATION_PLAN.md` (Fase 6), `docs/02-workflows/ALUR_PROSES_SEMUA_ROLE.md`.
- **Digantikan D-034 (3 November 2026):** Mode Presensi 2x (Datang-Pulang) dibangun penuh dan boleh dipilih Admin per rapat; Mode Presensi 1x tetap tersedia.

## D-025 — Ambang peringatan kas menipis [FINAL, 31 Oktober 2026]
- Peringatan kas menipis muncul bila `sisa() < batas_peringatan_kas`.
- `batas_peringatan_kas` adalah nominal rupiah per rapat, diatur Admin; nilai bawaannya **5 Ã— `transport_dasar`** saat rapat dibuat.
- Peringatan bersifat **hanya informasi**: tidak memblokir scan. Penolakan tetap memakai aturan "kas tidak cukup untuk nominal transport".
- Alasan memakai nominal, bukan jumlah peserta: yang dihitung tetap uang yang tersedia, dan angkanya bisa disesuaikan bila transport dasar berubah.
- Keputusan ini menutup **O-004**. Berkas yang wajib diperbarui: `docs/01-business/BUSINESS_RULES.md` (BR-009), `docs/03-technical/DATABASE_SCHEMA.md`, `docs/05-quality/TESTING.md`, `docs/02-workflows/SOP_PENGELOLAAN_KAS_RAPAT.md`.

## D-026 — Konfirmasi terima kas dan penggantian Scanner [FINAL, 31 Oktober 2026]
- Scanner **wajib menekan "Saya sudah menerima kas"** sekali per alokasi sebelum boleh memindai. Tanpa konfirmasi, scan ditolak. Waktu konfirmasi disimpan di `diterima_pada` dan diaudit.
- **Penggantian Scanner tidak memindahkan saldo.** Kas lama ditutup (`status=ditutup`, sisa fisik dicatat), sisa uang dikembalikan ke Keuangan dan direkonsiliasi, lalu Scanner baru menerima alokasi sendiri.
- Aturan ini **FINAL**, tetapi pengerjaannya menyatu dengan **Tutup Kas** pada Fase 8; jangan membuat alur ganti Scanner yang mentransfer saldo.
- Keputusan ini menutup **O-006**. Berkas yang wajib diperbarui: `docs/01-business/BUSINESS_RULES.md` (BR-009), `docs/03-technical/DATABASE_SCHEMA.md`, `docs/02-workflows/SOP_PENGELOLAAN_KAS_RAPAT.md`, `docs/04-development/IMPLEMENTATION_PLAN.md` (Fase 8).

## D-027 — Resi transport hanya untuk internal [FINAL, 31 Oktober 2026]
- Resi transport **tidak** dapat diverifikasi publik. Hanya **Scanner yang menyerahkan uang**, **Admin**, dan **Keuangan** yang dapat membukanya; Helpdesk dan Scanner lain ditolak (403).
- Alasan: pemegang resi sudah tahu isi resinya, sedangkan halaman publik baru menambah risiko enumerasi kode dan penyebaran nama serta nominal anggota.
- Bila kelak dibutuhkan verifikasi ringan, bentuknya hanya "resi sah, tanggal, nominal" tanpa nama/NBA, dan wajib rate limiting.
- Keputusan ini menutup butir "verifikasi resi publik" pada `DESIGN_SYSTEM.md`. Berkas yang wajib diperbarui: `docs/03-technical/DESIGN_SYSTEM.md`, `docs/04-development/IMPLEMENTATION_PLAN.md` (5.7), `docs/04-development/TRACEABILITY_MATRIX.md`.

## D-028 — Input SHU lewat impor XLSX [FINAL, 3 November 2026]
- Data SHU **hanya** masuk lewat impor XLSX per tahun buku; tidak ada input satu per satu. Kolom: `no_anggota`, `balas_jasa_saham`, `balas_jasa_pinjaman`.
- Total per anggota dihitung dari kedua komponen dan **tidak** disimpan sebagai kolom terpisah, agar tidak bisa berbeda dari komponennya.
- Impor ulang tahun buku yang sama memperbarui baris `(anggota_id, tahun_buku)`; tahun buku lain dan hasil rapat lama tidak tersentuh. NBA yang belum ada di master anggota ditolak per baris.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-011), `DATABASE_SCHEMA.md`, `IMPLEMENTATION_PLAN.md` (Fase 7b), `TESTING.md` (T-SHU).

## D-029 — Cek SHU publik dibuka lewat tombol Admin [FINAL, 3 November 2026]
- Cek SHU publik **tidak** terikat status rapat (bukan otomatis saat rapat dimulai atau selesai). Admin menekan tombol **Buka cek publik** per tahun buku setelah data final, dan dapat menutupnya kembali. Selama tertutup, pencarian publik tidak dilayani.
- Pencarian publik memakai **NBA + CAPTCHA** saja; tidak ada faktor kedua seperti tanggal lahir. Risiko bahwa CAPTCHA bukan bukti pemilik NBA sudah dicatat di `USER_FLOW.md` dan `PRD.md`.
- Buka cek publik ditolak bila tahun buku tersebut belum punya data SHU.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-011), `DATABASE_SCHEMA.md` (`shu_publik`), `IMPLEMENTATION_PLAN.md` (Fase 7b/7c), `ROLE_PERMISSION_MATRIX.md`, `USER_FLOW.md`.

## D-030 — Doorprize: jumlah pemenang dan konfirmasi kehadiran [DIGANTIKAN D-044]
- Jumlah pemenang disimpan sebagai parameter rapat `jumlah_pemenang_doorprize` (bawaan 10), bukan angka di kode dan bukan diisi saat mengundi.
- Kandidat hanya peserta yang sudah tercatat hadir. Satu peserta punya satu hasil per rapat (`menang` / `gugur`) sehingga tidak bisa menang dua kali dan tidak muncul lagi setelah gugur.
- Karena RAT memakai Mode Presensi 1x (D-024) tidak ada pencatatan pulang, maka status "sudah pulang" ditentukan lewat pop-up konfirmasi saat undian: **masih ada** = menang, **sudah pulang** = gugur. Tidak ada tombol presensi pulang dan tidak ada kolom baru di `presensi`.
- **Catatan D-034 (3 November 2026):** pada Mode Presensi 2x tab/tombol Pulang ada dan mencatat `pulang_at`; pop-up "masih ada / sudah pulang" tetap menjadi pemeriksaan kehadiran manusia, bukan pengganti presensi pulang.
- Undian berjalan satu kandidat per klik **Undi**; penggantian kandidat gugur tidak otomatis.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-011), `DATABASE_SCHEMA.md` (`doorprize_pemenang`, `rapat.jumlah_pemenang_doorprize`), `IMPLEMENTATION_PLAN.md` (Fase 7d), `TESTING.md` (T-DP), `TRACEABILITY_MATRIX.md` (FR-010), `ALUR_PROSES_SEMUA_ROLE.md`.

## D-031 - Penutupan kas dan selisih rekonsiliasi [FINAL, 3 November 2026]
- Kas ditutup oleh **Keuangan** per akun Scanner, bukan per meja. Penutupan mencatat `sisa_fisik` hasil hitung bersama Scanner, `ditutup_oleh`, dan `ditutup_pada`; status menjadi `ditutup` dan tidak berubah lagi.
- Sisa seharusnya = `kas_awal` - pembayaran transport yang tercatat. Selisih = `sisa_fisik` - sisa seharusnya.
- Selisih bukan nol **cukup diberi alasan tertulis dan diaudit**; tidak ada langkah persetujuan Admin dan tidak ada status tambahan. Penutupan ditolak bila selisih bukan nol tanpa alasan.
- Konfirmasi terima kas (D-026) dikerjakan pada fase ini: alokasi baru berstatus `menunggu`, Scanner pemilik menekan sekali, menjadi `aktif`, dan scan ditolak selama masih `menunggu`.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-009), `DATABASE_SCHEMA.md` (`kas_scanner`), `IMPLEMENTATION_PLAN.md` (Fase 8), `TESTING.md` (T-KAS/T-REKON), `TRACEABILITY_MATRIX.md` (FR-008), `SOP_PENGELOLAAN_KAS_RAPAT.md`, `ALUR_PROSES_SEMUA_ROLE.md`.

## D-032 - Koneksi hilang: pemindaian berhenti total [FINAL, 3 November 2026]
- Saat koneksi perangkat putus, **pemindaian dihentikan total**. Tidak ada antrean offline, tidak ada draf presensi, dan tidak ada pencatatan manual sebagai pengganti.
- Scanner melihat peringatan merah di halaman pindai dan tombol **Catat Presensi** menjadi nonaktif; halaman **Status Koneksi** menjelaskan aturan yang sama.
- Tidak ada uang yang boleh diserahkan selama offline. Peserta diarahkan ke Scanner lain yang online.
- Alasan: pembayaran tanpa jaminan kas dan status keanggotaan terkini melanggar BR-009/D-015; antrean offline menambah risiko konflik dan duplikat tanpa manfaat yang sepadan untuk kegiatan rapat.
- Keputusan ini menutup **O-002** dan menggugurkan Fase 9 (offline/sinkron) menjadi **tidak dikerjakan**. Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-006), `OFFLINE_SYNC_POLICY.md`, `IMPLEMENTATION_PLAN.md` (Fase 9), `TESTING.md` (T-OFF), `TRACEABILITY_MATRIX.md` (FR-012), `ALUR_PROSES_SEMUA_ROLE.md`, `PANDUAN_PETUGAS.md`.

## D-033 - Kasus tepi minimum proporsional [FINAL, 3 November 2026]
- `tgl_ja` kosong atau tidak lengkap **tidak** diberi keringanan: peserta dinilai **Tidak Layak** dengan alasan `Tanggal jadi anggota belum lengkap`, dan Helpdesk wajib melengkapi data lebih dulu.
- Anggota dengan `tgl_ja` setelah 31 Desember tahun buku berjalan memakai **minimum 0** karena belum punya kewajiban simpanan pada tahun buku itu.
- Bulan berjalan dihitung **inklusif** dan dibatasi 12 bulan; minimum proporsional tidak pernah melebihi minimum tahunan rapat (D-008).
- Keputusan ini menutup **O-008**. Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-003), `TESTING.md` (T-KEL), `TRACEABILITY_MATRIX.md`, `DATABASE_SCHEMA.md` (catatan snapshot minimum).

## D-034 - Mode Presensi 2x (Datang–Pulang) dipakai untuk semua agenda [FINAL, 3 November 2026]
- **Menggantikan D-024.** Aplikasi dipakai untuk seluruh agenda rapat, bukan hanya RAT. Mode Presensi 2x (Datang–Pulang) dibangun penuh dan boleh dipilih Admin per rapat; Mode Presensi 1x tetap tersedia.
- **Kedatangan:** peserta dipindai, foto + tanda tangan wajib, nominal transport dihitung server lalu **dikunci**. Tidak ada uang diserahkan dan kas Scanner tidak dipakai saat datang.
- **Kepulangan:** peserta dipindai lagi dan hanya membutuhkan token QR (tanpa foto/tanda tangan). Uang transport diambil dari kas **Scanner yang mencatat pulang**, boleh berbeda dari Scanner pencatat datang. Resi terbit saat pulang.
- Kepulangan dan pembayaran berhasil **bersama atau tidak sama sekali**; satu pembayaran per peserta. Bila kas Scanner tidak cukup, kepulangan tidak tercatat dan peserta diarahkan ke Scanner lain.
- **Datang tetapi tidak pernah pulang:** tetap dihitung hadir untuk kuorum, hak transportnya **hangus** dan tidak dibayarkan. Pemindaian pulang masih dapat dilakukan sampai Admin menetapkan rapat **Selesai** atau **Dibatalkan**.
- Doorprize tetap memakai popup "masih ada / sudah pulang" sebagai pemeriksaan kehadiran manusia; pemenang yang gugur tidak boleh ikut undian berikutnya. Tidak ada pengecualian "hadir menurut pembawa acara, tetap dibayar".
- Alasan: kebutuhan nyata seluruh agenda rapat, dan pembayaran di akhir mencegah uang keluar untuk peserta yang tidak menyelesaikan acara.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md` (BR-006, BR-007, BR-009, BR-011), `PRD.md`, `ALUR_PROSES_SEMUA_ROLE.md`, `USER_FLOW.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `DATA_DICTIONARY.md`, `SOP_PRESENSI_DAN_TRANSPORT.md`, `SOP_PENGELOLAAN_KAS_RAPAT.md`, `ACCEPTANCE_CRITERIA.md`, `IMPLEMENTATION_PLAN.md` (Fase 6), `TESTING.md` (T-PULANG), `TRACEABILITY_MATRIX.md` (FR-006), `UAT_SCENARIOS.md`, `PANDUAN_PETUGAS.md`.

## D-035 - Manajemen pengguna oleh Admin [FINAL, 4 November 2026]
- Halaman **Pengaturan - Pengguna** hanya untuk Admin: daftar akun, tambah, ubah nama/username/role, ganti sandi, dan hapus akun (identitas login diperbarui oleh D-041).
- Role tetap **empat** pilihan (`admin`, `helpdesk`, `scanner`, `keuangan`) memakai kolom `users.role` + middleware `EnsureRole`; **tanpa** Spatie Permission dan tanpa role buatan sendiri. Anggota koperasi tetap bukan akun login.
- Pengaman: Admin tidak dapat menghapus atau menurunkan role akun sendiri, dan sistem menolak bila akan tersisa nol akun Admin. Sandi baru minimal 8 karakter dan disimpan ter-hash.
- Setiap tambah/ubah/hapus akun dicatat di `log_audit` tanpa nilai sandi.
- Penghapusan hanya boleh untuk akun yang belum memiliki riwayat operasional. Akun yang sudah terkait verifikasi, undangan, kas, presensi, pembayaran, SHU, doorprize, pengaturan, rapat, atau audit ditolak agar identitas petugas dan arsip tetap utuh. Reset total D-037 tetap menjadi satu-satunya pengecualian.
- **Reset total** (Pengaturan Aplikasi) kini hanya membuat ulang akun Admin; akun petugas lain dibuat lagi lewat menu Pengguna.
- Alasan: akun petugas tidak boleh bergantung pada seeder atau `php artisan tinker` saat aplikasi dipakai.
- Berkas yang wajib diperbarui: `ALUR_PROSES_SEMUA_ROLE.md` (bab 2 dan 10), `PANDUAN_PETUGAS.md`, `TESTING.md` (T-USER), `PRD.md`, `ARCHITECTURE.md`.

## D-036 - Helpdesk tidak dapat melakukan presensi [FINAL, 4 November 2026]
- Helpdesk **tidak dapat mencatat, mengubah, membatalkan, atau mengoreksi presensi** dalam keadaan apa pun.
- Presensi datang/pulang hanya dilakukan Scanner melalui token QR atau token cadangan yang tetap melewati validasi server yang sama. Masalah identitas, status anggota, atau verifikasi ditangani Helpdesk sebelum peserta kembali ke Scanner.
- Tidak ada fitur "presensi manual darurat Helpdesk". Gangguan koneksi tetap mengikuti D-032: pemindaian berhenti total dan tidak diganti catatan manual.
- Berkas yang wajib diperbarui: `BUSINESS_RULES.md`, `PRD.md`, `ROLE_PERMISSION_MATRIX.md`, `USER_FLOW.md`, `TESTING.md`, `ACCEPTANCE_CRITERIA.md`.

## D-037 - Reset total adalah pengecualian eksplisit untuk arsip [FINAL, 4 November 2026]
- Dalam operasi normal, `log_audit`, pembayaran, dan bukti presensi tetap append-only/tidak dapat dihapus lewat CRUD biasa.
- Aksi Admin **Pengaturan Aplikasi → Hapus semua data** adalah prosedur destruktif khusus untuk memulai aplikasi dari nol. Setelah frasa konfirmasi tepat, aksi ini boleh menghapus seluruh data bisnis, `log_audit`, akun selain Admin, sesi/cache, dan berkas foto/TTE.
- Reset total tidak boleh tersamar sebagai koreksi data dan harus tetap dilindungi role Admin, frasa konfirmasi, transaksi database, serta logout setelah selesai.

## D-038 - Input dan koreksi SHU manual [FINAL, 4 November 2026]
- Menggantikan butir BR-011 lama yang hanya mengizinkan impor XLSX. Admin dapat input satu anggota secara manual dan mengoreksi dua komponen SHU.
- NBA harus sudah ada di master anggota dan kombinasi anggota–tahun buku tetap unik. Koreksi wajib alasan minimal 10 karakter serta audit nilai sebelum/sesudah.
- Impor XLSX tetap tersedia untuk data banyak; input manual bukan akses bagi Keuangan atau role lain.

## D-039 - Akses baca role sesuai tugas [FINAL, 4 November 2026]
- Helpdesk dapat mencari/melihat data anggota yang diperlukan untuk kelayakan, verifikasi, dan undangan, tetapi **tidak dapat mengubah master anggota**. Koreksi master tetap Admin-only.
- Keuangan tidak mendapat halaman master anggota; identitas anggota hanya terlihat dalam presensi, bukti, dan laporan yang memang menjadi tugas Keuangan.
- Informasi rapat untuk role selain Admin bersifat baca-saja dan dibatasi menurut tugas: Helpdesk melihat jadwal/status/kriteria kelayakan; Scanner melihat jadwal/status/mode dan status gerbang presensi; Keuangan melihat jadwal/status/mode dan konfigurasi transport.
- Tidak ada role selain Admin yang mendapat route pengaturan atau aksi perubahan parameter rapat.

## D-040 - Pengelompokan navigasi dan navbar Pengaturan [FINAL, 4 November 2026]
- Sidebar dibagi dalam span kerja yang singkat: Utama, Operasional/Pelayanan/Kas & Transport, Pelaporan, dan Sistem sesuai role.
- Untuk Admin, sidebar hanya menampilkan satu menu **Pengaturan** di bagian Sistem. Di dalam seluruh halaman Pengaturan tersedia satu navbar horizontal `nav-overflow` mode pills berisi **Pengguna**, **Integrasi**, dan **Aplikasi**. Tidak ada submenu vertikal/collapse di sidebar.
- Perubahan navigasi tidak mengubah otorisasi route; pembatasan server tetap menjadi sumber kebenaran.

## D-041 - Login petugas memakai username [FINAL, 4 November 2026]
- Petugas login dengan `username`, bukan email. Username unik, disimpan huruf kecil, panjang 3–50 karakter, dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda minus.
- Manajemen Pengguna membuat dan mengubah username; audit menyimpan username dan tidak memakai email sebagai identitas akun.
- Migrasi mempertahankan email lama sebagai kolom nullable untuk kompatibilitas data, tetapi email tidak digunakan pada form login, manajemen pengguna, autentikasi, atau akun baru.
- Akun awal setelah reset memakai username `admin` dan sandi awal `password`. Akun lama memperoleh username dari bagian sebelum `@`; benturan ditangani dengan akhiran ID.

## D-042 - Status aktif/nonaktif akun petugas [FINAL, 4 November 2026]
- Admin dapat mengaktifkan atau menonaktifkan akun petugas. Nonaktif adalah pilihan untuk menghentikan akses tanpa menghapus identitas dan riwayat operasional.
- Akun nonaktif tidak dapat login, sesi yang masih terbuka dihentikan pada request berikutnya, remember token dibatalkan, dan akun tidak dapat dipilih untuk alokasi kas baru.
- Admin tidak dapat menonaktifkan akun sendiri dan minimal satu Admin aktif harus tetap tersedia. Setiap perubahan status dicatat di `log_audit` tanpa sandi.
- Pengaktifan kembali memakai username, role, dan sandi yang sama kecuali Admin mengubahnya secara terpisah.

## D-043 - Tahun acuan rapat [FINAL, 4 November 2026]
- Aplikasi dipakai untuk semua jenis rapat, sehingga `Tahun Buku` bukan identitas rapat. Pada data rapat, istilah tersebut diganti menjadi **Tahun Acuan** dan dipindahkan dari Informasi Umum ke Kriteria Kelayakan.
- Tahun Acuan menentukan `tanggal_acuan_saldo` pada 31 Desember dan batas akhir perhitungan simpanan wajib proporsional. Nama, jenis, dan tanggal pelaksanaan tetap menjadi identitas rapat.
- Data rapat lama dimigrasikan tanpa kehilangan nilai. Header, dashboard, laporan, dan ekspor memakai nama rapat; tidak lagi memaksakan label “RAT Tahun Buku”.
- Kolom `tahun_buku` pada SHU, saklar SHU publik, dan log pencarian SHU **tetap** karena SHU memang dikelola per tahun buku dan tidak bergantung pada rapat.

## D-044 - Doorprize berbasis daftar hadiah [FINAL, 4 November 2026]
- **Menggantikan D-030.** Admin menyiapkan hadiah per rapat dengan data minimal `nama`, `jumlah`, dan `keterangan` opsional. Tidak ada urutan hadiah dan tidak ada lagi parameter jumlah pemenang pada rapat.
- Halaman undian menyediakan pilihan hadiah yang masih tersedia. Admin memilih hadiah lalu mengundi satu kandidat dari peserta hadir yang belum pernah menang atau gugur pada rapat itu.
- Undian hanya boleh dijalankan saat rapat berstatus **Verifikasi dan presensi sudah dibuka**, atau saat status **Berlangsung**. Status Draf, Selesai, dan Dibatalkan ditolak oleh server.
- Satu peserta hanya boleh memperoleh satu hadiah. Konfirmasi **masih ada** mencatat kemenangan untuk hadiah terpilih; **sudah pulang** mencatat gugur dan tidak mengurangi sisa hadiah.
- `jumlah` menyimpan stok awal dan tidak dikurangi. Sisa dihitung sebagai `jumlah - jumlah pemenang sah`. Saat sisa nol, hadiah otomatis tidak dapat dipilih, berpindah ke daftar **Habis**, tetapi tetap terlihat dalam hasil dan laporan.
- Daftar hadiah tetap dapat diubah. Jumlah tidak boleh lebih kecil daripada pemenang yang sudah tercatat; hadiah yang sudah memiliki hasil undian tidak boleh dihapus. Nama hadiah disalin ke snapshot hasil agar perubahan berikutnya tidak mengubah arsip kemenangan.
- Penetapan pemenang memakai transaksi dan penguncian database agar request bersamaan tidak melebihi jumlah hadiah. Semua perubahan hadiah dan hasil undian diaudit.

## D-045 - Jadwal dan alamat rapat pada Informasi Umum [FINAL, 4 November 2026]
- Jam mulai presensi, jam mulai acara, dan jam selesai ditempatkan pada tab **Informasi Umum**, bukan tab Presensi. Pemindahan UI tidak mengubah fungsi ketiga nilai tersebut.
- Data lokasi dipisahkan menjadi **Nama Lokasi Rapat** dan **Alamat Lengkap Lokasi Rapat**. Alamat lengkap wajib diisi saat Admin menyimpan pengaturan rapat baru atau memperbarui rapat lama.
- Migration menambah kolom alamat secara nullable agar data lama tidak rusak. Alamat ditampilkan pada ringkasan rapat dan undangan setelah diisi.

## D-046 - Pemisahan master hadiah dan layar undian [FINAL, 5 November 2026]
- Master hadiah dikelola langsung pada menu **Doorprize** di `/doorprize`, bukan sebagai tab Manajemen Rapat.
- Proses undian memakai halaman khusus `/doorprize/undian` dengan tampilan minimalis untuk layar acara. Halaman master menyediakan tombol menuju halaman undian dan halaman undian menyediakan jalan kembali ke master.
- Desain contoh `.data/undian_doorprize_minimalis.html` menjadi acuan visual, tetapi peserta tetap berasal dari presensi database, pemenang tetap dipilih server-side, dan konfirmasi **masih ada/sudah pulang** serta seluruh pengaman D-044 tidak boleh diganti oleh data atau pengacakan lokal browser.
