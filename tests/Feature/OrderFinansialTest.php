<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderPembayaran;
use App\Models\Sekolah;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase G1: helper finansial Order (diskon, dibayar, outstanding, status otomatis)
 * + grup kategori.
 */
class OrderFinansialTest extends TestCase
{
    use RefreshDatabase;

    private function order(int $total = 100000): Order
    {
        $cabang = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000001', 'nama' => 'SDN Uji', 'cabang_id' => $cabang->id]);

        return Order::create([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $sekolah->id, 'cabang_id' => $cabang->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'total' => $total, 'tanggal_booking' => now(),
        ]);
    }

    private function bayar(Order $order, int $jumlah, string $jenis = 'dp'): void
    {
        OrderPembayaran::create([
            'order_id' => $order->id, 'jenis' => $jenis, 'jumlah' => $jumlah, 'tanggal_bayar' => now()->toDateString(),
            'status' => OrderPembayaran::STATUS_APPROVED, // hanya yg disetujui dihitung
        ]);
        $order->load('pembayaran');
        $order->recalcStatusPembayaran();
    }

    public function test_belum_bayar_status_baru_outstanding_penuh(): void
    {
        $order = $this->order(100000);
        $this->assertSame(0, $order->totalDibayar());
        $this->assertSame(100000, $order->outstanding());
        $order->recalcStatusPembayaran();
        $this->assertSame(OrderStatus::BARU, $order->refresh()->status);
    }

    public function test_dp_sebagian_status_dp(): void
    {
        $order = $this->order(100000);
        $this->bayar($order, 40000, 'dp');

        $order->refresh()->load('pembayaran');
        $this->assertSame(40000, $order->totalDibayar());
        $this->assertSame(60000, $order->outstanding());
        $this->assertSame(OrderStatus::DP, $order->status);
    }

    public function test_lunas_setelah_pelunasan(): void
    {
        $order = $this->order(100000);
        $this->bayar($order, 40000, 'dp');
        $this->bayar($order, 60000, 'pelunasan');

        $order->refresh()->load('pembayaran');
        $this->assertSame(0, $order->outstanding());
        $this->assertSame(OrderStatus::LUNAS, $order->status);
    }

    public function test_diskon_per_item_mengurangi_tagihan(): void
    {
        $order = $this->order(100000);
        // Item harga 100.000 dgn diskon 20.000/satuan → total setelah diskon = 80.000.
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => null, 'qty' => 1, 'harga' => 100000, 'diskon' => 20000, 'is_free' => false]);
        $order->load('items');

        $this->assertSame(20000, $order->totalDiskon());
        $this->assertSame(80000, $order->totalSetelahDiskon());

        $this->bayar($order, 80000, 'dp');
        $order->refresh()->load('items', 'pembayaran');
        $this->assertSame(0, $order->outstanding());
        $this->assertSame(OrderStatus::LUNAS, $order->status);
    }

    public function test_order_batal_tak_diubah_recalc(): void
    {
        $order = $this->order(100000);
        $order->update(['status' => OrderStatus::BATAL]);
        $this->bayar($order, 50000, 'dp');

        $this->assertSame(OrderStatus::BATAL, $order->refresh()->status);
    }

    public function test_grup_kategori_label(): void
    {
        $k = Kategori::create(['nama' => 'Yearbook', 'pakai_desain' => true, 'grup' => 'yb']);
        $this->assertSame('yb', $k->grup);
        $this->assertSame('Yearbook (YB)', Kategori::grupLabel($k->grup));
        $this->assertSame('Reguler', Kategori::grupLabel(null));

        // Default grup = reguler bila tak diisi.
        $r = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $this->assertSame('reguler', $r->grup);
    }
}
