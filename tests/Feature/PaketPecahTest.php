<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Services\BookingService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H2: paket dipecah jadi order_items produk saat order (paid + free bawaan).
 */
class PaketPecahTest extends TestCase
{
    use RefreshDatabase;

    private function produk(string $nama, int $harga): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama' => 'K'], ['pakai_desain' => false]);

        return Produk::create(['kategori_id' => $kategori->id, 'nama' => $nama, 'harga' => $harga, 'status' => 'aktif']);
    }

    public function test_paket_dipecah_jadi_produk_saat_order(): void
    {
        $cabang = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000001', 'nama' => 'SDN Uji', 'cabang_id' => $cabang->id]);

        $a = $this->produk('Wisuda 10RP', 89000);
        $b = $this->produk('Souvenir Free', 0);

        $paket = Paket::create(['nama' => 'Paket A', 'harga' => 0, 'status' => 'aktif']);
        $paket->items()->create(['produk_id' => $a->id, 'opsi_ukuran' => '10RP', 'qty' => 1, 'harga' => 89000, 'is_free' => false]);
        $paket->items()->create(['produk_id' => $b->id, 'qty' => 2, 'harga' => 0, 'is_free' => true]);

        // hargaJual = 89.000 (hanya non-free).
        $this->assertSame(89000, $paket->hargaJual());

        $cart = new Cart;
        $cart->add(['tipe_item' => 'paket', 'paket_id' => $paket->id, 'qty' => 3]);
        $svc = app(BookingService::class);
        $lines = $svc->resolveLines($cart);

        $ctx = ['sekolah_id' => $sekolah->id, 'marketing_id' => null, 'cabang_id' => $cabang->id, 'sumber' => 'sekolah'];
        $order = $svc->simpan($ctx, $lines, [], 10, $svc->subtotal($lines));

        $order->load('items');
        // Tak ada order_item bertipe paket — semua produk hasil pecah.
        $this->assertSame(0, $order->items->where('tipe_item', 'paket')->count());
        $this->assertSame(2, $order->items->count());

        $itemA = $order->items->firstWhere('produk_id', $a->id);
        $this->assertSame(3, (int) $itemA->qty);           // 1 × 3
        $this->assertSame(89000, (int) $itemA->harga);
        $this->assertFalse((bool) $itemA->is_free);
        $this->assertSame($paket->id, (int) $itemA->paket_id); // jejak asal paket

        $itemB = $order->items->firstWhere('produk_id', $b->id);
        $this->assertSame(6, (int) $itemB->qty);           // 2 × 3
        $this->assertSame(0, (int) $itemB->harga);
        $this->assertTrue((bool) $itemB->is_free);

        // Total = harga produk berbayar (89.000 × 3).
        $this->assertSame(267000, (int) $order->total);
    }
}
