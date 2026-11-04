# TESTING.md — Strategi Pengujian eRAT (Electronic Rapat Anggota Tahunan)

Versi 1.0 — 23 Oktober 2026
Status: Draf awal hasil diskusi kebutuhan

Dokumen ini adalah aturan main pengujian untuk **eRAT** — sistem informasi manajemen Rapat Anggota Tahunan koperasi berbasis web (Laravel 13 + PHP 8.3 + MySQL, Blade + Livewire, UI Tabler, bahasa antarmuka Indonesia).
Framework pengujian: **Pest**. Database pengujian: SQLite `:memory:`.
Cakupan dokumen ini: autentikasi & rate limit, anggota & field sensitif, rapat & kriteria undangan, verifikasi pra-rapat, token QR HMAC deterministik, presensi berbasis scanner (scan, ketik manual, dan offline), pengecualian helpdesk, kuorum, uang transport dan rekonsiliasi kas, halaman publik, SHU, doorprize, log audit, dan otorisasi empat role login (`admin`, `helpdesk`, `scanner`, `keuangan`).

---

## 1. Strategi Pengujian

### 1.1 Tiga jenis kasus di setiap modul

Setiap skenario uji di dokumen ini termasuk salah satu dari tiga jenis kasus, dan setiap modul **wajib** punya ketiganya:

| Jenis kasus | Maksudnya | Contoh |
|---|---|---|
| **Kasus bahagia** | Alur normal berjalan dan hasilnya benar | Presensi datang tercatat lengkap dengan foto dan tanda tangan |
| **Kasus batas** | Nilai tepat di tepi aturan (ambang, batas waktu, kuota) | Simpanan wajib tepat sama dengan minimum → tetap terundang |
| **Kasus jahat** | Request yang sengaja melanggar aturan atau izin | Mengubah `simpanan_wajib` lewat payload Livewire tanpa hak → ditolak, data tidak berubah |

### 1.2 Test-Driven Development (TDD) untuk service bisnis

Untuk setiap service bisnis, **tulis test-nya dulu, baru implementasi**. Service yang wajib dibangun dengan TDD:

| Service | Tanggung jawab |
|---|---|
| `UndanganService` | Membangkitkan kandidat dari kriteria dinamis (Mode A / Mode B), menjalankan transisi verifikasi dengan snapshot saat verifikasi, dan menerbitkan undangan untuk semua `verified` (Layak maupun Tidak Layak) |
| `KuorumService` | Hitung target `floor(persen × populasi) + tambahan`, hanya dari jenis anggota `biasa`, pada basis populasi total/terverifikasi, lalu tentukan status sah/belum sah |
| `TransportService` | Hitung nominal dasar − potongan berdasarkan keterlambatan, grace period, dan batas hangus; cegah pembayaran ganda; hitung kas keluar dan rekonsiliasi |

Siklusnya: **Red** (test gagal karena belum ada implementasi) → **Green** (implementasi minimal sampai test lolos) → **Refactor** (rapikan tanpa merusak test).

### 1.3 Feature test untuk alur HTTP & Livewire

Setiap alur yang penting wajib punya feature test (request masuk → respons + efek ke database):

- Login (sukses, gagal, kena rate limit) untuk empat role login: `admin`, `helpdesk`, `scanner`, dan `keuangan`.
- CRUD anggota oleh admin; penolakan ubah field sensitif oleh yang tidak berhak.
- Buat rapat + atur konfigurasi → bangkitkan kandidat → verifikasi pra-rapat di panel Kelola Anggota → terbitkan undangan untuk semua `verified` → kunci daftar. Pengiriman/pengiriman ulang undangan selalu memakai token HMAC yang sama persis.
- Presensi di stasiun scanner: scan QR atau ketik manual nomor anggota untuk peserta `verified` (+foto + tanda tangan) → scan/ketik pulang (mode 2x); mode offline dan sinkronisasi anti-duplikat; pengecualian presensi manual oleh helpdesk; koreksi oleh admin.
- Keuangan memantau konfigurasi transport yang diatur admin, mengelola kas awal per meja, dan melakukan rekonsiliasi; scanner menyerahkan tunai di titik scan (mode 1x saat datang; mode 2x saat pulang + resi).
- Halaman publik: papan skor agregat tanpa data personal, undangan berdasarkan token, cek SHU dengan form nomor anggota + throttle + audit lookup, dan verifikasi resi teks.
- Pengelolaan data SHU (balas jasa saham & pinjaman) oleh keuangan.
- Undian doorprize oleh admin dari peserta hadir.

Komponen Livewire diuji lewat mekanisme test Livewire (memanggil aksi komponen dan memeriksa efeknya ke database), termasuk aksi yang **seharusnya ditolak**.

### 1.4 Unit test untuk penghitung murni

Fungsi murni (tidak menyentuh database) diuji sebagai unit test:

- Penghitung target kuorum dari persen, populasi, dan tambahan; penyaring jenis anggota `biasa`.
- Penghitung keterlambatan dan nominal transport dari jam mulai, waktu datang, nominal dasar, grace period, tier potongan, dan batas hangus.
- Penilai kriteria undangan Mode A (perbandingan `>=` dan `<=`) dan Mode B (status kelancaran).
- Derivasi token HMAC dari nomor anggota dan `APP_KEY` test: hasil berulang harus identik.

### 1.5 Aturan wajib

1. **Setiap service bisnis punya test.** Tidak ada service bisnis tanpa test.
2. **Test hijau adalah syarat fase selesai.** Sebuah fase/modul hanya boleh dinyatakan selesai bila seluruh suite Pest hijau dan sudah direview Chandra (lihat `IMPLEMENTATION_PLAN.md`).
3. **Setiap bug yang diperbaiki wajib disertai regression test** — test yang gagal sebelum perbaikan dan lolos sesudahnya, agar bug yang sama tidak muncul lagi. Ini terutama berlaku untuk celah keamanan aplikasi lama (lihat 3.2).
4. **Jangan menghapus atau melemahkan test supaya hijau.** Kalau test gagal karena aturan bisnis berubah, konfirmasi ke Chandra dulu.
5. Semua angka contoh di test (ambang kuorum, tier transport, minimum simpanan, dsb.) **di-set lewat konfigurasi rapat/pengaturan**, bukan ditulis di dalam service — sekaligus membuktikan service benar-benar membaca konfigurasi dan tidak ada angka bisnis yang hardcoded.

---

## 2. Setup

### 2.1 `phpunit.xml`

Konfigurasi database pengujian di `phpunit.xml`:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
</php>
```

Setiap test class yang menyentuh database memakai trait `RefreshDatabase`, sehingga tiap test berjalan di database kosong yang dimigrasi ulang. Untuk uji file (foto wajah, tanda tangan), gunakan disk privat palsu (`Storage::fake`) agar tidak ada file sungguhan yang tertulis.

### 2.2 Factory

Factory yang wajib tersedia (`database/factories`):

- **`AnggotaFactory`** — field wajib: `no_anggota` (unik), `nama`, `jenis_anggota` (default `biasa`), `status_anggota` (default aktif), `simpanan_wajib`, `kolektibilitas`, `status_kelancaran`. State pembantu: `biasa()`, `luarBiasa()`, `lancar()`, `tidakLancar()`, `nonaktif()`, dan nilai nominal/kolektibilitas bisa di-set per test.
- **`UserFactory`** — dengan state per role login saja: `admin()`, `helpdesk()`, `scanner()`, dan `keuangan()`. User factory **tidak** memiliki state/relasi ke `Anggota`; peserta bukan user dan tidak memiliki akun login.
- **`RapatFactory`** — state per konfigurasi: `modeSatuKali()`, `modeDuaKali()`, `kriteriaModeA(minSimpanan, maksKolektibilitas)`, `kriteriaModeB()`, konfigurasi kuorum (`persen`, `tambahan`, basis `total`/`terverifikasi`), plus konfigurasi transport yang bisa di-set per test.
- **`PesertaFactory`** (kandidat/undangan per rapat) — state verifikasi `pending()`, `verifiedLayak()`, `verifiedTidakLayak()`, dan `rejected()`; token tidak disimpan sebagai nilai acak factory, tetapi dihitung dengan helper `tokenUntuk(Anggota $anggota)` yang memakai HMAC nomor anggota + `APP_KEY` test yang sama setiap kali. **`PresensiFactory`** — dengan state `hadir()` (waktu datang + foto + tanda tangan terisi) dan helper entri offline ber-`client_event_id` unik.
- **`TransportFactory`** dan **`SesiKasFactory`** — untuk baris pembayaran scanner, kas awal per meja, sisa kas, dan rekonsiliasi.
- **`ShuFactory`** — per anggota per tahun buku dengan komponen `balas_jasa_saham` dan `balas_jasa_pinjaman`.

Contoh pemakaian:

```php
$anggota = Anggota::factory()->create([
    'simpanan_wajib' => 500_000,
    'kolektibilitas' => 2,
]);
$admin = User::factory()->admin()->create();
```

---

## 3. Skenario Uji per Modul

Penamaan test mengikuti pola: `it('menolak presensi datang duplikat')`, `it('menghitung target kuorum 61 dari populasi 120 anggota biasa')`.

### 3.1 Auth & Rate Limit

- Login dengan kredensial benar → masuk ke dashboard sesuai role.
- Login dengan kata sandi salah → ditolak, pesan kesalahan tampil dalam **Bahasa Indonesia**, tidak ada sesi yang terbentuk.
- **Rate limit:** setelah sejumlah percobaan gagal beruntun (batas mengikuti konfigurasi throttle login, contoh: 5 kali), percobaan berikutnya **diblokir sementara** — respons throttle (HTTP 429 / pesan "terlalu banyak percobaan" dalam Bahasa Indonesia) — **walaupun** kata sandi yang dimasukkan sudah benar.
- Setelah masa blokir lewat (atau hitungan percobaan direset oleh login yang sukses sebelum batas), login benar bisa masuk lagi.
- User yang belum login mengakses route terproteksi → diarahkan ke halaman login.
- **Hanya empat role login:** seeder dan factory hanya menghasilkan user ber-role `admin`, `helpdesk`, `scanner`, atau `keuangan`; tidak ada role, akun, atau sesi login peserta. Token QR peserta tidak dapat dipakai sebagai kredensial login dan endpoint scan tetap menolak request tanpa sesi petugas yang berizin. Halaman publik tidak menciptakan sesi peserta.

### 3.2 Anggota & Field Sensitif — REGRESI KEAMANAN UTAMA

Modul ini adalah regression test untuk celah aplikasi lama: field sensitif anggota pernah bisa ditentukan dari sisi client (properti publik Livewire), sehingga `status_anggota`, `simpanan_wajib`, dan `kolektibilitas` bisa dimanipulasi. Semua skenario di bawah adalah **kasus jahat** dan wajib selalu hijau:

- **Ubah sensitif tanpa izin ditolak:** request HTTP (form maupun payload yang meniru update properti Livewire) dari user role `helpdesk`, `scanner`, atau `keuangan` — atau user tanpa permission `anggota.kelola` — yang menyertakan nilai baru untuk `simpanan_wajib` / `kolektibilitas` / `status_anggota` / status kelancaran → **ditolak** (403/422) dan **nilai di database tidak berubah sama sekali**.
- **Manipulasi lewat field tak terduga:** request ubah data biasa yang diam-diam menyelipkan field sensitif tambahan → field sensitif **diabaikan/ditolak**, nilai di database tetap.
- **Jalur resmi tetap berfungsi:** hanya admin berizin yang dapat mengubah `simpanan_wajib` lewat form resmi → nilai berubah **dan** tercatat di log audit (lihat 3.9).
- **Helpdesk hanya boleh mengoreksi data non-sensitif:** pada panel verifikasi, helpdesk mengubah kontak/alamat/nama sesuai whitelist → perubahan berhasil dan tercatat. Payload yang sama bila menyelipkan `simpanan_wajib`, `kolektibilitas`, `status_anggota`, atau status kelancaran → seluruh field sensitif ditolak, nilai database tidak berubah; ini adalah **regresi P0 wajib** untuk role helpdesk.
- **Akses master dibatasi menurut role:** scanner tidak dapat menelusuri master anggota atau membuka data anggota di luar hasil minimal dalam alur scan/ketik manual aktif; keuangan tidak dapat mengubah field sensitif/kriteria walaupun dapat melihat data peserta seperlunya untuk transport dan SHU.

### 3.3 Rapat, Kriteria, Verifikasi Pra-Rapat & Undangan

Konfigurasi contoh untuk skenario Mode A: minimum simpanan wajib **Rp 500.000**, maksimum kolektibilitas **2**.

#### Kriteria kandidat

- **Mode A — batas bawah inklusif:** anggota dengan `simpanan_wajib` tepat **500.000** dan kolektibilitas 2 → masuk daftar kandidat.
- **Mode A — di bawah minimum:** anggota dengan `simpanan_wajib` **499.999** → **tidak masuk kandidat**, walau kolektibilitasnya 1.
- **Mode A — batas atas inklusif & di atasnya:** kolektibilitas tepat **2** → masuk kandidat; kolektibilitas **3** → tidak masuk kandidat, walau simpanan wajibnya besar.
- **Mode A — dua syarat bersifat DAN:** anggota yang lolos satu syarat tapi gagal syarat lainnya → tidak masuk kandidat.
- **Mode B:** dari data campuran, hanya anggota berstatus pinjaman **lancar** yang masuk kandidat; yang tidak lancar tidak masuk kandidat.
- **Tidak hardcoded:** dua rapat dengan ambang Mode A berbeda (dibangkitkan dari data anggota yang sama) menghasilkan daftar kandidat yang berbeda sesuai ambang masing-masing.
- **Anggota nonaktif tidak masuk kandidat:** anggota berstatus nonaktif tidak masuk daftar pada kedua mode, walau nilai kriterianya memenuhi.

#### Transisi verifikasi

- **Status awal `pending`:** setiap kandidat baru memiliki status `pending`, hasil kelayakan kosong, `verified_by` kosong, dan `verified_at` kosong.
- **`pending → verified` + Layak:** admin atau helpdesk memverifikasi kandidat → status menjadi `verified`, hasil menjadi `layak`, snapshot sama dengan nilai kriteria pada saat tombol verifikasi dijalankan, dan `verified_by`/`verified_at` terisi dari petugas serta waktu server.
- **`pending → verified` + Tidak Layak dengan catatan:** petugas memilih hasil Tidak Layak dan mengisi catatan → status menjadi `verified`, snapshot serta `verified_by`/`verified_at` tercatat.
- **Tidak Layak tanpa catatan ditolak:** keputusan hasil Tidak Layak dengan catatan kosong → 422/ditolak; status tetap `pending`, snapshot keputusan dan penanda verifikator tidak terisi.
- **`pending → rejected`:** data kandidat tidak dapat diverifikasi → status menjadi `rejected`, catatan alasan tersimpan, kandidat tidak menerima undangan formal, dan kandidat ditolak di pintu.
- **`rejected → koreksi → pending`:** petugas mengoreksi data yang menjadi alasan penolakan lalu mengembalikan kandidat → status menjadi `pending`; `verified_by`, `verified_at`, dan hasil kelayakan dikosongkan; riwayat penolakan/koreksi tetap ada di log audit.
- **`pending` hasil koreksi → verifikasi ulang:** kandidat yang sama diverifikasi lagi → status menjadi `verified`, snapshot diganti dengan nilai pada verifikasi ulang, dan `verified_by`/`verified_at` menunjuk keputusan terbaru.
- **Snapshot saat verifikasi tidak berubah sesudahnya:** setelah kandidat `verified`, ubah data anggota (mis. turunkan simpanan wajib ke bawah minimum) → snapshot pada baris peserta tetap nilai saat verifikasi dan keputusan tidak berubah diam-diam.
- **Koreksi oleh helpdesk terbatas:** helpdesk mengoreksi field non-sensitif pada kandidat `rejected` → koreksi berhasil dan status dapat dikembalikan ke `pending`; upaya helpdesk mengubah field sensitif pada transisi yang sama ditolak sesuai regresi P0 di 3.2.
- **Undangan untuk semua `verified`:** kandidat `verified` + Layak menerima undangan; kandidat `verified` + Tidak Layak juga menerima undangan (catatan petugas wajib) dan memenuhi syarat pintu; kandidat `pending`/`rejected` tidak menerima undangan.
- **Daftar terkunci tidak bisa dibangkitkan ulang:** kunci daftar undangan formal → jalankan bangkitan lagi → ditolak dan jumlah peserta tidak berubah.

#### Token QR deterministik

- **Token selalu sama:** hitung token untuk nomor anggota yang sama dua kali dengan `APP_KEY` test yang sama → kedua string **identik persis**.
- **Kirim ulang memakai token yang sama:** admin/helpdesk mengirim ulang undangan peserta → QR/token pada pengiriman kedua sama persis dengan yang pertama; tidak ada token baru yang diterbitkan.
- **Tidak ada fitur ganti token:** route/aksi untuk mengganti atau memutar token tidak tersedia bagi role mana pun; request yang meniru aksi tersebut ditolak/404.
- **Token anggota lain ditolak untuk konteks anggota ini:** token valid milik anggota A tidak dapat dipakai untuk mencatatkan atau membuka undangan peserta B; sistem hanya boleh menyelesaikan token ke anggota pemilik token.
- **Token peserta rapat lain ditolak di rapat aktif:** token valid milik anggota yang menjadi kandidat/peserta rapat lain, tetapi bukan kandidat pada rapat aktif → scan ditolak dan tidak ada presensi pada rapat aktif.
- **Perubahan `APP_KEY` di luar skenario test aplikasi:** test tidak mensimulasikan rotasi `APP_KEY` sebagai fitur; aturan operasionalnya adalah `APP_KEY` tidak diganti pada aplikasi yang sudah hidup.

### 3.4 Presensi, Token, Manual Scanner & Offline

Endpoint scan tidak bersifat publik: semua skenario scan dijalankan sebagai user petugas yang login dan berizin, terutama `scanner` pada rapat aktif.

- **QR valid mencatat kedatangan lengkap:** scanner memindai token HMAC milik kandidat `verified` pada rapat aktif → record presensi berisi `waktu_datang`, file foto wajah tersimpan di **disk privat**, dan tanda tangan tersimpan; status peserta menjadi hadir.
- **Syarat pintu hanya `verified`:** kandidat `verified` + Tidak Layak tetap dapat dicatat hadir oleh scanner; kandidat `pending` atau `rejected` dengan token/nomor yang benar tetap ditolak dan tidak ada presensi yang terbentuk.
- **Token rapat lain ditolak:** token valid milik anggota yang hanya menjadi kandidat/peserta rapat lain dipakai pada rapat aktif → ditolak, tidak ada presensi yang terbentuk pada rapat aktif, dan kegagalan diarahkan ke helpdesk.
- **Token tidak dikenal ditolak:** token acak/bukan hasil HMAC yang cocok → ditolak tanpa membocorkan data peserta dan tanpa record presensi.
- **Token anggota lain tidak dapat dipakai untuk peserta ini:** token anggota A dikirim bersama konteks/identitas peserta B → ditolak; token hanya menyelesaikan presensi untuk anggota pemilik token.
- **Tanda tangan wajib:** presensi datang tanpa tanda tangan digital → **ditolak / tidak dianggap lengkap** (tidak ada record presensi sah yang terbentuk).
- **Duplikat scan ditolak:** scan datang kedua untuk peserta & rapat yang sama → ditolak; tetap hanya ada satu record presensi. Scan pulang kedua pada mode 2x juga ditolak.
- **Bukan kandidat rapat tidak bisa presensi:** anggota yang tidak terdaftar sebagai kandidat/peserta rapat itu → presensi QR, ketik manual, maupun manual helpdesk ditolak.
- **Mode 1x tidak menyediakan pulang:** pada rapat mode 1x, aksi presensi pulang tidak tersedia dan request/scan pulang (termasuk yang dikirim manual) ditolak.
- **Mode 2x — pulang tanpa datang ditolak:** peserta yang belum presensi datang tidak bisa presensi pulang.
- **Mode 2x — alur lengkap:** scan datang lalu scan pulang → `waktu_pulang` terisi; pulang kedua ditolak.

#### Tab ketik manual scanner

Tab ketik manual adalah wewenang `scanner`, berbeda dari aksi presensi manual helpdesk.

- **Nomor anggota valid:** scanner mengetik nomor anggota kandidat `verified` pada rapat aktif → peserta yang tepat ditemukan dan alur foto + tanda tangan + presensi berjalan seperti scan QR.
- **Nomor anggota bukan kandidat/`pending`/`rejected`:** nomor anggota valid secara master tetapi bukan kandidat rapat aktif, atau statusnya belum `verified` → ditolak tanpa presensi.
- **Bukan penelusuran master:** tab ketik manual tidak mengembalikan daftar/saran anggota lain; nomor yang tidak cocok hanya menghasilkan pesan tidak ditemukan/tidak memenuhi syarat.
- **Scanner tidak bisa memakai aksi manual helpdesk:** scanner memanggil route/aksi `manual_helpdesk`, koreksi presensi, atau payload Livewire yang meniru aksi tersebut → 403/ditolak dan tidak ada presensi yang terbentuk.

#### Pengecualian helpdesk

- **Helpdesk manual hanya untuk peserta terundang + `verified`:** ketika QR gagal, helpdesk yang sudah memverifikasi peserta terundang mengirim presensi manual **dengan alasan** → presensi tercatat sebagai metode `manual_helpdesk`, petugas tercatat, dan log audit tercipta.
- **Helpdesk manual tanpa alasan ditolak:** payload yang sama tanpa alasan → 422/ditolak, tidak ada presensi, dan tidak ada perubahan status peserta.
- **Helpdesk manual untuk peserta tidak terundang/belum `verified` ditolak:** helpdesk mencoba presensi manual bagi anggota yang bukan peserta rapat atau masih `pending`/`rejected` → ditolak; kasus diteruskan ke admin/proses verifikasi ulang.
- **Helpdesk tidak dapat mengoreksi presensi selesai:** helpdesk mencoba mengubah waktu/status presensi yang sudah tercatat → 403 dan data tidak berubah.
- **Koreksi hanya admin:** koreksi waktu/status presensi oleh `helpdesk`, `scanner`, atau `keuangan` → 403 dan data tidak berubah. Oleh `admin` → berhasil **dan** tercatat di log audit dengan nilai sebelum/sesudah serta alasan koreksi (lihat 3.9).

#### Mode offline & sinkronisasi

- **Entri offline sah tersinkron satu kali:** scanner kehilangan koneksi, mencatat kedatangan kandidat `verified` dengan `client_event_id` unik dan waktu peristiwa perangkat, lalu koneksi pulih → sinkronisasi membuat tepat satu presensi dengan waktu peristiwa offline.
- **Retry sinkronisasi idempoten:** antrean yang sama dikirim ulang dengan `client_event_id` yang sama (mis. respons pertama hilang) → server mengembalikan hasil yang sudah ada dan jumlah presensi/pembayaran tetap satu.
- **Offline untuk anggota yang sudah hadir ditolak saat sinkron:** anggota tercatat hadir secara online dari stasiun lain; kemudian entri offline stasiun ini untuk anggota/rapat yang sama tiba → sinkronisasi menolak entri sebagai duplikat, melaporkannya sebagai konflik, tidak menimpa presensi asli, dan tidak membuat presensi atau serah terima kedua.
- **Offline untuk peserta belum `verified` ditolak:** status peserta berubah/diketahui bukan `verified` saat server memvalidasi sinkronisasi → entri ditolak dan masuk daftar konflik, bukan dipaksakan menjadi presensi.
- **Urutan datang-pulang offline:** antrean datang dan pulang mode 2x untuk peserta yang sama disinkronkan berurutan → satu presensi dengan kedua waktu; pulang offline tanpa datang yang diterima tetap ditolak.

#### Media presensi

- **Foto & TTE privat menurut role:** membuka URL foto/tanda tangan tanpa login → diarahkan ke login/403; `helpdesk` → **403 selalu**; `admin` dan `keuangan` yang berizin → dapat membuka arsip media; `scanner` dapat mengambil/mengunggah media dalam transaksi aktif tetapi membuka arsip di luar transaksi aktif → 403. File tidak bisa diakses lewat path publik (disk privat, hanya disajikan lewat route berotorisasi).

### 3.5 Kuorum

Aturan: **target = `floor(persen_kuorum × populasi) + tambahan_kuorum`**. Status **sah** bila hitungan hadir anggota `biasa` mencapai target. Persen, tambahan, dan basis populasi dibaca dari konfigurasi rapat.

- **Contoh wajib populasi 120:** basis menghasilkan populasi **120** anggota `biasa`, persen **50%**, tambahan **1** → target = `floor(0,50 × 120) + 1` = **61**.
- **Satu di bawah target belum sah:** dengan target 61, hitungan hadir `biasa` = 60 → status **belum sah**.
- **Tepat di target sah:** hitungan hadir `biasa` bertambah menjadi 61 → status berubah menjadi **sah** tepat pada orang ke-61.
- **Hadir `luar_biasa` tidak menambah hitungan:** pada data target 61 dan hadir `biasa` 60, tambahkan 5 presensi anggota `luar_biasa` → populasi tetap 120, hitungan hadir tetap **60**, dan status tetap **belum sah**. Presensi keenam puluh satu dari anggota `biasa` yang membuat status sah.
- **`luar_biasa` dikeluarkan dari populasi:** campuran kandidat berisi 120 `biasa` dan 30 `luar_biasa` → populasi kuorum tetap **120**, bukan 150, pada kedua basis.
- **Basis total:** populasi adalah seluruh populasi rapat dari anggota `biasa` sesuai konfigurasi, tanpa mempersempit ke yang sudah terverifikasi.
- **Basis terverifikasi:** populasi hanya menjumlah anggota `biasa` berstatus `verified`; kandidat `biasa` yang `pending`/`rejected` tidak masuk populasi basis ini. Uji dengan angka terpisah harus membuktikan target berubah mengikuti populasi terverifikasi.
- **Konfigurasi kustom terbaca:** rapat lain dengan persen 60%, tambahan 1, dan populasi 90 → target `floor(54) + 1` = **55**; 54 hadir belum sah dan 55 hadir sah. Ini membuktikan persen/tambahan tidak hardcoded.
- **Hanya presensi rapat itu yang dihitung:** presensi dari rapat lain tidak ikut menaikkan kuorum rapat ini.
- **Visibilitas kuorum per role:** admin dan keuangan dapat membuka daftar hadir & angka kuorum; scanner hanya menerima ringkasan stasiun/kuorum rapat aktif; helpdesk hanya melihat status peserta yang sedang diverifikasi, bukan laporan kuorum menyeluruh. Papan skor publik menampilkan target, populasi, hitungan, dan status yang sama dalam bentuk agregat tanpa data personal (lihat 3.11).

### 3.6 Uang Transport, Pembayaran Tunai & Rekonsiliasi Kas

#### Rumus nominal

Konfigurasi contoh wajib per rapat:

- Jam mulai rapat: **08:00**.
- Nominal dasar: **Rp100.000**.
- Grace period: **15 menit**.
- Tier potongan: keterlambatan **16–30 menit → potongan Rp25.000**.
- Batas hangus: **120 menit**.

Rumus: keterlambatan `<= 15` menit → nominal dasar; keterlambatan dalam rentang tier → nominal dasar − potongan tier; keterlambatan `> 120` menit → Rp0.

- **Contoh wajib 1 — dalam grace period:** scan datang **08:10** (telat 10 menit) → **Rp100.000**.
- **Contoh wajib 2 — masuk tier potongan:** scan datang **08:20** (telat 20 menit) → Rp100.000 − Rp25.000 = **Rp75.000**.
- **Contoh wajib 3 — lewat batas hangus:** scan datang **10:30** (telat 150 menit) → **Rp0**.
- **Batas grace inklusif:** scan 08:15 → **Rp100.000**; scan 08:16 dengan tier contoh → **Rp75.000**.
- **Setelah batas hangus:** scan 121 menit setelah mulai → **Rp0**. Konfigurasi lain dengan dasar/grace/tier/batas berbeda menghasilkan nominal sesuai konfigurasinya, membuktikan parameter tidak hardcoded.
- **Berdasarkan waktu datang, bukan waktu bayar/pulang:** peserta mode 2x yang datang 08:20 tetapi pulang dan dibayar 11:00 tetap berhak **Rp75.000**.
- **Hanya peserta hadir yang berhak:** peserta terundang yang tidak hadir tidak memiliki record transport.

#### Serah terima tunai oleh scanner

- **Mode 1x dibayar saat datang:** setelah presensi datang lengkap, scanner menyerahkan tunai di titik scan dan sistem membuat tepat satu baris transport `sudah_dibayar` dengan nominal hasil hitungan, scanner pembayar, meja/sesi kas, dan waktu.
- **Mode 2x dibayar saat pulang + resi:** setelah presensi pulang selesai, scanner menyerahkan tunai dan sistem menerbitkan tepat satu resi berkode unik; pembayaran tidak dibuat saat kedatangan.
- **Pembayaran ganda dicegah:** scanner mencoba membayar peserta yang sama lagi → ditolak; status, nominal, pembayar, sesi kas, kode resi, dan waktu pembayaran pertama tidak berubah.
- **Nominal Rp0 tidak menghasilkan serah terima tunai:** peserta yang hangus tercatat berhak Rp0/`tidak_ada_yang_dibayar`; tidak ada kas keluar dan pembayaran tidak dapat diulang sebagai pembayaran Rp0.
- **Scanner tidak dapat mengubah nominal:** payload scan yang menyelipkan nominal berbeda dari hasil `TransportService` diabaikan/ditolak; nominal tersimpan tetap hasil rumus konfigurasi.
- **Pembayaran dari presensi offline hanya sekali:** entri offline yang diterima membuat pembayaran sesuai titik mode presensi; retry sinkronisasi atau entri duplikat yang ditolak tidak membuat baris pembayaran kedua.

#### Kas awal, sisa kas & rekonsiliasi

- **Kas awal per meja:** keuangan membuat sesi kas meja A dengan `kas_awal` Rp500.000 dan meja B dengan kas awal berbeda → pembayaran meja A hanya mengurangi sesi A; sesi B tidak berubah.
- **Kas keluar sama dengan jumlah baris terbayar:** pada satu sesi, buat baris terbayar Rp100.000 + Rp75.000 + Rp50.000 → `kas_keluar` sistem tepat **Rp225.000**, dan `kas_seharusnya` = Rp500.000 − Rp225.000 = **Rp275.000**. Baris belum dibayar dan baris Rp0 tidak masuk kas keluar.
- **Rekonsiliasi tanpa selisih:** sisa kas fisik dicatat Rp275.000 → selisih **Rp0**; rekonsiliasi dapat difinalkan tanpa catatan selisih.
- **Selisih bukan nol tanpa catatan ditolak:** sisa kas fisik dicatat Rp270.000 pada contoh di atas → selisih **−Rp5.000**; finalisasi tanpa catatan → ditolak dan status sesi tetap belum terekonsiliasi final.
- **Selisih bukan nol dengan catatan dapat difinalkan:** data yang sama ditambah catatan penjelasan keuangan → finalisasi berhasil; kas awal, total baris terbayar, kas keluar, sisa fisik, selisih, catatan, petugas, dan waktu tersimpan untuk laporan/audit.
- **Peran pembayaran dan kas tidak tertukar:** keuangan mengelola konfigurasi, sesi kas, dan rekonsiliasi; admin/helpdesk tidak dapat menyerahkan tunai dari alur scan atau memfinalkan rekonsiliasi; scanner tidak dapat mengubah konfigurasi, kas awal, atau hasil rekonsiliasi.
- **Keuangan tidak dapat mengubah presensi atau kriteria:** dari alur transport/kas, keuangan mencoba mengubah waktu/status presensi, field sensitif kriteria, atau kriteria rapat → 403 dan data tidak berubah.

### 3.7 SHU & Cek Publik

Tidak ada akun peserta untuk skenario ini. Pengelolaan data dilakukan keuangan; pengecekan publik dilakukan melalui form nomor anggota.

- **Keuangan mengelola komponen SHU:** `keuangan` dapat menambah data secara manual dan mengimpor spreadsheet dengan field `balas_jasa_saham` dan `balas_jasa_pinjaman`; data tersimpan pada anggota dan tahun buku yang benar.
- **Total dihitung server-side:** contoh balas jasa saham Rp300.000 + balas jasa pinjaman Rp125.000 → total SHU **Rp425.000**. Payload yang menyelipkan total berbeda tidak boleh menimpa hasil hitungan server.
- **Koreksi SHU oleh keuangan tercatat audit:** keuangan mengoreksi salah satu komponen → total dihitung ulang dan log audit memuat aktor, komponen sebelum/sesudah, dan total sebelum/sesudah.
- **Tahun buku terpisah:** SHU anggota yang sama untuk tahun buku 2025 dan 2026 tampil sebagai dua data terpisah dengan komponen dan total masing-masing; filter tahun menampilkan hanya tahun yang dipilih.
- **Cek SHU publik dengan nomor tepat:** tanpa login, kirim nomor anggota yang ada → respons menampilkan balas jasa saham, balas jasa pinjaman, total, dan tahun buku yang sesuai. Nomor yang tidak ditemukan menampilkan pesan tidak ditemukan yang generik dan tidak membocorkan data anggota lain.
- **Lookup publik tercatat audit:** setiap percobaan cek SHU publik — ditemukan maupun tidak ditemukan — membuat catatan audit lookup berisi waktu, sumber/IP, nomor yang dicari sesuai kebijakan redaksi log, dan hasil ditemukan/tidak.
- **Throttle anti-enumerasi:** percobaan lookup beruntun dari sumber yang sama mencapai batas throttle konfigurasi test → percobaan berikutnya menerima HTTP 429/pesan throttle Bahasa Indonesia, walaupun nomor anggota yang dikirim valid. Setelah jendela throttle berakhir, lookup valid dapat dilakukan lagi.
- **Helpdesk dan scanner tidak mengelola/membuka konsol SHU:** helpdesk mencoba membuka lookup petugas atau tambah/ubah/impor/koreksi SHU → 403 dan data tidak berubah; scanner membuka halaman/endpoint pengelolaan SHU → 403. Peserta diarahkan ke form publik.
- **QR bukan akses SHU:** token QR peserta dipakai pada endpoint pengelolaan SHU atau sebagai kredensial login → ditolak; form publik hanya memakai nomor anggota sesuai alurnya.

### 3.8 Doorprize

- **Undian hanya dari peserta hadir:** siapkan rapat dengan 3 peserta — 2 hadir, 1 tidak hadir. Undian dijalankan berkali-kali pada data uji (atau periksa kumpulan undiannya langsung) → peserta tidak hadir **tidak pernah** terpilih.
- **Pemenang tidak bisa menang dua kali:** 1 peserta hadir dan 2 unit hadiah → undian pertama menghasilkan pemenang itu; undian kedua **ditolak** karena kumpulan undian kosong (satu peserta maksimal menang sekali per rapat).
- **Hadiah habis menghentikan undian:** total unit hadiah 1 dan sudah ada 1 pemenang tercatat → menjalankan undian lagi **ditolak** dengan pesan hadiah sudah habis.
- **Pemenang tercatat:** setiap hasil undian tersimpan (rapat, hadiah, peserta pemenang, waktu) dan tampil di daftar pemenang.
- **Belum ada yang hadir:** undian pada rapat tanpa peserta hadir → ditolak dengan pesan yang jelas, tidak ada pemenang yang tercatat.
- **Hanya admin yang mengelola dan menjalankan doorprize:** percobaan membuat/mengubah hadiah atau menjalankan undian oleh `helpdesk`, `scanner`, atau `keuangan` → 403 dan tidak ada pemenang baru. Helpdesk dan keuangan hanya dapat melihat daftar pemenang seperlunya; scanner tidak dapat membuka data doorprize.

### 3.9 Log Audit

- **Perubahan field sensitif tercatat:** admin mengubah `simpanan_wajib` anggota dari Rp 400.000 ke Rp 550.000 → log audit berisi aktor (admin tersebut), waktu, entitas anggota yang diubah, nilai **sebelum** (400.000) dan **sesudah** (550.000).
- **Koreksi presensi tercatat:** admin mengoreksi waktu datang peserta → log audit berisi nilai sebelum/sesudah **dan alasan** koreksi.
- **Presensi manual helpdesk tercatat:** helpdesk mencatat presensi manual peserta terundang + `verified` karena QR gagal → log audit memuat peserta, rapat, petugas helpdesk, metode manual, dan alasan wajibnya.
- **Verifikasi pra-rapat tercatat:** transisi `pending → verified`, `pending → rejected`, koreksi, dan `rejected → pending` masing-masing menghasilkan log berisi aktor, status/hasil sebelum-sesudah, snapshot keputusan, dan waktu; keputusan `verified` memuat `verified_by`/`verified_at` yang konsisten dengan log.
- **Sinkronisasi offline tercatat:** entri offline yang diterima dan entri duplikat yang ditolak sama-sama menghasilkan jejak audit/sinkronisasi berisi `client_event_id`, stasiun, peserta, dan hasil; jejak ini tidak menciptakan presensi kedua.
- **Aksi penting lain tercatat:** pembangkitan kandidat, penerbitan/penguncian daftar undangan, pengiriman ulang undangan dengan token yang sama, konfigurasi transport, pembayaran tunai scanner, pembukaan sesi kas, finalisasi rekonsiliasi, lookup SHU publik, serta koreksi SHU oleh keuangan masing-masing menghasilkan catatan log.
- **Log audit hanya dibuka admin:** halaman/endpoint log audit dapat dibuka `admin`; percobaan akses oleh `helpdesk`, `scanner`, atau `keuangan` → 403.
- **Log tidak bisa diubah dari aplikasi:** tidak ada aksi ubah/hapus log audit yang bisa dijalankan role mana pun (route/fitur tersebut memang tidak disediakan).
- **Percobaan jahat yang ditolak tidak mengubah data:** request manipulasi field sensitif yang ditolak di 3.2 tidak menghasilkan perubahan nilai — dan (bila pencatatan percobaan diaktifkan) tercatat sebagai percobaan yang ditolak, bukan sebagai perubahan.

### 3.10 Otorisasi (semua role)

Role login resmi proyek hanya: `admin`, `helpdesk`, `scanner`, dan `keuangan`. Peserta/anggota bukan role login dan tidak memiliki relasi user.

Untuk setiap role, coba akses route maupun aksi Livewire di luar haknya → **HTTP 403/ditolak di server**, bukan hanya menu yang disembunyikan. Contoh yang wajib diuji:

- **`admin`:** dapat mengelola master anggota dan field sensitif secara server-side, setup rapat & kriteria, membangkitkan kandidat, ikut memverifikasi pra-rapat, menerbitkan/mengunci undangan formal, mengoreksi presensi, mengelola dan menjalankan doorprize, mengelola pengaturan/user/role, membuka semua laporan, membuka arsip foto/TTE, dan membuka log audit. Admin tidak menyerahkan tunai dari alur scan dan tidak memfinalkan rekonsiliasi kas keuangan.
- **`helpdesk`:** dapat mencari peserta, melihat status verifikasi/undangan/hadir/transport, ikut panel verifikasi, mengoreksi hanya data non-sensitif, menampilkan/mengirim ulang undangan dengan token yang sama setelah verifikasi identitas, dan melakukan presensi manual hanya untuk peserta terundang berstatus `verified` saat QR gagal dengan alasan wajib. Percobaan mengubah field sensitif, konfigurasi, pembayaran/rekonsiliasi, data SHU, koreksi presensi selesai, atau membuka foto/TTE → 403 dan data tidak berubah. Peserta `pending`/`rejected` diteruskan ke proses koreksi dan verifikasi ulang.
- **`scanner`:** hanya dapat membuka stasiun rapat aktif, scan QR datang/pulang, memakai tab ketik manual nomor anggota, bekerja offline dan menyinkronkan antrean, mengambil foto/tanda tangan dalam alur aktif, melihat hasil minimal dan ringkasan stasiun/kuorum, serta menyerahkan tunai hasil hitung sistem pada titik scan (1x saat datang; 2x saat pulang + resi). Percobaan menelusuri master anggota, memakai aksi manual helpdesk, koreksi, mengubah nominal/konfigurasi, membuka arsip media, mengelola SHU, doorprize, laporan, audit, atau rekonsiliasi → 403.
- **`keuangan`:** dapat melihat aturan/tier transport yang diatur admin (tidak mengedit tier), mencatat kas awal per meja, memantau sisa kas, merekonsiliasi, mengelola SHU termasuk balas jasa saham & pinjaman (input/impor/koreksi teraudit), melihat daftar hadir & kuorum, membuka arsip foto/TTE, dan membuka laporan transport/SHU. Percobaan mengubah field sensitif kriteria, kriteria rapat, presensi, doorprize, atau pengaturan/user → 403 dan data tidak berubah. Keuangan tidak menyerahkan tunai per peserta dari konsol keuangan.
- **Peserta/QR:** tidak ada kredensial peserta yang dapat login; token hanya diproses oleh endpoint scan ketika petugas yang berwenang sudah login. Permukaan publik peserta hanya halaman pada 3.11.
- user yang belum login mengakses route terproteksi → diarahkan ke login.

### 3.11 Halaman Publik

Halaman publik tidak memerlukan login, tetapi tidak boleh membuka konsol petugas atau data di luar fungsi persisnya.

- **Papan skor agregat tanpa data personal:** buka papan skor rapat aktif tanpa login → respons menampilkan populasi, target, hitungan hadir kuorum, persentase, dan status sah/belum sah; respons **tidak memuat** nama peserta, nomor anggota, token, foto, tanda tangan, nominal personal, atau daftar hadir.
- **Undangan publik hanya cocok dengan tokennya:** token valid peserta `verified` + Layak → halaman menampilkan undangan rapat pemilik token. Token acak, token anggota lain yang dimanipulasi konteksnya, atau token yang tidak cocok → 404/pesan generik dan tidak membocorkan data pemilik token lain.
- **Undangan publik tidak menjalankan presensi:** halaman token tidak memiliki aksi yang dapat membuat presensi; submit presensi tanpa sesi petugas → ditolak dan tidak ada record presensi.
- **Cek SHU publik:** form nomor anggota bekerja sesuai 3.7 — hasil komponen/total tampil untuk nomor tepat, percobaan beruntun terkena throttle, dan lookup tercatat audit.
- **Verifikasi resi valid:** tanpa login, kirim kode resi yang diterbitkan pada pembayaran mode 2x → respons menampilkan ringkasan **teks** pembayaran (status, nominal, waktu, rapat, dan meja/stasiun) yang cocok dengan baris transport.
- **Kode resi tidak valid ditolak:** kode acak/diubah → pesan tidak ditemukan/generik dan tidak menampilkan data pembayaran peserta lain.
- **Media tetap tertutup dari resi publik:** halaman verifikasi resi tidak memuat URL/token akses foto atau TTE; membuka endpoint foto/TTE dari konteks publik/tanpa login role berhak → 403/diarahkan login, dan sebagai `helpdesk` tetap 403. Hanya `admin` dan `keuangan` yang dapat membuka media melalui route petugas berotorisasi.

---

## 4. Cara Menjalankan

```bash
# Seluruh test
php artisan test
# atau
./vendor/bin/pest

# Satu file test
./vendor/bin/pest tests/Feature/PresensiTest.php

# Filter berdasarkan nama test
./vendor/bin/pest --filter="menolak presensi datang duplikat"

# Dengan laporan coverage (bila ekstensi coverage tersedia)
./vendor/bin/pest --coverage
```

Catatan: `--coverage` membutuhkan driver coverage (Xdebug atau PCOV) terinstal di PHP. Bila belum tersedia, jalankan tanpa flag tersebut — test tetap berjalan normal.

---

## 5. Kriteria Selesai

1. **Semua test hijau sebelum commit.** Tidak ada commit dengan test merah.
2. **Test baru untuk fitur baru adalah DEFINISI SELESAI.** Sebuah fitur dianggap selesai hanya jika: implementasi jalan, test-nya ditulis (kasus bahagia + batas + jahat), dan seluruh suite hijau.
3. **Fase selesai = suite penuh hijau.** Sebelum satu fase dinyatakan selesai (lihat `IMPLEMENTATION_PLAN.md`), jalankan seluruh suite Pest sekali jalan — termasuk semua skenario keamanan di 3.2, 3.4, dan 3.9 — dan pastikan 0 kegagalan.
4. Setiap perbaikan bug menyertakan regression test (lihat 1.5).
5. Hasil test dilaporkan ke Chandra apa adanya: jumlah test lulus/gagal, bukan sekadar klaim "sudah hijau".
