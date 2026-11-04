# PLAYBOOK — Penanganan Masalah eRAT

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

| Insiden | Respons petugas | Pihak keputusan | Catatan wajib |
|---|---|---|---|
| QR tidak terbaca | Cari NBA via Helpdesk; periksa status Verified/rapat; jangan bypass otomatis | Helpdesk/Admin | NBA, waktu, alasan |
| NBA salah/ganda | Jangan menggabungkan otomatis; periksa sumber impor | Admin | sebelum/sesudah |
| Anggota Nonaktif sudah punya undangan | Scan ditolak; verifikasi data keluar | Admin | status dan waktu |
| Scanner kas menipis | Tampilkan peringatan, arahkan antrean sesuai kapasitas | Scanner/Keuangan | saldo logis |
| Scanner kas tidak mencukupi | Tolak presensi yang memerlukan bayar; arahkan Scanner lain | Scanner | alasan penolakan |
| Duplikat scan/bayar | Tolak transaksi kedua, audit usaha kedua | Admin | event-id dan peserta |
| Koneksi putus | Tidak ada pembayaran offline sukses; pemindaian dihentikan sampai koneksi pulih (D-032) | Admin | insiden jaringan |
| Sisa fisik ≠ sisa seharusnya | Periksa daftar bayar, hitung ulang, catat selisih | Keuangan/Admin | nominal & alasan |
| Salah input SHU | Koreksi oleh Admin, log audit | Admin | nilai sebelum/sesudah |
| WA gagal | Tawarkan cetak, log gagal | Helpdesk | petugas, waktu dan error |
| Jam sistem beda | Hentikan pencatatan berisiko, cek time sync, eskalasi | Admin/IT | timestamp |

Jangan menghapus audit atau membuat transaksi kas manual tersembunyi demi memperbaiki angka.


### Insiden pencarian SHU
Jika dideteksi scraping NBA, batasi akses dan periksa log pencarian yang tersedia maksimal 14 hari. Akses audit hanya Admin. Karena log lama dibersihkan otomatis, insiden yang baru dilaporkan setelah 14 hari mungkin tidak dapat direkonstruksi dari log ini. Pertahankan bukti yang sah selama investigasi sesuai prosedur dan kebijakan privasi yang berlaku; ini tidak mengubah retensi rutin.


## Insiden Integrasi API (v2.2)
CAPTCHA tidak tersedia/tidak valid → hentikan lookup Cek SHU, tampilkan pesan umum, log insiden tanpa token, periksa konfigurasi Admin. Fonnte gagal → tampilkan gagal pada petugas dan catat upaya, tetapi jangan membatalkan Verified atau mengklaim WA telah delivered. Dugaan token bocor → Admin rotasi di **Pengaturan → Integrasi**, batalkan token lama di vendor, audit akses, uji ulang, jangan menuliskan token lama/baru pada tiket.
