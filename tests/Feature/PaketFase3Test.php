<?php

namespace Tests\Feature;

use App\Livewire\Katalog\PaketIndex;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaketFase3Test extends TestCase
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

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function produk(string $nama): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama' => 'K'], ['pakai_desain' => false]);

        return Produk::create(['kategori_id' => $kategori->id, 'nama' => $nama, 'harga' => 1000, 'status' => 'aktif']);
    }

    public function test_marketing_ditolak_akses_paket(): void
    {
        $m = User::factory()->create();
        $m->assignRole('marketing');

        $this->actingAs($m)->get(route('app.paket.index'))->assertForbidden();
    }

    public function test_membuat_paket_dengan_item(): void
    {
        $a = $this->produk('Produk A');
        $b = $this->produk('Produk B');

        Livewire::actingAs($this->admin());

        Livewire::test(PaketIndex::class)
            ->call('create')
            ->set('nama', 'Paket Hemat')
            ->set('status', 'aktif')
            ->set('items', [
                ['produk_id' => $a->id, 'opsi_ukuran' => null, 'qty' => 1, 'harga' => 60000, 'is_free' => false],
                ['produk_id' => $b->id, 'opsi_ukuran' => null, 'qty' => 1, 'harga' => 40000, 'is_free' => true], // free
            ])
            ->call('save')
            ->assertHasNoErrors();

        $paket = Paket::where('nama', 'Paket Hemat')->firstOrFail();
        $this->assertSame(60000, $paket->harga); // harga = Σ non-free (40rb item free tak dihitung)
        $this->assertSame(2, $paket->items()->count());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $paket->items()->pluck('produk_id')->all());
    }

    public function test_edit_paket_sinkron_item(): void
    {
        $a = $this->produk('Produk A');
        $b = $this->produk('Produk B');
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 5000, 'status' => 'aktif']);
        $paket->items()->create(['produk_id' => $a->id, 'qty' => 1, 'harga' => 1000, 'is_free' => false]);
        $paket->items()->create(['produk_id' => $b->id, 'qty' => 1, 'harga' => 1000, 'is_free' => false]);

        Livewire::actingAs($this->admin());

        Livewire::test(PaketIndex::class)
            ->call('edit', $paket->id)
            ->assertSet('nama', 'Paket')
            ->set('items', [['produk_id' => $b->id, 'opsi_ukuran' => null, 'qty' => 1, 'harga' => 1000, 'is_free' => false]]) // sisakan satu
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$b->id], $paket->fresh()->items()->pluck('produk_id')->all());
    }

    public function test_paket_dipakai_aturan_free_tidak_bisa_dihapus(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 5000, 'status' => 'aktif']);
        $paket->aturanFreeSekolah()->create(['basis' => 'qty', 'operator' => '>=', 'nilai' => 50]);

        Livewire::actingAs($this->admin());

        Livewire::test(PaketIndex::class)
            ->call('delete', $paket->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'tidak bisa dihapus'));

        $this->assertDatabaseHas('paket', ['id' => $paket->id]);
    }
}
