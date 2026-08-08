<?php

namespace Tests\Feature;

use App\Livewire\Katalog\Etalase;
use App\Livewire\Katalog\EtalaseDetail;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KatalogPublikFase2Test extends TestCase
{
    use RefreshDatabase;

    private function produkAktif(): Produk
    {
        $kategori = Kategori::create(['nama' => 'Foto Kelas', 'pakai_desain' => false]);

        return Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Foto Kelas A',
            'harga' => 25000,
            'status' => 'aktif',
        ]);
    }

    public function test_katalog_publik_bisa_dibuka_tamu_tanpa_login(): void
    {
        $this->produkAktif();
        Paket::create(['nama' => 'Paket Hemat', 'harga' => 50000, 'status' => 'aktif']);

        $this->get(route('storefront.katalog.index'))
            ->assertOk()
            ->assertSeeLivewire(Etalase::class)
            ->assertSee('Foto Kelas A')
            ->assertSee('Paket Hemat');
    }

    public function test_detail_produk_publik_bisa_dibuka_tamu(): void
    {
        $produk = $this->produkAktif();

        $this->get(route('storefront.katalog.detail', ['tipe' => 'produk', 'id' => $produk->id]))
            ->assertOk()
            ->assertSeeLivewire(EtalaseDetail::class)
            ->assertSee('Foto Kelas A');
    }

    public function test_konteks_publik_membangun_url_storefront(): void
    {
        $produk = $this->produkAktif();

        Livewire::test(Etalase::class, ['konteks' => 'publik'])
            ->assertSet('konteks', 'publik')
            ->assertSee(route('storefront.katalog.detail', ['tipe' => 'produk', 'id' => $produk->id]));
    }

    public function test_tamu_bisa_menambah_produk_ke_keranjang(): void
    {
        $produk = $this->produkAktif();

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->call('tambah')
            ->assertSet('justAdded', true);

        $this->assertSame(1, app(Cart::class)->count());
    }

    public function test_produk_berdesain_wajib_pilih_desain_sebelum_tambah(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Foto Wisuda',
            'harga' => 75000,
            'status' => 'aktif',
        ]);
        Desain::create([
            'kategori_id' => $kategori->id,
            'kode' => 'WSD-2025-001',
            'tahun_ajaran' => '2025/2026',
            'status' => 'aktif',
        ]);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->call('tambah')
            ->assertHasErrors('selectedDesain');

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_detail_publik_tanpa_link_lihat_keranjang(): void
    {
        // Keranjang diakses via ikon navbar; detail hanya "Tambah ke keranjang".
        $produk = $this->produkAktif();

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSee('Tambah ke keranjang')
            ->assertDontSee('Lihat keranjang');
    }
}
