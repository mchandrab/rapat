# TRACEABILITY MATRIX — Jejak Fitur

> **Status:** Matriks aktif, diperbarui 7 November 2026.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

| Requirement | Aturan | Alur | Modul teknis | Test yang wajib | Status |
|---|---|---|---|---|---|
| FR-001 | BR-001, BR-012 | Impor master | Anggota, ImportAnggota | T-NBA-01..04 | Implementasi; test lulus (29/10/2026) |
| FR-002 | BR-002, D-045, D-051, D-052 | Setup rapat | Rapat, RapatTransportTier | T-RAPAT-01..06 | Tanggal Acuan Saldo menjadi sumber periode tunggal; Informasi Umum memuat tiga jam, nama lokasi, dan alamat lengkap; tahun buku SHU tetap |
| FR-003 | BR-003 | Verifikasi | PenilaianKelayakan | T-KEL-01..05 | Implementasi; test lulus (30/10/2026) |
| FR-004 | BR-004 | Verifikasi | Peserta, VerifikasiPeserta | T-VER-01..04 | Implementasi; test lulus (30/10/2026) |
| FR-005 | BR-005 | WA/cetak | LogUndangan, UndanganPeserta, UndanganController, Integrasi | T-UND-01..04, T-QR-01..05, T-INT-01..07 | Implementasi; test lulus (03/11/2026). Undangan cetak ber-QR dan kirim WhatsApp lewat Fonnte aktif; status hanya `diterima_api`/`gagal` sesuai bukti vendor |
| FR-006 | BR-006..08 | Scan | PresensiScan, PenghitungTransport, ResiController, BuktiPresensiController | T-SCAN-01..10, T-RESI-01..05, T-BUKTI-01..06, T-PULANG-01..09, T-OFF-01..03 | Implementasi sebagian; test lulus. Scan token + transport + kas cukup + foto/TTE + resi + akses bukti aktif; kamera QR aktif, uji perangkat nyata menunggu HTTPS/localhost; koneksi putus menghentikan pemindaian tanpa antrean (D-032); Helpdesk tidak mempunyai akses presensi (D-036); Mode Presensi 2x selesai (D-034) |
| FR-007 | BR-010 | Kuorum | `Kuorum`, komponen `kuorum-papan` dan `kuorum-ringkas` | T-KUO-01..10 | Implementasi; test lulus (04/11/2026). `/kuorum` untuk Admin/Keuangan, ringkasan Dashboard Scanner, dan `/papan-kuorum` tanpa login; seluruhnya memakai sumber hitung yang sama dan polling 10 detik, halaman publik tanpa data pribadi |
| FR-008 | BR-009 | Kas & rekonsiliasi | AlokasiKas, TutupKas, KasScanner, PresensiScan | T-KAS-01..12, T-RESI-02 | Implementasi; test lulus (03/11/2026). Alokasi kas + konfirmasi terima (D-026) + pembayaran transport + resi + peringatan kas menipis (D-025) + tutup kas & rekonsiliasi beralasan (D-031) aktif |
| FR-009 | BR-011 | SHU | `ShuController`, `ImportShu`, `Shu`, `ShuPublik`, `CekShuController` | T-SHU-01..15, T-SHU-LOG-01..06 | Implementasi; test lulus. Input manual dan koreksi beralasan dengan audit sebelum/sesudah, impor XLSX per tahun buku, tombol buka/tutup cek publik, serta `/cek-shu` dengan NBA + Turnstile + rate limit + log 14 hari |
| FR-010 | BR-011, D-044, D-046 | Hadiah & undian | `DoorprizeHadiah`, `Doorprize`, `/doorprize`, `/doorprize/undian`, komponen hadiah/undian | T-DP-01..15 | Implementasi selesai 5/11/2026; master dan layar undian dipisah, migration menjaga hasil lama melalui hadiah arsip, CRUD/audit aktif, gerbang status dan pilihan hadiah diterapkan server-side, row lock mencegah stok terlampaui, snapshot tampil pada hasil/laporan |
| FR-011 | BR-005,009,012 | Laporan | `Laporan`, `LaporanController`, `LaporanXlsx` | T-LAP-01..07 | Implementasi; test lulus (03/11/2026). Rekap per rapat sesuai role + ekspor XLSX dengan angka yang sama |
| FR-012 | BR-012, D-036, D-039, D-050 | Semua | Middleware role, FormRequest whitelist, ringkasan dashboard, Policy & Audit | T-SEC-01..05, T-OFF-01..03, T-ROLE-01..02 | Implementasi sebagian; test lulus. RBAC middleware, file foto/TTE privat, audit append-only, anti-enumerasi token, throttle login, larangan presensi Helpdesk, koreksi lima kolom pelayanan anggota oleh Helpdesk, dan ringkasan rapat baca-saja sesuai tugas aktif; offline aman = pemindaian berhenti total tanpa antrean (D-032) |
| FR-014 | D-035, D-041, D-042, BR-012 | Pengaturan pengguna | `PenggunaController`, `EnsureUserIsActive`, menu Pengguna, `users.username`, `users.role`, `users.active`, FK `kas_scanner.scanner_user_id` RESTRICT | T-USER-01..12, T-KAS-13 | Implementasi; test lulus. CRUD akun dengan username unik, status aktif/nonaktif, penghentian akses, login username tanpa email, audit tanpa sandi, proteksi akun Admin sendiri, serta larangan hapus akun yang memiliki riwayat operasional |

Update Status menjadi Implementasi / Test lulus / UAT lulus berdasarkan bukti, bukan perkiraan.


| BR-SHU-LOG-01 / D-018 | Riwayat cek SHU 14 hari | `log_cek_shu`, `CekShuController`, `PurgeLogCekShu`, Scheduler, Admin policy | T-SHU-LOG-01 s.d. T-SHU-LOG-06 | `SECURITY_PRIVACY.md`, `DEPLOYMENT_RUNBOOK.md`; implementasi lulus test 03/11/2026 |


## Penelusuran Integrasi v2.2
| ID | Kebutuhan | Implementasi | Uji |
|---|---|---|---|
| BR-INT-01 | Admin mengatur CAPTCHA/Fonnte dari UI, tidak `.env` | `pengaturan_integrasi`, controller/service Tabler | TEST-INT-01, 02 |
| SEC-INT-01 | Credential terenkripsi dan tak dapat dibaca kembali | Laravel Crypt, API hanya `configured` | TEST-INT-03 |
| SEC-INT-02 | Cek SHU fail-closed | `CaptchaVerifier` | TEST-INT-04 |
| OPS-INT-01 | Uji Fonnte eksplisit | `FonnteGateway` & dialog uji | TEST-INT-05 |
