<?php

namespace Tests\Feature;

use App\Livewire\Katalog\EtalaseDetail;
use App\Livewire\Katalog\ProdukForm;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Semua produk dipesan per JUMLAH PRODUK (qty); tak ada lagi satuan "per siswa".
 * Jumlah siswa jadi input tingkat order (keranjang/checkout).
 */
class ProdukHargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional'] as $r) {
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

    public function test_harga_terkali_jumlah_produk(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda 10RP', 'harga' => 89000, 'status' => 'aktif']);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 100]);

        $lines = app(BookingService::class)->resolveLines($cart);

        $this->assertSame(100, $lines[0]['qty']);
        $this->assertSame(8_900_000, $lines[0]['total']); // 89.000 × 100
        $this->assertArrayNotHasKey('satuan', $lines[0]); // tak ada lagi konsep satuan
    }

    public function test_label_jumlah_di_detail_bukan_siswa(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda 10RP', 'harga' => 89000, 'status' => 'aktif']);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSee('Jumlah')
            ->assertSee('/ item')
            ->assertDontSee('Jumlah siswa')
            ->assertDontSee('per siswa');
    }

    public function test_admin_simpan_produk_tanpa_satuan(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('kategori_id', $kategori->id)
            ->set('nama', 'Wisuda Gradasi 10RP')
            ->set('harga', 89000)
            ->set('status', 'aktif')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produk', ['nama' => 'Wisuda Gradasi 10RP', 'harga' => 89000]);
    }
}
