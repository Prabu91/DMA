<?php

namespace Tests\Feature;

use App\Livewire\PenggunaIndex;
use App\Models\Cabang;
use App\Models\Kota;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        User::factory()->create(['cabang_id' => $cabang->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('app.cabang.destroy', $cabang))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cabang', ['id' => $cabang->id]);
    }

    public function test_cabang_dengan_kota_tetap_bisa_dihapus_kota_dilepas(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA Kota', 'kode_area' => 'KOT']);
        $kota = Kota::create(['nama' => 'Kota X', 'cabang_id' => $cabang->id]);

        $this->actingAs($this->superAdmin())
            ->delete(route('app.cabang.destroy', $cabang))
            ->assertRedirect(route('app.cabang.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cabang', ['id' => $cabang->id]);
        // Kota tidak ikut terhapus, hanya dilepas (cabang_id null).
        $this->assertDatabaseHas('kota', ['id' => $kota->id, 'cabang_id' => null]);
    }

    // ---------- Pengguna ----------

    public function test_super_admin_bisa_membuat_pengguna_dengan_role_dan_cabang(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        Livewire::actingAs($this->superAdmin())
            ->test(PenggunaIndex::class)
            ->call('create')
            ->set('nama', 'Rina Marketing')
            ->set('email', 'rina@dma.test')
            ->set('no_telp', '0811')
            ->set('role', 'marketing')
            ->set('cabangIds', [$cabang->id])
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSet('success', 'Pengguna ditambahkan.');   // pesan tidak boleh ikut terhapus

        $user = User::where('email', 'rina@dma.test')->firstOrFail();
        $this->assertTrue($user->hasRole('marketing'));
        $this->assertSame([$cabang->id], $user->cabangIds());
        $this->assertSame('Rina Marketing', $user->name); // name disinkron dari nama
        $this->assertSame('marketing', $user->role);      // label ERD
    }

    public function test_pengguna_bisa_memegang_lebih_dari_satu_cabang(): void
    {
        $a = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $b = Cabang::create(['nama' => 'DMA B', 'kode_area' => 'B']);

        Livewire::actingAs($this->superAdmin())
            ->test(PenggunaIndex::class)
            ->call('create')
            ->set('nama', 'Dwi Cabang')
            ->set('email', 'dwi@dma.test')
            ->set('role', 'marketing')
            ->set('cabangIds', [$a->id, $b->id])
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'dwi@dma.test')->firstOrFail();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $user->cabangIds());
    }

    public function test_pengguna_dua_cabang_melihat_data_kedua_cabang(): void
    {
        // Inti multi-cabang: batas pemisah data ikut melebar, tidak lebih.
        $a = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $b = Cabang::create(['nama' => 'DMA B', 'kode_area' => 'B']);
        $c = Cabang::create(['nama' => 'DMA C', 'kode_area' => 'C']);

        Sekolah::create(['id_sekolah' => 'SKL-A-0001', 'nama' => 'SD A', 'cabang_id' => $a->id]);
        Sekolah::create(['id_sekolah' => 'SKL-B-0001', 'nama' => 'SD B', 'cabang_id' => $b->id]);
        Sekolah::create(['id_sekolah' => 'SKL-C-0001', 'nama' => 'SD C', 'cabang_id' => $c->id]);

        $marketing = User::factory()->create(['cabang_id' => null]);
        $marketing->assignRole('marketing');
        $marketing->cabangs()->sync([$a->id, $b->id]);
        $marketing->lupakanCabangIds();

        $this->actingAs($marketing);
        $terlihat = Sekolah::pluck('nama')->all();

        $this->assertEqualsCanonicalizing(['SD A', 'SD B'], $terlihat);
    }

    public function test_role_lintas_cabang_tidak_ditugaskan_ke_cabang(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        Livewire::actingAs($this->superAdmin())
            ->test(PenggunaIndex::class)
            ->call('create')
            ->set('nama', 'Ops Pusat')
            ->set('email', 'ops2@dma.test')
            ->set('role', 'operasional')
            ->set('cabangIds', [$cabang->id]) // harus diabaikan
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'ops2@dma.test')->firstOrFail();
        $this->assertSame([], $user->cabangIds());
        $this->assertNull($user->cabang_id);
    }

    public function test_admin_sales_dan_editor_terpusat_tanpa_cabang(): void
    {
        Role::findOrCreate('admin_sales', 'web');
        Role::findOrCreate('editor', 'web');
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);

        foreach (['admin_sales', 'editor'] as $i => $role) {
            Livewire::actingAs($this->superAdmin())
                ->test(PenggunaIndex::class)
                ->call('create')
                ->set('nama', 'Pusat '.$role)
                ->set('email', "pusat{$i}@dma.test")
                ->set('role', $role)
                ->set('cabangIds', [$cabang->id]) // harus diabaikan (terpusat)
                ->set('password', 'password123')
                ->set('password_confirmation', 'password123')
                ->call('save')
                ->assertHasNoErrors();

            $user = User::where('email', "pusat{$i}@dma.test")->firstOrFail();
            $this->assertSame([], $user->cabangIds(), "$role harus lintas cabang");
            $this->assertTrue($user->seesAllCabang());
        }
    }

    public function test_tidak_bisa_menghapus_akun_sendiri(): void
    {
        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(PenggunaIndex::class)
            ->call('delete', $admin->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'akun sendiri'));

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_pengguna_yang_masih_pegang_order_tak_bisa_dihapus(): void
    {
        // Dulu ini melempar galat foreign key mentah — itulah error 500-nya.
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-A-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);

        $marketing = User::factory()->create(['cabang_id' => $cabang->id]);
        $marketing->assignRole('marketing');

        Order::create([
            'booking_code' => 'TESTHAPUS0001',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $marketing->id,
            'cabang_id' => $cabang->id,
            'status' => 'baru',
            'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(PenggunaIndex::class)
            ->call('delete', $marketing->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'tidak bisa dihapus'));

        $this->assertDatabaseHas('users', ['id' => $marketing->id]);
    }

    public function test_filter_tetap_terpasang_setelah_membuka_form_ubah(): void
    {
        // Keluhan aslinya: menekan "Ubah" memuat ulang halaman, filter cabang
        // hilang, dan harus dicari ulang tiap kali.
        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $user = User::factory()->create(['nama' => 'Uji Filter', 'cabang_id' => $cabang->id]);
        $user->assignRole('marketing');

        Livewire::actingAs($this->superAdmin())
            ->test(PenggunaIndex::class)
            ->set('filterCabang', (string) $cabang->id)
            ->set('filterRole', 'marketing')
            ->call('edit', $user->id)
            ->assertSet('showForm', true)
            ->assertSet('nama', 'Uji Filter')
            ->assertSet('filterCabang', (string) $cabang->id)   // filter tidak ikut hilang
            ->assertSet('filterRole', 'marketing');
    }
}
