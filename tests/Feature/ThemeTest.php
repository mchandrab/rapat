<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_memiliki_kontrol_tema_dan_aset_tabler(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('vendor/tabler/tabler.min.css', false)
            ->assertSee('vendor/tabler/tabler-icons.min.css', false)
            ->assertSee('css/erat.css', false)
            ->assertSee('js/theme.js', false);
    }

    public function test_sidebar_mewarisi_tema_halaman(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee('<aside class="navbar navbar-vertical navbar-expand-lg"', false)
            ->assertDontSee('<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">', false);
    }

    public function test_font_inter_lokal_tersedia(): void
    {
        $this->assertFileExists(public_path('vendor/inter/InterVariable.woff2'));
        $this->assertStringContainsString('font-family: "Inter"', file_get_contents(public_path('css/erat.css')));
    }
}
