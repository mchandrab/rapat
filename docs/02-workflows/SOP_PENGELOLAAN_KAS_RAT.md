# SOP Pengelolaan Kas Transport per Scanner

> **Status:** Rancangan terintegrasi v2.1 (29 Oktober 2026). Aturan yang diputuskan pengguna dicatat sebagai **FINAL**. Butir yang belum diputuskan ditandai **USULAN / PERLU KEPUTUSAN**. Seluruh dokumen original tersedia pada `docs/archive/original_2026-10-23/`.

**Lintasan acuan:** `DECISION_LOG.md` → `BUSINESS_RULES.md` → `PRD.md` → alur & izin → desain teknis → pengujian. Jika ada pertentangan, jangan menebak: catat konflik dan minta keputusan sebelum coding.

## Tujuan
Menjamin uang transport dapat dipertanggungjawabkan **per akun user Scanner**, bukan berdasarkan meja. Tidak ada `kas_meja`, tambahan kas, top-up atau transfer antarscanner.

## Alur sebelum RAT
1. Keuangan memilih RAT dan akun dengan role Scanner yang bertugas.
2. Keuangan menginput `kas_awal`, memeriksa nominal, dan menyerahkan uang fisik sekali kepada Scanner.
3. Sistem menyimpan `scanner_user_id`, `rapat_id`, `diserahkan_oleh`, waktu, nominal, serta status. Scanner wajib menekan **Saya sudah menerima kas** sekali per alokasi sebelum boleh memindai (D-026).
4. Kunci agar satu Scanner hanya punya satu `kas_scanner` per RAT; cegah perubahan `kas_awal` saat sudah dipakai membayar.

## Selama RAT
- Per pembayar terverifikasi, catat `dibayar_oleh` scanner, `kas_scanner_id`, waktu, nominal, resi, dan presensi; lindungi dari pembayaran berulang.
- Saldo logis = `kas_awal - jumlah pembayaran sukses`. Mode Presensi 2x tidak memesan dana saat datang; kas berkurang saat pembayaran pulang (D-034).
- Peringatan saldo menipis muncul bila sisa < `batas_peringatan_kas` (bawaan 5 × transport dasar, diatur Admin per rapat); hanya informasi (D-025).
- Jika nominal lebih besar daripada saldo tersedia: **tolak presensi yang memerlukan pembayaran**; arahkan ke scanner lain. Tidak ada piutang/pembayaran susulan dari scan sukses.
- Uang tidak ditambahkan; tidak ada perpindahan uang antarscanner.

## Sesudah RAT
1. Pastikan seluruh pemindaian online telah selesai. Tidak ada antrean atau sinkronisasi offline (D-032).
2. Scanner menyerahkan sisa uang fisik; Keuangan menghitung bersama Scanner.
3. Keuangan membuka **Rekonsiliasi Kas**, lalu menekan **Tutup kas** pada baris Scanner. Sistem menampilkan `kas_awal`, total terbayar, sisa seharusnya, sisa fisik dan selisih.
4. Selisih bukan nol wajib diberi alasan tertulis dan dicatat di `log_audit` (`aksi=tutup_kas`, termasuk nominal selisih); tidak ada persetujuan Admin dan tidak ada status tambahan (D-031).
5. Setelah ditutup, kas berstatus `ditutup` dan akun Scanner tidak lagi bisa mencatat pembayaran pada RAT tersebut. Kas yang sudah ditutup tidak dapat diubah atau ditutup ulang.

## Uji utama
Satu kas per Scanner/RAT; tidak ada top-up; duplikat dicegah; saldo negatif mustahil; pemantauan saldo, audit pembayar dan penutupan kas konsisten.


**Batas tanggung jawab:** log pencarian SHU publik tidak terkait kas Scanner. Masa retensinya 14 hari, berbeda dari bukti pembayaran dan audit kas yang mengikuti kebijakan tersendiri.

> **Integrasi v2.2:** konfigurasi CAPTCHA dan Fonnte WhatsApp hanya melalui **Pengaturan → Integrasi** oleh Admin; lihat `docs/03-technical/INTEGRATION_SETTINGS.md`. Tidak perlu menyimpan credential layanan di `.env`; secret wajib terenkripsi.
