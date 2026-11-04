# Acceptance Criteria — eRAT (Electronic Rapat Anggota Tahunan)

> Versi 1.0 — 23 Oktober 2026
> Status: Draf awal hasil diskusi kebutuhan

Kriteria penerimaan eRAT. Setiap baris adalah checklist yang harus bisa diverifikasi secara konkret: lakukan langkahnya, lihat hasilnya, centang jika sesuai. Build stack: Laravel 13 + PHP 8.3 + MySQL, Blade + Livewire, UI Tabler. Semua UI berbahasa Indonesia. Test berjalan dengan Pest di SQLite `:memory:`.

---

## 1. Anggota

- [ ] Admin bisa menambah anggota baru: nomor anggota, nama, kontak, alamat, status anggota, dan **jenis anggota** tersimpan dengan benar. Jenis anggota diperlukan karena hanya jenis **`biasa`** yang dihitung dalam kuorum.
- [ ] Nomor anggota tidak bisa duplikat: menyimpan nomor yang sudah ada ditolak dengan pesan yang jelas.
- [ ] Admin bisa mengubah data anggota, termasuk field sensitif (saldo simpanan wajib, kolektibilitas/NPL, status kelancaran pinjaman, status anggota), dan perubahan tersimpan benar.
- [ ] Setiap perubahan field sensitif tercatat di log audit: siapa yang mengubah, kapan, nilai sebelum, dan nilai sesudah.
- [ ] **Field sensitif TIDAK berubah lewat request yang tidak berizin — dibuktikan dengan test:** user tanpa izin mengubah (termasuk lewat manipulasi parameter atau Livewire property dari sisi client) ditolak, dan nilai di database tetap seperti semula.
- [ ] Impor data anggota dari file berhasil untuk baris yang valid; baris yang rusak ditolak dan dilaporkan per baris (nomor baris + alasan), bukan gagal total tanpa penjelasan.
- [ ] Daftar anggota bisa dicari berdasarkan nama/nomor anggota dan difilter berdasarkan status anggota, kolektibilitas, dan status kelancaran pinjaman.
- [ ] Anggota berstatus nonaktif tidak ikut terpilih saat daftar undangan rapat dibangkitkan.

## 2. Rapat & Undangan

- [ ] Admin bisa membuat rapat baru: jenis rapat (RAT atau rapat biasa), judul, tanggal & jam, dan lokasi tersimpan dengan benar.
- [ ] Saat setup rapat, admin **memilih mode presensi** (1x = datang saja, atau 2x = datang + pulang); pilihan tersimpan per rapat dan rapat berikutnya bisa memakai mode yang berbeda.
- [ ] Saat setup rapat, admin **memilih mode kriteria undangan** beserta nilai ambangnya; tidak ada nilai kriteria yang hardcoded:
  - Mode A: simpanan wajib >= minimum DAN kolektibilitas/NPL <= maksimum (kedua ambang diisi per rapat).
  - Mode B: berdasarkan status kelancaran pinjaman (lancar / tidak lancar) yang dipilih per rapat.
- [ ] Daftar undangan final hasil bangkitan **cocok 100%** dengan kriteria yang dipilih dan hanya berisi anggota berstatus verifikasi **`verified`** dengan hasil kelayakan **Layak**, diverifikasi terhadap hitungan manual pada data uji (untuk Mode A maupun Mode B). Anggota `pending` atau `rejected` tidak masuk daftar undangan.
- [ ] Setiap undangan menyimpan **snapshot** nilai kriteria saat dibangkitkan (saldo simpanan wajib & kolektibilitas untuk Mode A, atau status pinjaman untuk Mode B); mengubah data anggota setelah undangan dibangkitkan TIDAK mengubah snapshot tersebut.
- [ ] Target kuorum bisa diatur per rapat dengan rumus **floor(persen × populasi) + tambahan**; nilai bawaan konfigurasi adalah persen 50% dan tambahan 1, tetapi keduanya dibaca dari konfigurasi rapat, bukan hardcoded.
- [ ] Daftar undangan per rapat bisa dilihat, dicari, dan diekspor untuk kebutuhan panitia.
- [ ] Setiap anggota memiliki **token QR deterministik per anggota**, diturunkan di server dari **nomor anggota** memakai **HMAC dengan `APP_KEY`**. Selama nomor anggota dan `APP_KEY` tidak berubah, token hasil pembentukan berulang **persis sama**; tidak ada token acak baru per rapat atau per pengiriman.
- [ ] **Kirim ulang undangan memakai token yang sama persis:** QR pada undangan pertama dan QR pada pengiriman ulang identik. Sistem **tidak menyediakan fitur regenerate token**, dan token anggota tetap sama selama aplikasi hidup.
- [ ] Pengiriman undangan lewat WhatsApp bersifat **on-demand per peserta** dari halaman/tindakan petugas untuk peserta terverifikasi; tidak ada pengiriman broadcast massal.

### 2.1 Verifikasi Pra-Rapat

- [ ] Setiap calon peserta rapat mulai dengan status verifikasi **`pending`**. Status hanya dapat berubah menjadi **`verified`** atau **`rejected`** oleh petugas berizin, dan setiap perubahan mencatat **identitas verifikator serta waktu verifikasi**.
- [ ] Status verifikasi dan hasil kelayakan adalah dua hal terpisah. Hasil **Tidak Layak** wajib memiliki catatan alasan; anggota yang status verifikasinya `verified` tetapi hasilnya Tidak Layak **tetap bisa masuk** melalui pintu setelah tokennya terverifikasi, dan kehadirannya ditandai sebagai peserta tanpa undangan formal pada laporan.
- [ ] Anggota berstatus **`rejected`** tidak menerima undangan dan tidak dapat masuk. Penolakan wajib memiliki catatan koreksi; setelah data dikoreksi, catatan dapat dikembalikan ke `pending` dan **diverifikasi ulang**, dengan riwayat verifikator dan waktu setiap keputusan tetap tercatat.
- [ ] Anggota berstatus `pending` juga belum menerima undangan dan belum dapat masuk. **Satu-satunya syarat pintu adalah status verifikasi `verified`** untuk rapat tersebut; scanner tidak boleh menolak peserta `verified` hanya karena hasil kelayakannya Tidak Layak, dan tidak boleh menerima `pending`/`rejected` dengan alasan apa pun selain proses verifikasi ulang yang selesai lebih dulu.

## 3. Presensi

- [ ] Alur lengkap satu peserta di stasiun scanner (scan QR undangan → presensi datang → ambil foto wajah → tanda tangan digital) selesai dalam waktu **kurang dari 30 detik** pada perangkat petugas.
- [ ] Presensi tercatat dengan waktu datang sesuai jam server, foto wajah tersimpan, dan status peserta berubah menjadi hadir.
- [ ] Presensi **duplikat tertolak**: peserta yang sudah presensi untuk rapat yang sama tidak bisa dipresensikan kedua kali; muncul pesan yang jelas.
- [ ] Peserta berstatus verifikasi **`pending`** atau **`rejected`** tidak bisa dipresensikan. Peserta **`verified`** yang tidak memiliki undangan formal karena hasil kelayakan Tidak Layak tetap bisa dipresensikan oleh scanner dan ditandai tanpa undangan formal; status verifikasi adalah satu-satunya syarat pintu.
- [ ] Setelah presensi, peserta melakukan tanda tangan digital; presensi tanpa tanda tangan berstatus belum tervalidasi, dan presensi dengan tanda tangan berstatus valid.
- [ ] Mode presensi **1x** berperilaku benar: cukup presensi datang + tanda tangan, tidak ada kewajiban presensi pulang.
- [ ] Mode presensi **2x** berperilaku benar: presensi pulang bisa dicatat setelah presensi datang; laporan membedakan peserta "sudah datang, belum pulang" dan "sudah pulang".
- [ ] Koreksi presensi (mengubah waktu atau status) hanya bisa dilakukan admin berizin, wajib disertai alasan, dan **tercatat di log audit** (siapa, kapan, nilai sebelum/sesudah).
- [ ] User **scanner** berhasil melakukan scan QR untuk presensi datang pada rapat aktif (mode 1x dan 2x) dan scan QR presensi pulang pada mode 2x; setelah scan, scanner mengambil foto wajah dan tanda tangan peserta di alur yang sama.
- [ ] **Helpdesk** bisa melakukan presensi manual HANYA untuk peserta terundang berstatus `verified` yang QR-nya gagal, wajib mengisi alasan, dan tercatat di log audit; presensi manual tanpa alasan ditolak. Peserta `pending`/`rejected` yang datang ke helpdesk diteruskan ke proses koreksi dan verifikasi ulang oleh petugas berizin — helpdesk tidak bisa mempresensikannya sebelum statusnya menjadi `verified`.
- [ ] User scanner dan keuangan **tidak bisa** melakukan presensi manual maupun koreksi presensi (aksi langsung lewat URL/request pun ditolak server).
- [ ] **Mode offline scanner anti-duplikat:** scan yang dilakukan saat koneksi terputus tersimpan lokal dengan identitas operasi unik dan masuk antrean sinkronisasi. Saat sinkronisasi, jika anggota yang sama ternyata sudah tercatat hadir melalui jalur online/stasiun lain, entri offline tersebut **ditolak, tidak membuat presensi kedua, tidak menggandakan serah terima transport**, dan muncul pada laporan hasil sinkronisasi untuk ditindaklanjuti petugas. Sinkronisasi ulang antrean yang sama juga tidak boleh menggandakan data.

## 4. Kuorum

- [ ] Target kuorum dihitung dengan rumus **floor(persen × populasi) + tambahan** dari konfigurasi rapat. Contoh: populasi 101 dengan persen 50% dan tambahan 1 menghasilkan target **51**; populasi 100 menghasilkan target **51**.
- [ ] Hanya anggota dengan **jenis anggota `biasa`** yang masuk pembilang dan penyebut kuorum. Anggota jenis lain tidak memengaruhi jumlah hadir kuorum, populasi, maupun persentase, meskipun datanya ada di sistem.
- [ ] Basis populasi mengikuti konfigurasi rapat: **total** (seluruh populasi anggota `biasa` untuk rapat) atau **terverifikasi** (hanya anggota `biasa` berstatus `verified` untuk rapat). Mengganti basis pada data uji mengubah penyebut dan target sesuai rumus, tanpa mengubah presensi yang sudah tercatat.
- [ ] Angka jumlah hadir, populasi, target, dan persentase kuorum di dashboard **cocok persis** dengan hitungan manual pada data uji.
- [ ] Status kuorum berubah menjadi **"sah" tepat pada saat jumlah hadir mencapai target** — terverifikasi pada uji batas: satu peserta di bawah target → status belum sah; presensi berikutnya yang mencapai target → status sah.
- [ ] Dashboard kuorum terbarui otomatis setelah presensi baru tersimpan dengan **jeda pembaruan <= 10 detik**, tanpa panitia perlu me-refresh manual.
- [ ] User keuangan dan helpdesk bisa melihat daftar hadir dan angka kuorum tanpa bisa mengubah data presensi; user scanner hanya melihat ringkasan stasiun/kuorum rapat aktif di layar scan.

## 5. Uang Transport

- [ ] Aturan uang transport diatur **per rapat oleh user keuangan** dan dibaca dari konfigurasi rapat (nominal dasar, masa tenggang, tier potongan berdasarkan keterlambatan, dan batas hangus); tidak ada angka yang hardcoded; admin, helpdesk, dan scanner tidak bisa mengubah aturan atau nominal hasil hitung.
- [ ] **Contoh hitung wajib lolos** pada rapat uji dengan jam mulai **08:00**, nominal dasar **Rp100.000**, masa tenggang **15 menit**, tier keterlambatan **16–30 menit** dengan potongan **Rp25.000**, dan batas hangus **120 menit**:
  - datang **08:10** → **Rp100.000** (masih dalam masa tenggang);
  - datang **08:20** → **Rp75.000** (tier 16–30 menit);
  - datang **10:30** → **Rp0** (150 menit setelah mulai, melewati batas hangus 120 menit).
- [ ] Uang transport **diserahkan secara tunai oleh scanner di titik scan**, berdasarkan nominal yang dihitung sistem. Pada mode presensi **1x**, serah terima terjadi saat presensi datang; pada mode **2x**, serah terima terjadi saat presensi pulang dan **resi terbit** untuk peserta.
- [ ] Scanner hanya mengonfirmasi serah terima tunai pada alur scan; scanner tidak bisa mengetik/mengubah nominal, menandai peserta lain sebagai menerima, atau mengulang serah terima yang sudah tercatat.
- [ ] **Tidak ada serah terima ganda:** peserta yang sama tidak bisa menerima uang transport dua kali untuk rapat yang sama, termasuk bila ada percobaan scan ulang atau sinkronisasi offline berulang; upaya kedua ditolak dan tercatat.
- [ ] Status transport tercatat per peserta (belum menerima / sudah menerima, nominal, waktu, mode presensi, meja/stasiun scanner, dan petugas scanner yang menyerahkan).
- [ ] User **keuangan** mencatat **kas awal per meja/stasiun** sebelum acara dan **sisa kas fisik** setelah acara. Sistem menghitung sisa kas yang seharusnya dari kas awal dikurangi total tunai yang diserahkan.
- [ ] **Rekonsiliasi kas wajib tuntas:** selisih antara sisa kas fisik dan sisa kas menurut sistem harus **Rp0**, atau rekonsiliasi tidak bisa ditutup sebelum keuangan mengisi **catatan selisih** yang menjelaskan penyebabnya. Total rekap transport, total per meja, kas awal, sisa kas, dan selisih tampil pada laporan keuangan.

## 6. SHU

- [ ] Cek SHU tersedia sebagai **halaman publik tanpa login dan tanpa portal/akun anggota**: pengunjung mengisi **nomor anggota** dan memilih tahun buku, lalu sistem menampilkan data SHU nomor tersebut untuk tahun yang dipilih.
- [ ] User **keuangan** bisa menginput, mengimpor, dan mengoreksi data SHU per anggota per tahun buku, termasuk komponen **balas jasa saham** dan **balas jasa pinjaman** serta totalnya; setiap koreksi tercatat di log audit (siapa, kapan, nilai sebelum/sesudah).
- [ ] Halaman publik hanya menampilkan data untuk nomor anggota dan tahun buku yang diminta; data tahun buku lain tidak ikut tampil, dan field internal/kriteria anggota tidak ditampilkan.
- [ ] **Throttle anti-enumerasi aktif pada form publik:** percobaan lookup berurutan/melebihi batas konfigurasi dari sumber yang sama dibatasi dan menerima penolakan sementara (HTTP 429 atau pesan setara). Nomor yang tidak ditemukan menghasilkan pesan umum "Data tidak ditemukan" tanpa petunjuk tambahan tentang format, rentang, atau keberadaan nomor lain.
- [ ] **Setiap lookup publik tercatat di audit lookup:** waktu, nomor anggota yang dicari, tahun buku, hasil ditemukan/tidak ditemukan, dan alamat IP/sumber permintaan. Log ini hanya dapat dibuka petugas berizin, dan halaman publik tidak menampilkan isi log.
- [ ] User helpdesk dan scanner tidak memiliki fitur lookup atau pengelolaan SHU di area petugas; helpdesk tidak dapat membuka data SHU peserta dari konsolnya, dan scanner tidak memiliki akses SHU sama sekali.

## 7. Doorprize

- [ ] Admin bisa mengelola daftar hadiah doorprize per rapat (nama hadiah, jumlah).
- [ ] Undian **hanya memilih dari peserta berstatus hadir** pada rapat tersebut; peserta tidak hadir tidak pernah terpilih (terverifikasi dengan undian berulang pada data uji).
- [ ] Peserta yang sudah menang di rapat yang sama **tidak masuk undian berikutnya**: satu peserta maksimal menang satu kali per rapat.
- [ ] Setiap pemenang tercatat lengkap (hadiah, peserta pemenang, waktu undian) dan daftar pemenang bisa dilihat kembali pada laporan rapat.
- [ ] User helpdesk dan keuangan hanya bisa **melihat** daftar pemenang seperlunya; user scanner tidak memiliki akses doorprize sama sekali.

## 8. Keamanan

- [ ] **Rate limiting login aktif:** setelah 5 kali percobaan login gagal berurutan, percobaan berikutnya dibatasi/ditolak sementara — dibuktikan dengan test otomatis.
- [ ] Foto wajah dan tanda tangan digital tersimpan di **disk privat**: membuka URL file secara langsung tanpa login/otorisasi gagal (403/404). Akses baca arsip foto/tanda tangan **HANYA** diberikan kepada **admin dan keuangan** melalui route berotorisasi; user **helpdesk secara eksplisit ditolak (403)** meskipun sedang menangani kasus peserta, dan scanner tidak dapat membuka arsip di luar pengambilan media pada alur scan aktif.
- [ ] **Log audit memuat semua perubahan data sensitif selama pengujian:** setiap perubahan field sensitif anggota dan setiap koreksi presensi yang dilakukan selama sesi uji muncul di log audit — tidak ada yang lolos tanpa catatan.
- [ ] Otorisasi ditegakkan di server, bukan hanya di tampilan: upaya menjalankan aksi di luar izin role-nya secara langsung (URL/request) ditolak untuk semua role — scanner membuka master anggota/SHU/pembayaran/laporan, helpdesk mengubah field sensitif/konfigurasi/pembayaran/data SHU, keuangan mengubah kriteria rapat/presensi/doorprize/pengaturan.
- [ ] **Tidak ada akun atau portal anggota:** peserta bukan user login; tidak ada halaman yang bisa dibuka peserta dengan akun sendiri. Satu-satunya kredensial peserta adalah QR undangan, dan QR **bukan** kredensial login — mengetahui/memindai kode QR tidak memberi akses ke halaman petugas mana pun.

## 9. Peran Petugas & QR Undangan

- [ ] Sistem memiliki **tepat 4 role login**: `admin`, `helpdesk`, `scanner`, `keuangan` — tidak ada role lain (tidak ada role `pengurus` atau `anggota`), dan tidak ada akun login untuk peserta.
- [ ] Scan QR dengan token yang **tidak dikenal** ditolak dengan pesan yang jelas; token anggota yang tidak memiliki catatan peserta dan status `verified` untuk **rapat aktif** juga ditolak di pintu.
- [ ] **Tidak ada regenerate QR/token:** tidak ada tombol, menu, endpoint, atau tindakan petugas yang dapat membuat token baru untuk anggota yang sama. Pembentukan ulang token dari nomor anggota dan `APP_KEY` yang sama selalu menghasilkan nilai yang **persis sama** dengan token pada undangan pertama.
- [ ] Endpoint scan QR hanya bisa dipakai oleh user petugas yang login dan berizin; membuka endpoint scan tanpa login ditolak.
- [ ] User scanner yang login hanya diarahkan ke layar scan rapat aktif; menu master anggota, konfigurasi, SHU, pembayaran, doorprize, laporan, dan audit tidak tersedia dan tidak bisa diakses langsung.
- [ ] User helpdesk bisa menampilkan/mengirim ulang undangan peserta **setelah verifikasi identitas**, dan QR pada pengiriman ulang harus **identik** dengan QR pertama karena tokennya sama; helpdesk bisa melihat status hadir serta status transport peserta, tetapi tidak bisa membuat token baru, melihat arsip foto/tanda tangan, atau mengubah konfigurasi.

## 10. Halaman Publik

- [ ] **Papan skor publik hanya menampilkan data agregat:** jumlah hadir, populasi, target dan persentase kuorum, status kuorum, serta agregat transport yang aman ditampilkan. Halaman ini tidak memuat nama, nomor anggota, foto, tanda tangan, SHU, status individu, atau nominal per peserta.
- [ ] **Undangan publik per token:** tautan berisi token deterministik anggota membuka undangan yang sesuai dengan rapat dan status verifikasinya, beserta QR yang sama. Token tidak dikenal menghasilkan halaman tidak ditemukan/pesan umum; token tidak memberi akses ke konsol petugas atau data anggota lain.
- [ ] **Verifikasi resi bersifat publik dalam format teks terbuka:** kode resi yang diterbitkan setelah serah terima transport pada mode 2x dapat diverifikasi tanpa login. Hasil valid menampilkan informasi minimum untuk mencocokkan resi (status valid, rapat, waktu serah terima, dan nominal); kode tidak dikenal atau sudah tidak berlaku menghasilkan pesan tidak valid tanpa membuka data peserta lain.
- [ ] Halaman publik selain papan skor, undangan per token, cek SHU, dan verifikasi resi tidak membuka data personal. Mengubah token/kode pada URL tidak boleh menampilkan data milik anggota lain.

## 11. Kinerja & Operasional

- [ ] **100 presensi berurutan** (data uji) diproses tanpa error, tanpa presensi yang hilang, dan tanpa duplikat.
- [ ] Halaman presensi tetap responsif dipakai di **tablet dan laptop panitia** selama simulasi beban di atas (pencarian peserta dan penyimpanan presensi tidak terasa tersendat).
- [ ] Seluruh Pest test suite hijau (0 failed) dijalankan dengan SQLite `:memory:`.
- [ ] Tidak ada error 500 pada seluruh alur utama: login → kelola anggota → setup rapat → bangkitkan undangan → presensi → kuorum → transport → SHU → doorprize → laporan.
- [ ] Seluruh teks UI berbahasa Indonesia: tidak ada label, tombol, atau pesan error berbahasa Inggris yang terlihat pengguna.
- [ ] Format tanggal Indonesia (DD/MM/YYYY) dan format angka/Rupiah Indonesia (Rp 1.500.000) konsisten di seluruh tampilan dan ekspor.
