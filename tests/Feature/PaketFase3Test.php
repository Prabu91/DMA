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

        $this->actingAs($m)->get(route('paket.index'))->assertForbidden();
    }

    public function test_membuat_paket_dengan_produk(): void
    {
        $a = $this->produk('Produk A');
        $b = $this->produk('Produk B');

        Livewire::actingAs($this->admin());

        Livewire::test(PaketIndex::class)
            ->call('create')
            ->set('nama', 'Paket Hemat')
            ->set('harga', 100000)
            ->set('status', 'aktif')
            ->set('selectedProduk', [$a->id, $b->id])
            ->call('save')
            ->assertHasNoErrors();

        $paket = Paket::where('nama', 'Paket Hemat')->firstOrFail();
        $this->assertSame(100000, $paket->harga);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $paket->produk()->pluck('produk.id')->all());
    }

    public function test_edit_paket_sinkron_produk(): void
    {
        $a = $this->produk('Produk A');
        $b = $this->produk('Produk B');
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 5000, 'status' => 'aktif']);
        $paket->produk()->sync([$a->id, $b->id]);

        Livewire::actingAs($this->admin());

        Livewire::test(PaketIndex::class)
            ->call('edit', $paket->id)
            ->assertSet('nama', 'Paket')
            ->set('selectedProduk', [$b->id]) // sisakan satu
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$b->id], $paket->fresh()->produk()->pluck('produk.id')->all());
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
