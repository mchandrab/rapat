<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\LogAudit;
use App\Models\User;
use Database\Seeders\AnggotaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AnggotaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Budi Admin']);
    }

    private function isian(array $ubah = []): array
    {
        return array_merge([
            'no_anggota' => '020006000010399',
            'nama' => 'Anggota Baru',
            'no_hp' => '081200000000',
            'jenis_anggota' => 'biasa',
            'status_anggota' => 'aktif',
            'tgl_ja' => '2024-01-15',
            'tgl_keluar' => '',
            'simpanan_wajib' => 300000,
            'kolektibilitas' => 1,
            'pinjaman_lancar' => 1,
        ], $ubah);
    }

    public function test_nba_disimpan_15_digit_dan_ditampilkan_bertitik(): void
    {
        $this->actingAs($this->admin())
            ->post(route('anggota.store'), $this->isian(['no_anggota' => '020006.000.010.399']))
            ->assertRedirect();

        $this->assertDatabaseHas('anggota', ['no_anggota' => '020006000010399']);

        $this->get(route('anggota.index'))->assertSee('020006.000.010.399');
    }

    public function test_nba_kurang_dari_15_digit_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->from(route('anggota.index'))
            ->post(route('anggota.store'), $this->isian(['no_anggota' => '02000600001039']))
            ->assertSessionHasErrors('no_anggota');

        $this->assertDatabaseCount('anggota', 0);
    }

    public function test_nba_duplikat_ditolak(): void
    {
        Anggota::factory()->create(['no_anggota' => '020006000010399']);

        $this->actingAs($this->admin())
            ->from(route('anggota.index'))
            ->post(route('anggota.store'), $this->isian())
            ->assertSessionHasErrors('no_anggota');

        $this->assertDatabaseCount('anggota', 1);
    }

    public function test_helpdesk_tidak_boleh_mengubah_master(): void
    {
        $helpdesk = User::factory()->create(['role' => 'helpdesk']);
        $anggota = Anggota::factory()->create();

        $this->actingAs($helpdesk)->post(route('anggota.store'), $this->isian())->assertForbidden();

        $this->actingAs($helpdesk)
            ->put(route('anggota.update', $anggota), $this->isian(['no_anggota' => $anggota->no_anggota]))
            ->assertForbidden();
    }

    public function test_pencarian_menerima_nba_dengan_dan_tanpa_titik(): void
    {
        $this->seed(AnggotaSeeder::class);
        $this->actingAs($this->admin());

        Livewire::test('peserta-verifikasi')
            ->set('cari', '020006.000.010.340')
            ->assertSee('Siti Rahma')
            ->assertDontSee('Budi Santoso');

        Livewire::test('peserta-verifikasi')
            ->set('cari', '020006000010340')
            ->assertSee('Siti Rahma')
            ->assertDontSee('Budi Santoso');
    }

    public function test_perubahan_master_dicatat_di_log_audit(): void
    {
        $this->seed(AnggotaSeeder::class);
        $anggota = Anggota::firstWhere('no_anggota', '020006000010339');

        $this->actingAs($this->admin())
            ->put(route('anggota.update', $anggota), $this->isian([
                'no_anggota' => '020006000010339',
                'nama' => 'Budi Santoso Baru',
            ]))
            ->assertRedirect();

        $log = LogAudit::where('entitas', 'anggota')->where('entitas_id', $anggota->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('Budi Santoso', $log->sebelum['nama']);
        $this->assertSame('Budi Santoso Baru', $log->sesudah['nama']);
        $this->assertSame('Budi Admin', User::find($log->aktor_user_id)?->name);
    }

    public function test_log_audit_tidak_dapat_diubah(): void
    {
        $log = LogAudit::catat('buat', 'anggota', 1, null, ['nama' => 'Contoh']);

        $this->expectException(LogicException::class);

        $log->update(['aksi' => 'ubah']);
    }
}
