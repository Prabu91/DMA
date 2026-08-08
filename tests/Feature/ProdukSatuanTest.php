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
 * Fitur "satuan produk": produk bisa dihitung per-qty (default) atau per-siswa.
 * Satuan hanya mengubah label input jumlah — harga tetap unit × jumlah yang diisi.
 */
class ProdukSatuanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'area', 'marketing'] as $r) {
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

    // ---------- Model ----------

    public function test_default_satuan_qty(): void
    {
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Cetak', 'harga' => 10000, 'status' => 'aktif']);

        $this->assertSame('qty', $produk->satuan);
        $this->assertFalse($produk->isPerSiswa());
        $this->assertSame('Jumlah', $produk->satuanLabel());
        $this->assertSame('item', $produk->satuanUnit());
    }

    public function test_produk_per_siswa_helper(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda 10RP', 'harga' => 89000, 'satuan' => 'siswa', 'status' => 'aktif']);

        $this->assertTrue($produk->isPerSiswa());
        $this->assertSame('Jumlah siswa', $produk->satuanLabel());
        $this->assertSame('siswa', $produk->satuanUnit());
    }

    // ---------- Pricing: jumlah dipesan = qty, harga = unit × jumlah ----------

    public function test_harga_per_siswa_terkali_jumlah(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda 10RP', 'harga' => 89000, 'satuan' => 'siswa', 'status' => 'aktif']);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 100]); // 100 siswa

        $lines = app(BookingService::class)->resolveLines($cart);

        $this->assertSame('siswa', $lines[0]['satuan']);
        $this->assertSame(100, $lines[0]['qty']);
        $this->assertSame(8_900_000, $lines[0]['total']); // 89.000 × 100
    }

    // ---------- Detail produk: label input menyesuaikan satuan ----------

    public function test_label_jumlah_siswa_di_detail(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda 10RP', 'harga' => 89000, 'satuan' => 'siswa', 'status' => 'aktif']);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSee('Jumlah siswa')
            ->assertSee('/ siswa');
    }

    public function test_label_jumlah_biasa_untuk_qty(): void
    {
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan Kunci', 'harga' => 15000, 'status' => 'aktif']);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSee('/ item')
            ->assertDontSee('Jumlah siswa');
    }

    // ---------- Form: admin dapat set & ubah satuan ----------

    public function test_admin_simpan_produk_dengan_satuan_siswa(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('kategori_id', $kategori->id)
            ->set('nama', 'Wisuda Gradasi 10RP')
            ->set('harga', 89000)
            ->set('satuan', 'siswa')
            ->set('status', 'aktif')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('produk', ['nama' => 'Wisuda Gradasi 10RP', 'satuan' => 'siswa']);
    }

    public function test_satuan_tak_valid_ditolak(): void
    {
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('kategori_id', $kategori->id)
            ->set('nama', 'X')
            ->set('harga', 1000)
            ->set('satuan', 'kelas')
            ->set('status', 'aktif')
            ->call('save')
            ->assertHasErrors('satuan');
    }
}
