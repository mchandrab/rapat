# Pengaturan — Integrasi API (v2.2)

**Status: FINAL — keputusan pengguna 29 Oktober 2026.** Sumber aturan: D-019 dan BR-INT-01.

## Tujuan
Semua pengaturan integrasi CAPTCHA dan Fonnte WhatsApp dikelola Admin dari aplikasi, bukan dari `.env`. `.env` tetap diperlukan untuk infrastruktur inti Laravel seperti `APP_KEY`, database dan queue; bila database mati, konfigurasi integrasi tidak bisa dimuat.

## Layar Admin
Menu **Pengaturan → Integrasi** (Tabler), terdiri dari:

### CAPTCHA
- Pilihan provider (awal: Cloudflare Turnstile sebagai opsi implementasi; sesuaikan dengan vendor sebenarnya).
- Status aktif/nonaktif; site key publik; input *secret key baru* dengan placeholder `Tersimpan` tanpa nilai rahasia.
- `Simpan Perubahan`, `Uji Verifikasi CAPTCHA` berdasarkan token challenge asli, `Rotasi Secret`, `Nonaktifkan`.
- Uji harus benar-benar memanggil verifikasi di backend, bukan sekadar menilai format key.
- Ketika nonaktif atau gagal, endpoint publik Cek SHU gagal tertutup (fail closed), tidak bypass.

### Fonnte WhatsApp
- Status aktif/nonaktif; token API baru (masked); opsional nama/label akun.
- `Simpan Perubahan` tidak melakukan pengiriman. `Uji Kirim WhatsApp` meminta nomor penguji dan konfirmasi tindakan.
- Simpan hasil uji dengan status `diminta`, `diterima_api`, `gagal`, `terkirim` hanya jika ada bukti vendor, bukan diasumsikan.
- Kegagalan kirim undangan tidak mengubah anggota Verified; upaya kirim tetap terlacak.

## Kontrak API internal yang disarankan
- `GET /admin/pengaturan/integrasi` → status konfigurasi, boolean `configured`, metadata status tes, site key publik; **tanpa secret**.
- `PUT /admin/pengaturan/integrasi/captcha` → validasi + simpan/rotasi di server.
- `POST /admin/pengaturan/integrasi/captcha/uji` → verifikasi token challenge nyata pada server.
- `PUT /admin/pengaturan/integrasi/fonnte` → validasi + simpan token.
- `POST /admin/pengaturan/integrasi/fonnte/uji` → penerima + konfirmasi eksplisit, rate limit.
- Semua route memakai autentikasi Admin, CSRF, audit tindakan tanpa rahasia. Route dan payload bersifat kontrak desain, bukan integrasi yang telah dijalankan.

## Data dan keamanan
Satu tabel `pengaturan_integrasi` per layanan dengan secret ciphertext, versi credential, status uji, dan metadata Admin. Gunakan Laravel `Crypt`/encrypted cast; jangan dekripsi untuk rendering. Jangan masukkan credential pada log HTTP, debug, audit, notifikasi, atau export. Input secret kosong saat edit berarti mempertahankan secret lama; hapus perlu aksi eksplisit dan konfirmasi. Konfigurasi dibaca langsung dari database per permintaan; tidak ada cache konfigurasi aplikasi yang perlu diinvalidasi.

## Operasional dan batasan
- Retensi log pencarian SHU tetap **14 hari**, bukan retensi konfigurasi integrasi.
- CAPTCHA mengurangi bot, **bukan** membuktikan pemilik NBA. Risiko privasi cek SHU dengan NBA saja tetap memerlukan persetujuan sebelum produksi.
- Untuk mencegah lockout: Admin tetap bisa membuka Pengaturan → Integrasi saat layanan belum diatur.
- Konfigurasi provider spesifik dan detail endpoint HTTP disesuaikan dengan dokumentasi resmi vendor pada saat implementasi.

## Status implementasi (3 November 2026)
Sudah berjalan di aplikasi: `GET /pengaturan/integrasi` (`integrasi.index`), `PUT /pengaturan/integrasi/captcha` (`integrasi.captcha.simpan`), `POST /pengaturan/integrasi/captcha/uji` (throttle 10/menit), `POST /pengaturan/integrasi/captcha/nonaktif`, `PUT /pengaturan/integrasi/fonnte` (`integrasi.fonnte.simpan`), `POST /pengaturan/integrasi/fonnte/uji` (throttle 5/menit, wajib konfirmasi), dan `POST /pengaturan/integrasi/fonnte/nonaktif`. Semua di grup `role:admin`.

Vendor yang dipanggil: Cloudflare Turnstile (`https://challenges.cloudflare.com/turnstile/v0/siteverify`) dan Fonnte (`https://api.fonnte.com/send`, header `Authorization`). Fonnte mengembalikan `status: true` untuk permintaan yang diterima, sehingga status yang dicatat adalah `diterima_api`; `terkirim` hanya dipakai bila ada bukti pengiriman dari vendor. Cache konfigurasi tidak dipakai — pengaturan dibaca langsung dari database, jadi perubahan langsung berlaku.
