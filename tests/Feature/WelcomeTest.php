<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_menampilkan_cta_masuk_dan_daftar_untuk_tamu(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Sistem operasional')
            ->assertSee('Masuk')
            ->assertSee('Daftar');
    }

    public function test_welcome_menampilkan_buka_dashboard_untuk_user_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Buka dashboard')
            ->assertDontSee('Daftar');
    }
}
