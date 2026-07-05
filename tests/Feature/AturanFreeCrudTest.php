<?php

namespace Tests\Feature;

use App\Livewire\Katalog\AturanFreeIndex;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AturanFreeCrudTest extends TestCase
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

    public function test_marketing_ditolak(): void
    {
        $m = User::factory()->create();
        $m->assignRole('marketing');

        $this->actingAs($m)->get(route('aturan-free.index'))->assertForbidden();
    }

    public function test_super_admin_membuat_aturan(): void
    {
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Bingkai', 'harga' => 1000, 'status' => 'aktif']);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        Livewire::actingAs($admin);

        Livewire::test(AturanFreeIndex::class)
            ->call('create')
            ->set('paket_id', $paket->id)
            ->set('basis', 'qty')
            ->set('operator', '>=')
            ->set('nilai', 50)
            ->set('hasil_produk_id', $produk->id)
            ->set('hasil_ukuran', '10RP')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('aturan_free_sekolah', [
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '>=', 'nilai' => 50,
            'hasil_produk_id' => $produk->id, 'hasil_ukuran' => '10RP',
        ]);
    }

    public function test_validasi_wajib_hasil_produk(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        Livewire::actingAs($admin);

        Livewire::test(AturanFreeIndex::class)
            ->call('create')
            ->set('paket_id', $paket->id)
            ->set('nilai', 50)
            ->call('save')
            ->assertHasErrors(['hasil_produk_id']);
    }
}
