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

        $this->actingAs($marketing)->get(route('app.cabang.index'))->assertForbidden();
        $this->actingAs($marketing)->get(route('app.pengguna.index'))->assertForbidden();
    }

    public function test_super_admin_bisa_membuat_cabang(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('app.cabang.store'), ['nama' => 'DMA Medan', 'kode_area' => 'MDN'])
            ->assertRedirect(route('app.cabang.index'));

        $this->assertDatabaseHas('cabang', ['nama' => 'DMA Medan', 'kode_area' => 'MDN']);
    }

    public function test_cabang_dengan_pengguna_tidak_bisa_dihapus(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $penghuni = User::factory()->create(['cabang_id' => $cabang->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('app.cabang.destroy', $cabang))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cabang', ['id' => $cabang->id]);
    }

    public function test_cabang_dengan_kota_tetap_bisa_dihapus_kota_dilepas(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA Kota', 'kode_area' => 'KOT']);
        $kota = \App\Models\Kota::create(['nama' => 'Kota X', 'cabang_id' => $cabang->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('app.cabang.destroy', $cabang))
            ->assertRedirect(route('app.cabang.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cabang', ['id' => $cabang->id]);
        // Kota tidak ikut terhapus, hanya dilepas (cabang_id null).
        $this->assertDatabaseHas('kota', ['id' => $kota->id, 'cabang_id' => null]);
    }

    public function test_super_admin_bisa_membuat_pengguna_dengan_role_dan_cabang(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        $this->actingAs($this->superAdmin())
            ->post(route('app.pengguna.store'), [
                'nama' => 'Rina Marketing',
                'email' => 'rina@dma.test',
                'no_telp' => '0811',
                'role' => 'marketing',
                'cabang_id' => $cabang->id,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('app.pengguna.index'));

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
            ->post(route('app.pengguna.store'), [
                'nama' => 'Ops Pusat',
                'email' => 'ops2@dma.test',
                'role' => 'operasional',
                'cabang_id' => $cabang->id, // harus diabaikan
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('app.pengguna.index'));

        $user = User::where('email', 'ops2@dma.test')->firstOrFail();
        $this->assertNull($user->cabang_id);
    }

    public function test_admin_sales_dan_editor_terpusat_cabang_null(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin_sales', 'web');
        \Spatie\Permission\Models\Role::findOrCreate('editor', 'web');
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        foreach (['admin_sales', 'editor'] as $i => $role) {
            $this->actingAs($this->superAdmin())
                ->post(route('app.pengguna.store'), [
                    'nama' => 'Pusat '.$role,
                    'email' => "pusat{$i}@dma.test",
                    'role' => $role,
                    'cabang_id' => $cabang->id, // harus diabaikan (terpusat)
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ])
                ->assertRedirect(route('app.pengguna.index'));

            $user = User::where('email', "pusat{$i}@dma.test")->firstOrFail();
            $this->assertNull($user->cabang_id, "$role harus lintas cabang (cabang_id null)");
            $this->assertTrue($user->seesAllCabang());
        }
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->delete(route('app.pengguna.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
