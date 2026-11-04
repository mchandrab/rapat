# DATA DICTIONARY — Istilah dan Definisi

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

| Istilah | Definisi/format | Sumber/acuan |
|---|---|---|
| NBA | Nomor Buku Anggota 15 digit; tersimpan `020006000010339`, tampil `020006.000.010.339` | Import/admin |
| `tgl_ja` | Tanggal resmi mulai anggota | Import/admin |
| `tgl_keluar` | Tanggal keluar koperasi, nullable | Admin |
| `status_anggota` | Aktif/Nonaktif; Nonaktif memblokir scan tetapi **bukan** kriteria Layak | Admin/master terkini |
| `simpanan_wajib` | Saldo simpanan wajib per 31 Desember tahun buku RAT aktif, bukan saldo real-time | Import hasil sistem client |
| minimum proporsional | Bulan kewajiban × tarif bulanan, batas contoh maksimum 12 bulan | Konfigurasi rapat + tgl_ja |
| Layak/Tidak Layak | Penilaian **otomatis** Mode A/B | Kriteria per rapat |
| Pending/Verified/Rejected | **Keputusan petugas** per rapat, terpisah dari Layak | Admin/Helpdesk |
| QR | Token acak unik untuk anggota+rapat yang lahir setelah Verified | Server |
| Jam mulai presensi | Awal layanan pemindai diperbolehkan | Admin/Rapat |
| Jam mulai RAT | Acuan toleransi dan keterlambatan transport | Admin/Rapat |
| kas_awal | Alokasi uang sekali kepada user Scanner pada RAT | Keuangan |
| saldo tersedia | Kas awal - terbayar; tanpa cadangan di kedua mode, kas berkurang saat pembayaran (D-034) | KasScannerService |
| nominal transport | Besaran yang dihitung sistem berdasarkan waktu datang | Admin config + datang_at |
| selisih kas | Sisa fisik − sisa seharusnya | Keuangan |
| SHU | Data hasil usaha per anggota dan tahun buku, dikelola Admin | Admin |

## Ketentuan data
Uang `DECIMAL(15,2)`; tanggal ISO YYYY-MM-DD; waktu event lengkap zona waktu; status enumerasi dibakukan. Contoh nilai dalam dokumen bukan aturan default tanpa pengaturan.


### Kamus `log_cek_shu`
`id`: PK; `nba_dicari`: NBA input hasil normalisasi, hanya Admin; `tahun_buku`: tahun yang diminta; `ip_address`: sumber permintaan; `hasil`: hasil proses pencarian/penolakan; `created_at`: timestamp pencarian yang menjadi acuan umur data. **Retensi 14 hari** sejak `created_at`. Tidak mengandung nominal SHU. Jangan samakan dengan `shu` (saldo/komponen SHU permanen sesuai kebijakan) atau `log_audit` perubahan oleh Admin.


## `pengaturan_integrasi` (v2.2)
`layanan`: enum aplikasi `captcha|fonnte`; `provider`: nama vendor; `aktif`: status operator; `site_key`: key publik CAPTCHA; `secret_ciphertext`: credential terenkripsi yang **tak pernah dipulangkan ke UI**; `credential_version`: nomor rotasi; `last_test_at`, `last_test_status`: catatan uji koneksi tanpa detail rahasia; `updated_by`: Admin terakhir mengubah. Status **terkonfigurasi** berarti key lengkap; berbeda dari status **teruji sukses**.
