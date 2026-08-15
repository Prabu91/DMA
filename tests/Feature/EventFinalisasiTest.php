<?php

namespace Tests\Feature;

use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fase D — poin 7 & 8: tim event konfirmasi Hari-H (kunci), editor item
 * (tambah/kurang/qty + hitung ulang), dan tombol "sampai kantor".
 */
class EventFinalisasiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    private Produk $produkA;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
        $this->produkA = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan', 'harga' => 15000, 'status' => 'aktif']);
    }

    private function timEvent(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('tim_event');

        return $u;
    }

    private function orderDenganItem(): Order
    {
        $order = Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->addDays(2)->toDateString(),
            'jumlah_siswa' => 10,
            'total' => 150000,
            'tanggal_booking' => now(),
        ]);
        $order->items()->create([
            'tipe_item' => 'produk', 'produk_id' => $this->produkA->id, 'qty' => 10, 'harga' => 15000, 'is_free' => false,
        ]);

        return $order;
    }

    private function comp(Order $order, User $tim)
    {
        $order->timEvent()->attach($tim->id);

        return Livewire::actingAs($tim)->test(EventDetail::class, ['orderId' => $order->id]);
    }

    public function test_tambah_item_hitung_ulang_total(): void
    {
        $order = $this->orderDenganItem();
        $kategori = $this->produkA->kategori;
        $produkB = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Pin', 'harga' => 10000, 'status' => 'aktif']);

        $this->comp($order, $this->timEvent())
            ->set('tambahTipe', 'produk')
            ->set('tambahProdukId', $produkB->id)
            ->set('tambahQty', 2)
            ->call('tambahItem')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(170000, $order->total); // 150.000 + 2×10.000
        $this->assertSame(2, $order->items()->where('is_free', false)->count());
    }

    public function test_ubah_qty_item_hitung_ulang(): void
    {
        $order = $this->orderDenganItem();
        $item = $order->items()->first();

        $this->comp($order, $this->timEvent())
            ->call('ubahQtyItem', $item->id, 5);

        $this->assertSame(5, $item->refresh()->qty);
        $this->assertSame(75000, $order->refresh()->total); // 5×15.000
    }

    public function test_hapus_item_hitung_ulang(): void
    {
        $order = $this->orderDenganItem();
        $item = $order->items()->first();

        $this->comp($order, $this->timEvent())
            ->call('hapusItem', $item->id);

        $this->assertSame(0, $order->refresh()->items()->count());
        $this->assertSame(0, $order->total);
    }

    public function test_konfirmasi_hari_h_mengunci_order(): void
    {
        $order = $this->orderDenganItem();
        $tim = $this->timEvent();

        $this->comp($order, $tim)->call('konfirmasiHariH');

        $order->refresh();
        $this->assertNotNull($order->konfirmasi_hh_at);
        $this->assertNotNull($order->konfirmasi_lokasi_at); // ikut ditandai untuk OTP
        $this->assertTrue($order->isLocked());
    }

    public function test_setelah_hari_h_tidak_bisa_ubah_item(): void
    {
        $order = $this->orderDenganItem();
        $order->update(['konfirmasi_hh_at' => now()]); // terkunci
        $item = $order->items()->first();

        $this->comp($order, $this->timEvent())
            ->call('ubahQtyItem', $item->id, 3)
            ->assertStatus(422);

        $this->assertSame(10, $item->refresh()->qty); // tak berubah
    }

    public function test_setelah_hari_h_tidak_bisa_revisi(): void
    {
        $order = $this->orderDenganItem();
        $order->update(['konfirmasi_hh_at' => now()]);

        $this->comp($order, $this->timEvent())
            ->call('mulaiRevisi')
            ->assertStatus(422);
    }

    public function test_sampai_kantor_setelah_selesai(): void
    {
        $order = $this->orderDenganItem();
        $order->update(['event_status' => OrderStatus::EVENT_SELESAI, 'event_selesai_at' => now(), 'konfirmasi_hh_at' => now()]);

        $this->comp($order, $this->timEvent())
            ->call('sampaiKantor');

        $this->assertNotNull($order->refresh()->sampai_kantor_at);
    }

    public function test_sampai_kantor_ditolak_sebelum_selesai(): void
    {
        $order = $this->orderDenganItem();

        $this->comp($order, $this->timEvent())
            ->call('sampaiKantor')
            ->assertStatus(422);

        $this->assertNull($order->refresh()->sampai_kantor_at);
    }
}
