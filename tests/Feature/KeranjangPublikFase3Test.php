<?php

namespace Tests\Feature;

use App\Livewire\Booking\Keranjang;
use App\Models\Kategori;
use App\Models\Produk;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeranjangPublikFase3Test extends TestCase
{
    use RefreshDatabase;

    private function produkAktif(string $nama = 'Foto Kelas A', int $harga = 25000): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama' => 'Foto Kelas'], ['pakai_desain' => false]);

        return Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => $nama,
            'harga' => $harga,
            'status' => 'aktif',
        ]);
    }

    private function isiKeranjang(Produk $produk, int $qty = 2): void
    {
        app(Cart::class)->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => $qty]);
    }

    public function test_keranjang_publik_bisa_dibuka_tamu_tanpa_login(): void
    {
        $this->get(route('storefront.keranjang'))
            ->assertOk()
            ->assertSeeLivewire(Keranjang::class);
    }

    public function test_menampilkan_item_dan_subtotal(): void
    {
        $produk = $this->produkAktif('Foto Kelas A', 25000);
        $this->isiKeranjang($produk, 2);

        Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->assertSee('Foto Kelas A')
            ->assertSee('Rp50.000'); // 2 x 25.000
    }

    public function test_konteks_publik_sembunyikan_booking_untuk_dan_jumlah_siswa(): void
    {
        $produk = $this->produkAktif();
        $this->isiKeranjang($produk);

        Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->assertDontSee('Booking untuk')
            ->assertDontSee('Jumlah siswa')
            ->assertSee('Lanjut ke pemesanan');
    }

    public function test_ubah_qty_dan_hapus_item(): void
    {
        $produk = $this->produkAktif();
        $this->isiKeranjang($produk, 1);
        $key = array_key_first(app(Cart::class)->items());

        $component = Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->call('ubahQty', $key, 3);
        $this->assertSame(3, app(Cart::class)->count());

        $component->call('hapus', $key);
        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_lanjut_saat_kosong_menampilkan_info(): void
    {
        Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->call('lanjut')
            ->assertSet('info', 'Keranjang masih kosong.')
            ->assertNoRedirect();
    }

    public function test_lanjut_dengan_item_redirect_ke_checkout(): void
    {
        $produk = $this->produkAktif();
        $this->isiKeranjang($produk);

        // Checkout menerapkan login-gate (CheckoutController): tamu → /masuk.
        Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->call('lanjut')
            ->assertRedirect(route('storefront.checkout'));
    }
}
