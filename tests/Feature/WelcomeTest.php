<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_tampil_untuk_tamu(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Delapan Mata Air')
            ->assertSee('Daftar sekolah');
    }

    public function test_staf_diarahkan_ke_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('app.dashboard'));
    }
}
