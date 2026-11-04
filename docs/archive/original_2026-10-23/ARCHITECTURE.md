# ARCHITECTURE.md — eRAT (Electronic Rapat Anggota Tahunan)

Dokumen ini menjelaskan arsitektur teknis **eRAT**, sistem informasi manajemen Rapat Anggota Tahunan koperasi berbasis web. eRAT mendigitalisasi, mengamankan, dan mempercepat proses registrasi, presensi, validasi aturan bisnis, perhitungan kuorum, pelacakan uang transport berbasis waktu, hingga audit kehadiran memakai foto wajah — plus fitur cek SHU dan undian doorprize untuk peserta yang hadir. Dokumen ini berdiri sendiri: cukup dibaca bersama `README.md` project, tidak membutuhkan dokumen lain untuk dipahami.

- **Versi:** 1.0 — 23 Oktober 2026
- **Status:** Draf awal hasil diskusi kebutuhan
- **Stack:** Laravel 13 + PHP 8.3 + MySQL, Blade + Livewire, UI **Tabler penuh** (CSS, JavaScript, dan ikon dari template Tabler).
- **Sifat proyek:** bangun ulang dari nol (tanpa migrasi data dari aplikasi lama), **berdiri sendiri** — tidak terhubung ke ERP dan tidak berbagi database maupun kode dengan aplikasi lain.
- **Alasan bangun ulang:** aplikasi lama harus dibersihkan dari masalah keamanan (lihat Bab 5) sekaligus ditambah fitur baru: verifikasi sebagai mekanisme undangan, mode presensi yang bisa dipilih per rapat, tanda tangan digital, potongan uang transport berbasis menit terlambat, mode offline scanner, dan halaman publik terbatas.
- **Bahasa:** seluruh tampilan, pesan, dan data berbahasa Indonesia; nama kode (class, method, variabel) berbahasa Inggris.

---

## 1. Gambaran Arsitektur

eRAT adalah **monolit Laravel klasik**: satu aplikasi web yang me-render halaman langsung di server memakai template Blade, diperkaya komponen **Livewire** untuk layar yang interaktif (stasiun scanner, dashboard kuorum realtime, papan skor publik, undian doorprize). Satu database MySQL melayani semua domain (anggota, rapat, verifikasi/peserta, presensi, transport, kas meja, SHU, doorprize, audit).

Alasan memilih monolit klasik:

1. **Tim kecil.** Satu orang pengembang utama; satu codebase = satu tempat mencari bug, satu deploy.
2. **Familiar.** Stack-nya sama dengan proyek ERP yang sudah dikuasai (Laravel + MySQL + Tabler), jadi tidak ada kurva belajar teknologi baru.
3. **Deployment sederhana.** Satu proses PHP + satu database MySQL. Bisa jalan di shared hosting maupun VPS tanpa orkestrasi kontainer, tanpa daemon tambahan.
4. **Skala acara terbatas.** RAT dihadiri ratusan peserta dalam satu hari, bukan jutaan pengguna terus-menerus. Monolit + polling ringan lebih dari cukup; tidak perlu antrean pesan, websocket, atau microservice.

Tidak ada pemisahan frontend/backend dan tidak ada API publik. Hampir semua halaman petugas diakses lewat browser dengan session login — dan yang login **hanya petugas**: `admin` (nama lama di repo terdahulu: `superadmin`), `helpdesk`, `scanner`, `keuangan`. Anggota koperasi/peserta **bukan pengguna login**: mereka tidak punya akun dan tidak ada portal anggota. QR peserta memakai `anggota.qr_token`, yaitu token deterministik HMAC-SHA256 atas nomor anggota/NBA dengan `APP_KEY`; token ini tidak pernah di-regenerate dan tidak kedaluwarsa, sehingga kirim ulang undangan selalu memakai token yang sama. QR adalah kredensial *presensi* dan kunci halaman undangan publik per token, bukan kredensial login — endpoint scan tetap mengharuskan akun petugas yang login (lihat Bab 5 dan 8). Di luar area petugas hanya ada halaman publik terbatas: papan skor agregat, undangan publik per token, cek SHU dengan formulir nomor anggota yang di-throttle dan diaudit, serta verifikasi resi. JavaScript di luar Livewire dipakai untuk kamera/scan QR, kanvas tanda tangan, dan antrean offline scanner; aset UI memakai Tabler.

---

## 2. Struktur Direktori `app/`

Setiap domain (anggota, rapat, peserta, presensi, transport, SHU, doorprize, laporan, audit) punya komponennya sendiri di dalam struktur baku berikut:

```
app/
├── Models/                  # Model Eloquent
│   ├── User.php             # akun login PETUGAS saja (admin, helpdesk, scanner, keuangan); anggota koperasi bukan user, tidak ada relasi ke Anggota untuk login
│   ├── Anggota.php          # data induk anggota: jenis, status verifikasi, cara undangan, qr_token HMAC
│   ├── Rapat.php            # rapat (RAT / rapat biasa) + konfigurasi per rapat
│   ├── Peserta.php          # peserta dibuat saat anggota verified untuk rapat + snapshot kriteria verifikasi
│   ├── Presensi.php          # kehadiran per peserta (datang/pulang, foto, tanda tangan)
│   ├── RapatTransportTier.php  # tingkatan tarif transport per rapat
│   ├── TransportPembayaran.php # status serah terima transport per peserta
│   ├── KasMeja.php             # kas awal/sisa per meja & penanggung jawab scanner
│   ├── WaLog.php               # log pengiriman/cetak undangan per anggota
│   ├── Shu.php              # SHU per anggota per tahun buku
│   ├── DoorprizeHadiah.php  # daftar hadiah per rapat
│   ├── DoorprizePemenang.php   # hasil undian
│   ├── Pengaturan.php       # key-value pengaturan global
│   └── LogAudit.php         # jejak perubahan data sensitif (append-only)
├── Http/
│   ├── Controllers/         # TIPIS: hanya orkestrasi request/response
│   │   ├── AnggotaController.php
│   │   ├── RapatController.php
│   │   ├── PesertaController.php
│   │   ├── KoreksiPresensiController.php  # koreksi presensi oleh admin (diaudit)
│   │   ├── HelpdeskController.php         # verifikasi peserta, kirim ulang undangan (token sama), eskalasi ke admin
│   │   ├── TransportController.php        # kas meja & rekonsiliasi (keuangan); aturan tier diatur admin di pengaturan rapat
│   │   ├── ShuController.php              # kelola SHU (keuangan); lookup publik diatur PublicController + ShuService
│   │   ├── DoorprizeController.php        # undian doorprize (admin)
│   │   ├── PengaturanController.php
│   │   ├── LaporanController.php          # laporan per role: admin (semua + audit), helpdesk (operasional kehadiran/undangan), keuangan (transport & SHU)
│   │   ├── AuditController.php            # penampil log audit (HANYA admin)
│   │   ├── PublicController.php             # papan skor agregat, undangan per token, cek SHU publik, verifikasi resi
│   │   └── MediaController.php            # foto/tanda tangan privat; HANYA admin + keuangan (Bab 9)
│   └── Requests/            # Validasi FormRequest — semua validasi input di sini
│       ├── StoreAnggotaRequest.php
│       ├── UpdateAnggotaRequest.php
│       ├── StoreRapatRequest.php
│       ├── VerifikasiAnggotaRequest.php
│       ├── CekShuPublikRequest.php
│       ├── VerifikasiResiRequest.php
│       ├── TutupKasMejaRequest.php
│       ├── KoreksiPresensiRequest.php
│       ├── ImporShuRequest.php
│       ├── StoreHadiahRequest.php
│       └── ...
├── Livewire/                # Komponen interaktif (ATURAN KEAMANAN di Bab 5)
│   ├── ScanPresensi.php     # stasiun SCANNER: scan QR datang/pulang pada rapat aktif, foto + tanda tangan, snapshot & antrean offline
│   ├── PresensiManual.php   # HELPDESK: presensi manual khusus peserta terundang saat QR gagal (alasan wajib, diaudit)
│   ├── DashboardKuorum.php  # angka kuorum realtime (polling) — admin; scanner hanya melihat ringkasan stasiun/kuorum
│   ├── UndiDoorprize.php    # panggung undian hadiah (admin)
│   └── DaftarPeserta.php    # daftar peserta + status kehadiran realtime (admin/helpdesk)
├── Services/                # SEMUA LOGIC BISNIS ADA DI SINI
│   ├── UndanganService.php     # antrean kandidat & hasil verifikasi; saat verified membuat peserta + snapshot kriteria
│   ├── VerifikasiService.php   # keputusan Layak/Tidak Layak, verified_by/at, catatan & alasan penolakan
│   ├── QrTokenService.php      # HMAC-SHA256 token anggota & kode resi berbasis APP_KEY
│   ├── WhatsAppService.php     # kirim undangan on-demand per anggota via Fonnte/gateway WA; tanpa broadcast; selalu menulis wa_logs
│   ├── KasService.php          # keuangan membuka/menutup & rekonsiliasi kas; scanner PIC memakai kas di titik scan
│   ├── ResiService.php         # terbitkan & verifikasi kode resi setelah serah terima tunai
│   ├── SinkronisasiPresensiService.php # sinkron antrean offline secara idempoten & anti-duplikat
│   ├── PresensiService.php     # catat datang/pulang via scan QR, presensi manual helpdesk, tanda tangan, koreksi admin
│   ├── KuorumService.php       # hitung kuorum (hadir vs basis, status sah)
│   ├── TransportService.php    # hitung potongan & nominal final; scanner menyerahkan tunai di titik scan; keuangan mengelola aturan & rekonsiliasi
│   ├── DoorprizeService.php    # undian dari peserta hadir, anti dobel menang (dioperasikan admin)
│   ├── ShuService.php          # dikelola keuangan (input/impor/koreksi teraudit); lookup publik per nomor anggota di-throttle & diaudit
│   └── AuditService.php        # pencatatan log audit terpusat
├── Policies/                # Otorisasi per model (role via Spatie laravel-permission)
│   ├── AnggotaPolicy.php
│   ├── RapatPolicy.php
│   ├── PresensiPolicy.php
│   ├── TransportPolicy.php
│   ├── ShuPolicy.php
│   ├── DoorprizePolicy.php
│   ├── MediaPolicy.php      # akses foto/tanda tangan HANYA admin + keuangan (Bab 9)
│   ├── KasMejaPolicy.php
│   └── ...
├── Support/                 # Helper murni (pure function, tanpa akses DB)
│   ├── FormatRupiah.php        # 1500000 → "Rp1.500.000"
│   └── WaktuHelper.php         # selisih menit terhadap jam mulai rapat, dsb.
├── Observers/               # Otomatisasi lintas potong
│   └── AuditLogObserver.php    # mencatat perubahan model sensitif ke log_audit
└── Providers/
```

Aturan isi tiap lapisan:

- **Models:** definisi relasi Eloquent + query scope (mis. `Anggota::aktif()`, `Peserta::hadir()`, `Rapat::berlangsung()`). Model boleh punya method kecil yang hanya membaca relasinya sendiri; tidak boleh menghitung rumus bisnis.
- **Http/Controllers:** hanya (1) validasi input via FormRequest, (2) panggil satu Service, (3) render view atau redirect. Controller tidak boleh berisi rumus bisnis dan tidak boleh memanggil model domain lain secara langsung.
- **Http/Requests:** semua aturan validasi (`required`, `numeric`, `exists`, `date`, batas file upload). Pesan error berbahasa Indonesia. Field sensitif anggota hanya boleh divalidasi di sini dalam konteks request admin yang berotorisasi — bukan dari komponen Livewire (lihat Bab 5).
- **Services:** satu class per domain proses. Service menerima input sebagai parameter, menjalankan seluruh aturan bisnis, menulis ke database dalam satu transaksi, dan mengembalikan hasil. Service tidak me-render view dan tidak mengakses `request()`, sehingga bisa dipanggil ulang dari controller, komponen Livewire, maupun test.
- **Livewire:** hanya lapisan interaksi. Komponen Livewire **tidak boleh** berisi rumus bisnis dan **tidak boleh** menulis field sensitif; semua penulisan data lewat Service, semua aksi lewat otorisasi (Bab 5).
- **Policies:** menentukan *siapa boleh apa* per model, ditegakkan di level kode (bukan cuma disembunyikan di UI). Hanya ada **empat role login**, semuanya akun petugas: `admin` (di repo lama bernama `superadmin`; master anggota termasuk field sensitif server-side, setup rapat & kriteria, verifikasi, koreksi presensi, doorprize, pengaturan, user & role, semua laporan, dan log audit), `helpdesk` (cari/verifikasi peserta, lihat undangan/status hadir/status transport, kirim ulang undangan dengan token yang sama setelah verifikasi, presensi manual khusus peserta terundang saat QR gagal dengan alasan wajib + log audit, dan eskalasi peserta tidak terundang ke admin — tidak boleh mengubah field sensitif, konfigurasi, pembayaran, data SHU, koreksi presensi selesai, lookup SHU, atau membuka media), `scanner` (hanya layar scan rapat aktif, termasuk mode offline: scan QR datang/pulang, lihat hasil minimal, unggah foto & tanda tangan dalam alur, menyerahkan tunai sesuai nominal final yang dihitung sistem pada titik scan — mode 1x saat datang, mode 2x saat pulang — dan melihat ringkasan stasiun/kuorum; scanner tidak boleh mengubah nominal, membuka/menutup atau merekonsiliasi kas, browse master, presensi manual dari daftar master, koreksi, konfigurasi, SHU, doorprize, laporan, audit, atau membuka arsip media; gagal scan dialihkan ke helpdesk), dan `keuangan` (pantau tier potongan transport yang diatur admin — tidak mengedit tier — kas awal/sisa & rekonsiliasi kas meja, SHU input/impor/koreksi teraudit, daftar hadir & total kuorum, laporan transport & SHU, serta akses baca media untuk verifikasi bukti/resi — tidak boleh mengubah field sensitif kriteria, kriteria rapat, presensi, doorprize, atau pengaturan/user; serah terima tunai ke peserta dilakukan scanner di titik scan, bukan dari konsol keuangan). **Anggota koperasi/peserta BUKAN role login** — mereka subjek data, tidak punya akun/portal eRAT; QR mereka berasal dari `anggota.qr_token`, bukan field peserta. Role dan permission dikelola dengan Spatie `laravel-permission`.
- **Observers/AuditService:** perilaku otomatis yang tidak boleh lupa ditulis manual, terutama **audit log**: setiap perubahan data sensitif (field anggota yang terkunci, keputusan verifikasi, koreksi presensi, serah terima transport, penutupan kas, hasil undian, dan lookup SHU publik) tercatat: siapa, kapan, nilai sebelum, nilai sesudah, alamat IP.

---

## 3. Aturan Service-Layer (Wajib)

1. **Controller dan komponen Livewire dilarang berisi rumus bisnis.** Yang ketahuan menghitung kuorum, menentukan tier transport, atau menyaring kriteria undangan di luar Service adalah bug arsitektur dan harus dipindahkan ke Service.
2. **Semua angka dan pilihan bisnis WAJIB dibaca dari konfigurasi, tidak boleh hardcode di kode.**
   - Konfigurasi ada dua lapis (detail di Bab 10): tabel `pengaturan` (global) dan kolom konfigurasi di tabel `rapat` + `rapat_transport_tier` (per rapat).
   - Contoh yang dilarang: `if ($hadir / $total >= 0.5) { ... }` (angka 0,5 hardcode), `if ($anggota->simpanan_wajib >= 1000000)` (ambang undangan hardcode), `$transport = 50000;` (tarif hardcode).
   - Contoh yang benar: `KuorumService::hitung($rapat)` membaca `persen_kuorum`, `kuorum_basis`, dan `kuorum_tambahan` dari baris `rapat`; `VerifikasiService` membaca `mode_kriteria`, `min_simpanan_wajib`, dan `maks_kolektibilitas` dari baris `rapat`; `TransportService` membaca `nominal_transport_dasar`, `grace_period_menit`, `batas_hangus_menit`, dan tier potongan dari `rapat_transport_tier`.
   - Pengecualian: hanya konstanta yang memang tidak akan pernah berubah oleh kebijakan (mis. jumlah detik dalam satu menit) boleh inline, dan tetap harus dikomentari.
3. **Service selalu membungkus tulis-berantai dalam DB transaction.** Contoh: presensi datang dicatat → status peserta diperbarui → menit terlambat, tier potongan, dan nominal transport final ditentukan. Ketiganya harus sukses, atau tidak ada yang berubah.
4. **Waktu resmi selalu waktu server.** `waktu_datang` dan `waktu_pulang` diambil dari jam server saat Service berjalan, tidak pernah dari input client. Ini penting karena potongan transport dan audit bergantung pada waktu yang tidak bisa dimanipulasi peserta. Pengecualian mode offline: perangkat scanner menyimpan `captured_at` lokal dalam antrean; saat sinkron, server memvalidasi perangkat, rapat, dan jendela acaranya, lalu hasil sinkron ditandai lewat `sumber = offline` serta `status_sinkron`.

---

## 4. Alur Data Contoh

### 4.1 Verifikasi sebagai Mekanisme Undangan (Setup Rapat → Daftar Peserta)

```
1. Setup      : admin membuat Rapat (status draft): jenis, tanggal & jam,
                lokasi, mode_presensi (1x / 2x), mode_kriteria, ambang
                kuorum, nominal transport dasar, grace period, batas
                hangus, dan tier potongan. Keuangan memeriksa konfigurasi
                nominal dasar serta tier potongan sebelum verifikasi dibuka.
2. Kandidat   : UndanganService menyusun antrean kandidat dari anggota aktif
                dan kriteria rapat (Mode A: simpanan wajib + kolektibilitas;
                Mode B: kelancaran pinjaman). Antrean ini belum membuat
                peserta dan belum merupakan undangan.
3. Verifikasi : petugas berizin memeriksa data dan memberi keputusan.
                - Verified: dalam satu transaksi, VerifikasiService
                  mengisi status_verifikasi = verified, verified_by,
                  verified_at, catatan_verifikasi (bila ada), dan
                  cara_undangan pada anggota, lalu MEMBUAT baris peserta
                  untuk rapat itu. Hasil penilaian kriteria dicatat;
                  bila hasilnya Tidak Layak, catatan_verifikasi wajib
                  diisi, tetapi status verified tetap menjadi syarat
                  pintu — peserta verified tetap dapat masuk.
                - Rejected: status_verifikasi = rejected, alasan_penolakan
                  wajib diisi, dan catatan_verifikasi wajib diisi; tidak
                  ada baris peserta yang dibuat. Setelah data dikoreksi,
                  status dapat dikembalikan ke pending untuk verifikasi
                  ulang.
4. Snapshot   : snapshot_simpanan_wajib, snapshot_kolektibilitas, dan
                snapshot_pinjaman_lancar disimpan HANYA di baris peserta
                yang dibuat pada langkah verified di atas. Snapshot adalah
                nilai kriteria saat verifikasi, tidak berubah walau data
                anggota berubah belakangan, dan tidak bisa diubah client.
5. Token QR   : QR tidak disimpan di peserta. QR memakai anggota.qr_token,
                token deterministik HMAC-SHA256(no_anggota/NBA, APP_KEY)
                yang unik per anggota. Token tidak pernah di-regenerate,
                tidak kedaluwarsa, dan pengiriman ulang selalu menghasilkan
                token/QR yang sama. APP_KEY tidak boleh diganti: perubahan
                APP_KEY merusak pencocokan token dan kode resi lama.
6. Kirim      : setelah verified, petugas memicu pengiriman secara
                on-demand untuk SATU anggota (bukan broadcast):
                WhatsAppService mengirim tautan undangan publik/QR lewat
                Fonnte atau mencetak undangan. Setiap usaha dicatat di
                wa_logs dengan jenis verifikasi, kirim_ulang, atau cetak,
                beserta target, status, metode, dan detail hasil.
7. Kunci      : setelah masa verifikasi ditutup, daftar peserta rapat
                dikunci. Perubahan status anggota sesudahnya tidak menambah
                atau menghapus peserta rapat yang sudah terbentuk.
```

### 4.2 Presensi Datang → Tanda Tangan (→ Pulang pada Mode 2x)

```
1. Scan       : petugas `scanner` yang LOGIN memindai QR pada rapat aktif.
                Server membaca token QR, menghitung ulang HMAC dari
                nomor anggota kandidat dan membandingkannya secara
                constant-time dengan anggota.qr_token, lalu memastikan
                status_verifikasi anggota adalah verified dan anggota itu
                punya baris peserta pada rapat aktif. Token sama berlaku lintas rapat;
                yang menentukan hak hadir adalah baris peserta rapat ini.
                QR bukan kredensial login. Bila token tidak dikenal,
                peserta tidak terverifikasi untuk rapat ini, atau QR gagal
                terbaca, peserta dialihkan ke helpdesk.
2. Manual     : helpdesk hanya boleh mencatat peserta TERUNDANG dengan
                sumber = ketik_manual, setelah verifikasi identitas, dengan
                alasan wajib dan catatan log audit. Peserta tidak terundang
                diteruskan ke admin; scanner tidak melakukan presensi
                manual dari daftar master.
3. Foto/TTD   : peserta difoto dan menandatangani kanvas digital di stasiun
                scanner. Berkas diunggah ke disk privat. Presensi tanpa
                tanda tangan BELUM SAH. Scanner hanya mengunggah media
                dalam alur ini; ia tidak dapat membukanya kembali lewat
                MediaController.
4. Catat      : PresensiService menulis dicatat_oleh (user petugas pencatat),
                sumber (scan_qr/ketik_manual/offline), status_sinkron,
                waktu server, foto, dan tanda tangan dalam satu transaksi.
5. Transport  : TransportService menghitung menit_terlambat dari waktu
                datang terhadap jam_mulai. Keterlambatan efektif adalah
                max(0, menit_terlambat - grace_period_menit). Tier dipilih
                dari rentang menit_min..menit_maks (menit_maks NULL berarti
                tanpa batas atas) mengikuti urutan. Rumusnya:
                nominal_transport_final =
                  max(0, nominal_transport_dasar - nominal_potongan).
                Hasil tier_id dan nominal final disimpan sebagai snapshot
                di presensi. Bila batas_hangus_menit terisi dan
                menit_terlambat melampauinya, tier_id NULL dan nominal
                final 0 (hak transport hangus), walaupun kehadiran tetap
                tercatat.
6. Resi/Bayar : serah terima tunai dilakukan scanner di titik scan dari
                kas meja yang menjadi tanggung jawabnya, sebesar nominal
                final sistem (scanner tidak boleh mengubah nominal).
                - Mode 1x: setelah presensi datang lengkap dan tunai
                  diserahkan, ResiService menerbitkan kode_resi HMAC di
                  presensi dan TransportService menandai pembayaran
                  selesai dalam transaksi yang sama.
                - Mode 2x: peserta wajib presensi pulang lebih dulu;
                  serah terima tunai dan penerbitan kode_resi dilakukan
                  pada titik pulang setelah presensi pulang lengkap.
                Kode resi unik tersimpan di presensi dan dapat diverifikasi
                publik tanpa membuka data sensitif. Keuangan kemudian
                memakai catatan pembayaran per kas_meja untuk rekonsiliasi.
7. Offline    : bila koneksi putus, ScanPresensi memakai snapshot minim data
                rapat aktif yang sudah diunduh ke perangkat (identitas
                peserta, token pencocokan, status peserta, konfigurasi
                waktu & transport) dan menyimpan event ke antrean lokal.
                Event lokal membawa client_event_id, captured_at, jenis
                event, token, serta berkas foto/TTD terenkripsi di
                perangkat. Saat online, SinkronisasiPresensiService
                mengirim antrean satu per satu; server menolak duplikat
                berdasarkan peserta + jenis event + client_event_id dan
                aturan state (pulang hanya sesudah datang). Baris hasil
                sinkron memakai sumber = offline dan status_sinkron berubah
                dari pending_sinkron menjadi tersinkron. Bila server sudah
                punya presensi online yang sah untuk event yang sama,
                data server menang dan duplikat offline hanya dicatat
                sebagai konflik sinkron di log audit.
8. Koreksi    : kesalahan catat hanya bisa dikoreksi admin lewat alur
                koreksi (alasan wajib). PresensiService mencatat pelaku,
                alasan, dan nilai sebelum/sesudah ke log_audit.
```

### 4.3 Undian Doorprize

```
1. Pool       : DoorprizeService menyusun pool HANYA dari peserta rapat ini
                yang berstatus hadir (punya presensi sah), dikurangi peserta
                yang sudah pernah menang di rapat yang sama.
2. Undi       : admin menekan tombol undi di komponen Livewire UndiDoorprize
                (doorprize dioperasikan admin; helpdesk/keuangan hanya boleh
                melihat daftar pemenang sesuai kebutuhan, scanner tidak);
                pemenang dipilih acak di server (bukan di JavaScript client).
3. Catat      : pemenang ditulis ke doorprize_pemenang dalam transaksi
                (rapat, hadiah, peserta, waktu, pengundi). Constraint UNIQUE
                (rapat_id, peserta_id) di database menjamin satu peserta
                maksimal menang satu kali per rapat walau tombol ditekan
                bersamaan.
```

---

## 5. Pola Livewire & Aturan Keamanannya (Pelajaran dari Aplikasi Lama)

Livewire dipakai untuk layar interaktif: meja presensi, dashboard kuorum, daftar peserta realtime, dan undian doorprize. Aplikasi lama runtuh keamanannya justru di Livewire: sebuah komponen `EditAnggota` menaruh field sensitif anggota (`status_anggota`, `simpanan_wajib`, `kolektibilitas`) sebagai **public property** — akibatnya nilai field itu bisa ditentukan dari sisi client dan anggota bisa mengubah status serta angka keuangannya sendiri. Aturan berikut bersifat wajib dan tidak bisa ditawar:

1. **Public property tidak pernah memuat field sensitif.** Public property hanya boleh berisi state tampilan dan input non-sensitif: kata kunci pencarian, ID baris yang sedang dipilih dari daftar yang sudah difilter server, nomor halaman, dan sejenisnya. Field sensitif anggota (`simpanan_wajib`, `kolektibilitas`, `pinjaman_lancar`, `status_anggota`) **dilarang** menjadi public property dalam bentuk apa pun.
2. **Setiap aksi memanggil otorisasi.** Method publik komponen Livewire yang mengubah data wajib memanggil `$this->authorize(...)` (atau `Gate::authorize`) di baris pertama. Tidak ada aksi tulis tanpa cek policy/permission, termasuk aksi yang tombolnya disembunyikan di UI.
3. **Nilai sensitif selalu dibaca ulang dari database, server-side.** Saat aksi berjalan, Service mengambil ulang nilai field sensitif dan status dari database — tidak pernah memakai nilai yang datang dari payload client, state komponen, atau hidden input. Client hanya boleh mengirim *identitas* (ID), bukan *nilai*.
4. **ID dari client selalu divalidasi kepemilikannya.** ID peserta/anggota yang dikirim client dicocokkan ulang dengan rapat yang sedang dibuka dan hak akses pengguna; ID di luar lingkup itu ditolak.
5. **Perubahan field sensitif hanya lewat jalur admin klasik.** Mengubah `simpanan_wajib`, `kolektibilitas`, `pinjaman_lancar`, dan `status_anggota` hanya boleh lewat Controller + FormRequest + Policy milik role `admin` berizin — tidak ada jalur Livewire untuk mengubahnya — dan selalu tercatat di log audit.
6. **Upload dari Livewire divalidasi seperti upload biasa.** Foto wajah dan tanda tangan divalidasi tipe serta ukurannya di server, disimpan ke disk privat dengan nama acak (Bab 8); nama berkas asli dari client tidak pernah dipakai sebagai jalur simpan.

Contoh pola yang **dilarang** (pola aplikasi lama):

```php
// SALAH — nilai sensitif bisa dikirim dari browser
class EditAnggota extends Component
{
    public $simpanan_wajib;   // ← public property sensitif
    public $kolektibilitas;   // ← public property sensitif

    public function simpan()
    {
        $this->anggota->update([
            'simpanan_wajib' => $this->simpanan_wajib, // ← percaya client
        ]);
    }
}
```

Contoh pola yang **benar**:

```php
// BENAR — client hanya mengirim token dari QR; nilai dibaca & ditulis server-side
class ScanPresensi extends Component
{
    public $qrToken;          // hanya token dari QR yang dipindai, bukan nilai sensitif

    public function catatDatang()
    {
        $this->authorize('presensi.scan');       // 1. otorisasi dulu (akun scanner yang login)
        PresensiService::catatDatangByToken(     // 2. anggota dicari dari anggota.qr_token & peserta rapat divalidasi server-side
            qrToken: $this->qrToken,
            foto: $this->foto,                   // divalidasi & disimpan privat
            oleh: auth()->user(),
        );
    }
}
```

---

## 6. Perhitungan Kuorum Realtime

Kuorum dipantau admin selama rapat berjalan; keuangan boleh melihat totalnya untuk rekonsiliasi, scanner hanya melihat ringkasan stasiun/kuorum, dan papan skor publik menampilkan agregat yang sama tanpa data personal.

- **Populasi selalu hanya anggota `jenis_anggota = biasa` dan berstatus aktif.** Anggota luar biasa boleh tercatat sebagai peserta/undangan bila kebijakan rapat mengizinkan, tetapi tidak masuk penyebut maupun pembilang kuorum.
- **Basis dipilih dari `rapat.kuorum_basis`:**
  - `total` — populasi adalah seluruh anggota aktif berjenis biasa yang menjadi populasi rapat.
  - `terverifikasi` — populasi adalah anggota aktif berjenis biasa dengan `status_verifikasi = verified` yang telah dibuatkan baris peserta untuk rapat ini.
- **Pembilang** adalah jumlah presensi sah (status hadir dan tanda tangan lengkap) pada rapat itu yang anggota terkaitnya berjenis biasa.
- **Rumus target:** `target_kuorum = floor(persen_kuorum × populasi) + kuorum_tambahan`, dengan persen dibaca sebagai proporsi (mis. 50 berarti 0,50) dan semua nilai dibaca dari konfigurasi rapat. Status sah tercapai bila `jumlah_hadir >= target_kuorum`. Nilai bawaan `kuorum_tambahan = 1` mempertahankan kaidah “lebih dari separuh” pada konfigurasi 50%, tanpa meng-hardcode angka 1 di kode.
- **Sumber kebenaran tunggal:** `KuorumService::hitung(Rapat $rapat)` menjalankan query agregat dari anggota, peserta, dan presensi. Tidak ada counter terpisah yang bisa selisih.
- **Penyajian realtime:** komponen Livewire `DashboardKuorum` memanggil `KuorumService` secara berkala memakai polling Livewire (`wire:poll`), lalu me-render ulang hadir, populasi, target, persentase, dan status sah/tidak. Papan skor publik memakai service yang sama, tetapi hanya menerima agregat teranonimkan.
- **Konsistensi:** koreksi presensi oleh admin dan sinkronisasi offline langsung tercermin pada polling berikutnya.

---

## 7. Database

- **Production & development: MySQL.** Satu database, satu schema, khusus eRAT — tidak berbagi tabel dengan aplikasi lain.
- **Testing: SQLite `:memory:`.** Setiap test Pest memakai database SQLite dalam memori yang di-refresh per test — cepat dan tanpa server tambahan.
- **Migration untuk semua perubahan skema.** Tidak ada perubahan tabel lewat SQL manual di server. Nama migration deskriptif: `create_anggota_table`, `add_mode_presensi_to_rapat_table`.
- **Tanpa tenant.** eRAT melayani satu koperasi; tidak ada kolom `company_id` dan tidak ada middleware tenant.
- **Nilai kriteria peserta adalah snapshot.** Nilai kriteria undangan disalin ke baris `peserta` tepat saat anggota dinyatakan verified untuk rapat (Bab 4.1) agar keputusan undangan bisa diaudit dan tidak berubah mengikuti perubahan data anggota.
- **Seeder untuk data awal:** role & permission (`admin` — nama lama `superadmin` di repo terdahulu — `helpdesk`, `scanner`, `keuangan`), pengaturan bawaan, dan akun petugas awal (minimal satu akun admin). **Tidak ada seeder akun anggota** — anggota koperasi bukan user dan tidak pernah dibuatkan akun login. Perintah: `php artisan db:seed`. Data contoh (anggota/rapat contoh) memakai seeder terpisah untuk development, tidak ikut seed production.
- Detail lengkap setiap tabel ada di `DATABASE_SCHEMA.md`.
- **APP_KEY adalah bagian dari integritas data.** `anggota.qr_token` dan `presensi.kode_resi` adalah HMAC berbasis `APP_KEY`; APP_KEY wajib dicadangkan aman bersama database dan tidak boleh diganti/rotasi sembarangan. Mengganti APP_KEY membuat token QR dan verifikasi resi lama tidak cocok.

---

## 8. Auth, Session & Keamanan Aplikasi

- **Login berbasis session + cookie** (pola standar Laravel/Breeze). Tidak ada token API dan tidak ada API publik. Akun login **hanya untuk petugas** (admin, helpdesk, scanner, keuangan). QR pada undangan peserta **bukan** kredensial login dan tidak membuka session apa pun — endpoint scan presensi tetap menolak request tanpa session petugas yang login. Empat pengecualian publik yang sempit adalah papan skor agregat, halaman undangan per token, formulir cek SHU, dan verifikasi resi; keempatnya tidak memberi akses area petugas.
- **Rate limiting login WAJIB.** Route login dibungkus throttle (mis. maksimal 5 percobaan gagal per menit per kombinasi email + IP, mengikuti pola throttle bawaan Laravel) dan tidak boleh dinonaktifkan atau dilonggarkan. Ini menutup celah aplikasi lama yang membiarkan login ditebak tanpa batas.
- **Password** disimpan sebagai hash bcrypt bawaan Laravel; tidak ada password yang disimpan/dicatat dalam bentuk teks.
- **Session** diregenerasi saat login, cookie bersifat httpOnly, dan CSRF token aktif di semua form dan aksi Livewire (bawaan Laravel, tidak dimatikan).
- **Otorisasi dua lapis:** (1) Spatie `laravel-permission` untuk role & permission kasar (menu apa yang terlihat, route apa yang bisa dibuka); (2) Policy per model untuk aturan halus (scanner hanya boleh mengakses endpoint scan rapat yang sedang aktif, menyerahkan tunai hanya di dalam alur scan sesuai nominal sistem, dan tidak bisa membuka data master atau arsip media apa pun; helpdesk tidak bisa mengubah field sensitif, konfigurasi, pembayaran, data SHU, koreksi presensi selesai, atau membuka media; keuangan tidak bisa mengubah presensi, kriteria rapat, atau doorprize, tetapi boleh membaca media untuk verifikasi pembayaran/resi serta mengelola kas dan rekonsiliasi). Otorisasi ditegakkan di server pada setiap aksi — menyembunyikan tombol di UI tidak pernah dianggap pengamanan.
- **Validasi upload ketat.** Foto wajah dan tanda tangan divalidasi tipe gambar dan ukuran maksimumnya di server sebelum disimpan; batas ukuran dibaca dari `pengaturan`.
- **Log audit menyeluruh.** Perubahan data sensitif dan koreksi data operasional dicatat lewat `AuditLogObserver` (untuk perubahan model) dan `AuditService` (untuk aksi proses seperti koreksi presensi, pembayaran transport, undian doorprize, lookup SHU publik): siapa/pelaku sistem, kapan, entitas, nilai sebelum, nilai sesudah, alamat IP. Lookup SHU publik dicatat dengan `user_id` NULL, nomor anggota dan tahun buku yang dicari, hasil ditemukan/tidak, serta IP — tanpa menulis nominal SHU ke log. Tabel `log_audit` bersifat append-only — tidak ada fitur ubah/hapus log dari aplikasi. Penampilnya (`AuditController`) hanya untuk role `admin`; helpdesk, scanner, dan keuangan tidak bisa membuka log audit.

---

### 8.1 Halaman Publik, WhatsApp, Kas Meja & Mode Offline

- **Papan skor publik:** `PublicController` menampilkan populasi, target, jumlah hadir, persentase, dan status kuorum dari `KuorumService`. Respons hanya agregat; tidak ada nama, nomor anggota, foto, nominal personal, atau daftar belum hadir.
- **Undangan publik per token:** URL undangan memakai `anggota.qr_token` sebagai kunci bearer yang tidak dapat ditebak. Halaman hanya menampilkan nama peserta, nama/judul rapat yang relevan, waktu, lokasi, QR yang sama, dan petunjuk hadir. Token tidak kedaluwarsa dan tidak berganti saat kirim ulang; karena itu halaman tidak boleh menampilkan field sensitif, SHU, nominal transport personal, atau data anggota lain, dan route diberi throttle serta header `noindex`.
- **Cek SHU publik:** formulir meminta nomor anggota (dan tahun buku) lalu memanggil `ShuService` lewat endpoint khusus. Endpoint di-throttle per IP + nomor anggota, memberi pesan generik untuk nomor tidak ditemukan, dan menulis jejak lookup ke `log_audit`. Ini bukan portal login dan tidak membuat session anggota.
- **Verifikasi resi publik:** formulir menerima `presensi.kode_resi`; `ResiService` mencocokkan HMAC/record unik dan hanya mengembalikan status valid/tidak, judul rapat, waktu pembayaran, dan nominal transport final. Nama lengkap, foto, tanda tangan, dan field sensitif tidak ditampilkan.
- **WhatsApp on-demand:** pengiriman selalu dipicu petugas untuk satu anggota dari antrean/detail verifikasi. `WhatsAppService` membungkus Fonnte/gateway WA, mengirim tautan undangan publik dan/atau QR, lalu menulis `wa_logs` untuk sukses maupun gagal. Tidak ada endpoint broadcast massal; kirim ulang memakai token yang sama dan dicatat sebagai jenis `kirim_ulang`.
- **Kas meja:** `KasService` mencatat `kas_meja` untuk satu rapat dan satu user scanner penanggung jawab, termasuk kas awal dari keuangan. Pembayaran yang diserahkan scanner di titik scan dikaitkan ke meja itu. Saat penutupan, keuangan membandingkan sisa kas fisik dengan sisa yang seharusnya; selisih dihitung server dan bila tidak nol, `catatan_selisih` wajib diisi sebelum rekonsiliasi selesai.
- **Mode offline scanner:** sebelum acara, perangkat scanner yang login mengunduh snapshot minim data rapat aktif dan menyimpannya terenkripsi di penyimpanan lokal. Selama offline, event masuk antrean lokal; sesudah online, `SinkronisasiPresensiService` memprosesnya secara idempoten. Snapshot lokal wajib dihapus setelah rapat selesai dan antrean kosong.

## 9. Storage: Foto Wajah & Tanda Tangan

Foto wajah dan tanda tangan digital adalah bukti kehadiran yang sensitif — perlakuannya berbeda dari berkas biasa:

- **Disk privat.** Semua foto wajah dan tanda tangan disimpan di disk privat Laravel (`storage/app/private`, disk non-publik), **bukan** di `public/` dan bukan di disk `public`. Tidak ada `storage:link` untuk folder ini.
- **Struktur folder per jenis:**

```
storage/app/private/
├── presensi/
│   ├── foto/           # foto wajah saat presensi datang & pulang
│   └── tanda-tangan/   # gambar tanda tangan digital peserta
└── shu/
    └── impor/          # berkas impor SHU yang diunggah keuangan
```

- **Nama berkas acak.** Nama berkas di server adalah nama acak (UUID) + ekstensi hasil validasi tipe berkas; nama asli dari client tidak pernah dipakai sebagai jalur simpan, untuk mencegah penimpaan berkas dan tebakan alamat.
- **Disajikan hanya lewat route berotorisasi.** Foto dan tanda tangan tidak punya URL publik. Penampilannya lewat `MediaController` yang memeriksa `MediaPolicy`: **hanya `admin` dan `keuangan`** yang boleh membaca/menampilkan arsip foto dan tanda tangan — admin untuk arsip/audit, keuangan untuk verifikasi bukti pembayaran dan resi. `helpdesk` **tidak boleh** membuka media. `scanner` hanya boleh **mengunggah** foto/tanda tangan melalui endpoint tulis `PresensiService` selama alur scan aktif; endpoint itu tidak mengembalikan URL atau isi arsip, dan scanner tidak punya akses baca media. Setiap jalur berkas di database (`foto_datang_path`, `foto_pulang_path`, `tanda_tangan_path`) hanya berisi path relatif di disk privat, bukan URL.

---

## 10. Konfigurasi Bisnis

Semua angka dan pilihan bisnis hidup di konfigurasi, terbagi dua lapis:

**Lapis 1 — tabel `pengaturan` (global, key-value):** nama koperasi, batas ukuran unggahan foto, dan pengaturan umum lain yang berlaku lintas rapat. Dibaca aplikasi lewat model `Pengaturan` sebagai satu pintu; tidak ada nilai kebijakan yang dibaca langsung dari `.env` atau konstanta kode selain kredensial teknis.

**Lapis 2 — konfigurasi per rapat:** pilihan dan ambang yang memang berbeda antar rapat disimpan sebagai kolom di tabel `rapat` dan tabel anaknya:

| Konfigurasi | Disimpan di |
|---|---|
| Mode presensi (`1x` / `2x`) | `rapat.mode_presensi` |
| Mode kriteria undangan + ambangnya (min. simpanan wajib, maks. kolektibilitas) | `rapat.mode_kriteria`, `rapat.min_simpanan_wajib`, `rapat.maks_kolektibilitas` |
| Basis, persen & tambahan kuorum | `rapat.kuorum_basis`, `rapat.persen_kuorum`, `rapat.kuorum_tambahan` |
| Nominal transport dasar, masa tenggang & batas hangus | `rapat.nominal_transport_dasar`, `rapat.grace_period_menit`, `rapat.batas_hangus_menit` |
| Tier potongan uang transport (rentang menit terlambat + potongan + urutan) | `rapat_transport_tier.menit_min`, `rapat_transport_tier.menit_maks`, `rapat_transport_tier.nominal_potongan`, `rapat_transport_tier.urutan` |

Konsekuensinya: mengganti aturan untuk rapat berikutnya cukup dilakukan dari layar petugas — admin untuk setup rapat & kriteria, keuangan untuk nominal dasar dan tier potongan transport — tanpa menyentuh kode, tanpa deploy ulang. Nilai bawaan (mis. `persen_kuorum` 50) hanya berlaku sebagai nilai awal saat rapat dibuat dan tetap tersimpan sebagai data, bukan konstanta program.

---

## 11. Lingkungan Jalan & Deployment

- **Pengembangan:** Laragon di Windows dengan **PHP 8.3.36**, MySQL lokal. Aplikasi dikembangkan dan didemokan dari lingkungan ini.
- **Produksi:** shared hosting atau VPS. Kebutuhan jalan hanya **satu proses PHP + satu MySQL**:
  - tidak ada worker antrean yang wajib hidup terus (semua proses bersifat sinkron; skala RAT tidak memerlukannya);
  - tidak ada server websocket (kuorum realtime memakai polling Livewire);
  - integrasi WhatsApp bersifat request on-demand ke Fonnte/gateway, bukan daemon broadcast; kegagalan gateway dicatat di `wa_logs` dan tidak menghalangi jalur cetak;
  - mode offline scanner memakai penyimpanan lokal browser/perangkat dan endpoint sinkronisasi saat koneksi kembali, bukan server tambahan;
  - tidak ada Node.js di server — satu-satunya build step adalah kompilasi aset Tabler memakai **Vite** saat pengembangan, dan hasil build-nya ikut diunggah saat deploy.
- **Perubahan skema** di semua lingkungan hanya lewat migration (`php artisan migrate` atau mekanisme panel hosting yang setara), tidak pernah SQL manual.
- **Cadangan:** foto wajah, tanda tangan, dan database adalah aset audit; pencadangan folder `storage/app/private` dan database MySQL menjadi bagian dari prosedur operasional admin (di luar cakupan kode aplikasi).

---

## 12. Prinsip Modul

1. **Satu domain = satu paket utuh.** Setiap domain (anggota, rapat, verifikasi/peserta, presensi, transport, kas meja, log WA, SHU, doorprize, publik) memiliki Model + Controller/komponen Livewire + Service + view Blade-nya sendiri. Menambah domain baru berarti menambah paket baru, bukan menempelkan logic ke paket yang sudah ada.
2. **Dilarang logic lintas domain di controller/komponen.** Contoh yang dilarang: `ScanPresensi` langsung mengubah tabel `transport_pembayaran`. Yang benar: komponen memanggil `PresensiService::catatDatangByKode()`, dan di dalam service itulah `TransportService` dipanggil untuk menentukan tier.
3. **View hanya menampilkan.** Blade tidak boleh menghitung (tidak ada aritmatika bisnis di `@php`), tidak boleh query database langsung. Semua angka sudah dihitung service dan diformat helper `Support`.
4. **Konfigurasi terpusat.** Semua angka bisnis hidup di `pengaturan` atau konfigurasi per rapat (Bab 10) dan dibaca lewat Service. Tidak ada angka kebijakan yang tersebar di banyak file.
5. **Setiap perubahan data sensitif tercatat.** Field sensitif anggota, keputusan verifikasi, presensi, serah terima transport, penutupan kas, dan hasil undian memakai log audit; percobaan kirim/cetak undangan memakai `wa_logs`, dan lookup SHU publik memakai log audit — siapa pun yang mengubah bisa dilacak: siapa, kapan, nilai sebelum, nilai sesudah.

---

*Dokumen ini adalah acuan arsitektur. Perubahan arsitektur (mis. menambah API publik, mengubah pola Livewire, menambah domain baru) wajib memperbarui dokumen ini terlebih dahulu sebelum kode ditulis.*
