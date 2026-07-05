<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    public function test_non_super_admin_ditolak(): void
    {
        $marketing = User::factory()->create();
        $marketing->assignRole('marketing');

        $this->actingAs($marketing)->get(route('cabang.index'))->assertForbidden();
        $this->actingAs($marketing)->get(route('pengguna.index'))->assertForbidden();
    }

    public function test_super_admin_bisa_membuat_cabang(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('cabang.store'), ['nama' => 'DMA Medan', 'kode_area' => 'MDN'])
            ->assertRedirect(route('cabang.index'));

        $this->assertDatabaseHas('cabang', ['nama' => 'DMA Medan', 'kode_area' => 'MDN']);
    }

    public function test_cabang_dengan_pengguna_tidak_bisa_dihapus(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $penghuni = User::factory()->create(['cabang_id' => $cabang->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('cabang.destroy', $cabang))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cabang', ['id' => $cabang->id]);
    }

    public function test_super_admin_bisa_membuat_pengguna_dengan_role_dan_cabang(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        $this->actingAs($this->superAdmin())
            ->post(route('pengguna.store'), [
                'nama' => 'Rina Marketing',
                'email' => 'rina@dma.test',
                'no_telp' => '0811',
                'role' => 'marketing',
                'cabang_id' => $cabang->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('pengguna.index'));

        $user = User::where('email', 'rina@dma.test')->firstOrFail();
        $this->assertTrue($user->hasRole('marketing'));
        $this->assertSame($cabang->id, $user->cabang_id);
        $this->assertSame('Rina Marketing', $user->name); // name disinkron dari nama
        $this->assertSame('marketing', $user->role);      // label ERD
    }

    public function test_role_lintas_cabang_memaksa_cabang_null(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        $this->actingAs($this->superAdmin())
            ->post(route('pengguna.store'), [
                'nama' => 'Ops Pusat',
                'email' => 'ops2@dma.test',
                'role' => 'operasional',
                'cabang_id' => $cabang->id, // harus diabaikan
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('pengguna.index'));

        $user = User::where('email', 'ops2@dma.test')->firstOrFail();
        $this->assertNull($user->cabang_id);
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->delete(route('pengguna.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
