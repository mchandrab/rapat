<?php

namespace App\Services;

use App\Models\LogAudit;
use App\Models\LogUndangan;
use App\Models\PengaturanIntegrasi;
use App\Models\Peserta;
use App\Models\User;
use LogicException;

/**
 * BR-005: setiap kirim/cetak undangan dicatat. WhatsApp memakai pengaturan
 * Fonnte v2.2; status hanya "terkirim" bila vendor memberi bukti.
 */
final class UndanganPeserta
{
    public function kirim(Peserta $peserta, User $petugas, string $metode): LogUndangan
    {
        if ($peserta->status_verifikasi !== Peserta::VERIFIED) {
            throw new LogicException('Undangan hanya dapat diproses setelah peserta Verified.');
        }

        $cetak = $metode === LogUndangan::METODE_CETAK;

        if ($cetak) {
            $log = LogUndangan::create([
                'rapat_id' => $peserta->rapat_id,
                'peserta_id' => $peserta->id,
                'petugas_user_id' => $petugas->id,
                'metode' => LogUndangan::METODE_CETAK,
                'status' => LogUndangan::DICETAK,
                'waktu_aksi' => now(),
            ]);
        } else {
            $log = $this->kirimWhatsapp($peserta, $petugas);
        }

        LogAudit::catat('undangan', 'peserta', $peserta->id, null, [
            'metode' => $log->metode,
            'status' => $log->status,
        ]);

        return $log;
    }

    private function kirimWhatsapp(Peserta $peserta, User $petugas): LogUndangan
    {
        $peserta->loadMissing('anggota', 'rapat');

        $nomor = trim((string) $peserta->anggota?->no_hp);

        $log = LogUndangan::create([
            'rapat_id' => $peserta->rapat_id,
            'peserta_id' => $peserta->id,
            'petugas_user_id' => $petugas->id,
            'metode' => LogUndangan::METODE_WA,
            'status' => LogUndangan::GAGAL,
            'waktu_aksi' => now(),
            'detail_error' => $nomor === ''
                ? 'Anggota belum memiliki nomor HP.'
                : 'Pengiriman WhatsApp belum dicoba.',
        ]);

        if ($nomor === '') {
            return $log;
        }

        $hasil = (new Integrasi)->kirimWhatsapp($nomor, $this->isiPesan($peserta));

        $log->update([
            'status' => $hasil['status'],
            'provider_message_id' => $hasil['id'],
            'detail_error' => $hasil['status'] === PengaturanIntegrasi::GAGAL ? $hasil['pesan'] : null,
        ]);

        return $log;
    }

    private function isiPesan(Peserta $peserta): string
    {
        $rapat = $peserta->rapat;

        return implode("\n", array_filter([
            'Undangan Rapat Koperasi Kredit Rukun',
            'Nama: '.($peserta->anggota?->nama ?? '-'),
            'Rapat: '.($rapat?->judul ?? '-'),
            'Tanggal: '.($rapat?->tgl_rapat?->format('d/m/Y') ?? '-'),
            'Jam presensi: '.($rapat?->jam_mulai_presensi ?? '-').' WIB',
            'Lokasi: '.($rapat?->lokasi ?? '-'),
            'Alamat: '.($rapat?->alamat_lokasi ?? '-'),
            $rapat?->tautan_google_maps ? 'Google Maps: '.$rapat->tautan_google_maps : null,
            'Tunjukkan QR undangan saat presensi.',
        ]));
    }
}
