<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_menyimpan_nama_dan_membiarkan_cabang_role_null(): void
    {
        $this->post('/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@dma.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('app.dashboard', absolute: false));

        $user = User::where('email', 'budi@dma.test')->firstOrFail();

        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame('Budi Santoso', $user->nama);   // kolom ERD ikut terisi
        $this->assertNull($user->cabang_id);              // diatur admin, bukan pendaftar
        $this->assertNull($user->role);
        $this->assertTrue($user->getRoleNames()->isEmpty()); // belum ada role spatie
    }
}
