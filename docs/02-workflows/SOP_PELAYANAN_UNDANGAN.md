# SOP Pelayanan, Verifikasi & Undangan

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Pelaku & jadwal
Admin/Helpdesk; pelayanan biasanya H-30 s.d. H-7. Anggota datang meminta undangan; tidak ada login anggota.

## Langkah
1. Cocokkan identitas, cari NBA dalam format dengan/tanpa titik.
2. Pilih rapat tahap Verifikasi; tampilkan saldo simpanan wajib akhir tahun buku dan hasil hitungan proporsional, kolektibilitas untuk Mode A atau kelancaran untuk Mode B.
3. Sistem menampilkan Layak/Tidak Layak. Petugas menentukan Verified/Rejected (Pending jika belum diputus). Jika Tidak Layak tetapi diizinkan menerima undangan, wajib tulis alasan yang bisa diaudit.
4. Sistem menyimpan petugas, waktu dan snapshot kriteria; hanya Verified mendapatkan QR rapat.
5. Tanyakan cara menerima undangan: WhatsApp atau cetak. Catat `rapat`, `peserta`, `petugas`, kanal, waktu, hasil, tujuan bila relevan. Kirim ulang/cetak ulang = log baru, QR rapat tetap sama.
6. Informasikan tanggal, lokasi, jam, dan kewajiban menunjukkan QR.

## Masalah
NBA tidak cocok/duplikat → eskalasi Admin; data sensitif salah → Admin membetulkan dengan audit; penolakan → alasan dicatat; hasil WA gagal → catat percobaan gagal dan berikan opsi cetak.

## Bukti
Laporan undangan bisa difilter per petugas, tanggal, status, kanal, rapat, dan anggota. Jangan mengklaim WA diterima tanpa bukti status delivery.


**Batas tanggung jawab:** log kirim/cetak undangan bukan log pencarian SHU publik dan tidak otomatis dihapus 14 hari.

> **Integrasi v2.2:** konfigurasi CAPTCHA dan Fonnte WhatsApp hanya melalui **Pengaturan → Integrasi** oleh Admin; lihat `docs/03-technical/INTEGRATION_SETTINGS.md`. Tidak perlu menyimpan credential layanan di `.env`; secret wajib terenkripsi.
