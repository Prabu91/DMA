<?php

namespace Tests\Feature;

use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Desain;
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
 * Permintaan pemilik: desain ditampilkan ke tim event saat konfirmasi terakhir
 * (Hari-H), sebagai GAMBAR — setelah itu order terkunci dan tidak bisa diubah.
 */
class EventTinjauDesainTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    private Kategori $berdesain;

    private Kategori $tanpaDesain;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id,
        ]);
        $this->berdesain = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $this->tanpaDesain = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
    }

    private function timEvent(Order $order): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('tim_event');
        $order->timEvent()->attach($u->id);

        return $u;
    }

    private function order(): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->addDays(2)->toDateString(),
            'konfirmasi_lokasi_at' => now(),
            'konfirmasi_h2_at' => now(),
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    private function produk(Kategori $kategori, string $nama): Produk
    {
        return Produk::create([
            'kategori_id' => $kategori->id, 'nama' => $nama, 'harga' => 50000, 'aktif' => true,
        ]);
    }

    private function item(Order $order, Produk $produk, ?Desain $desain = null): void
    {
        $order->items()->create([
            'tipe_item' => 'produk',
            'produk_id' => $produk->id,
            'desain_id' => $desain?->id,
            'qty' => 1,
            'harga' => 50000,
            'is_free' => false,
        ]);
    }

    public function test_desain_terpilih_tampil_sebagai_gambar_saat_konfirmasi_terakhir(): void
    {
        $order = $this->order();
        $desain = Desain::create([
            'kategori_id' => $this->berdesain->id, 'kode' => 'WSD-007', 'seri' => 'Klasik',
            'tahun_ajaran' => '2026/2027', 'status' => 'aktif', 'foto_preview' => 'desain/wsd-007.jpg',
        ]);
        $this->item($order, $this->produk($this->berdesain, 'Wisuda Gradasi'), $desain);

        Livewire::actingAs($this->timEvent($order))
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertSee('Desain yang akan dikunci')
            ->assertSee('WSD-007')
            ->assertSee('storage/desain/wsd-007.jpg', escape: false);
    }

    public function test_item_tanpa_desain_diberi_peringatan_tapi_tidak_menghalangi(): void
    {
        $order = $this->order();
        $this->item($order, $this->produk($this->berdesain, 'Wisuda Gradasi')); // tanpa desain

        $komponen = Livewire::actingAs($this->timEvent($order))
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertSee('Desain belum dipilih.')
            ->assertSee('1 item belum punya desain.');

        // Peringatan saja — konfirmasi Hari-H tetap boleh jalan.
        $komponen->call('konfirmasiHariH')->assertHasNoErrors();
        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->refresh()->event_status);
    }

    public function test_produk_kategori_tanpa_desain_tidak_ikut_ditinjau(): void
    {
        $order = $this->order();
        $this->item($order, $this->produk($this->tanpaDesain, 'Box Pensil'));

        Livewire::actingAs($this->timEvent($order))
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertDontSee('Desain yang akan dikunci')
            ->assertCount('itemBerdesain', 0);
    }

    public function test_hanya_item_berdesain_yang_masuk_daftar_tinjauan(): void
    {
        $order = $this->order();
        $this->item($order, $this->produk($this->berdesain, 'Wisuda Gradasi'));
        $this->item($order, $this->produk($this->tanpaDesain, 'Box Pensil'));

        Livewire::actingAs($this->timEvent($order))
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertCount('itemBerdesain', 1)
            ->assertCount('itemTanpaDesain', 1);
    }

    public function test_tinjauan_hilang_setelah_order_terkunci(): void
    {
        // Blok ini gunanya SEBELUM mengunci; setelah terkunci tak ada lagi yang
        // bisa diperbaiki, dan desainnya tetap terlihat di daftar item.
        $order = $this->order();
        $desain = Desain::create([
            'kategori_id' => $this->berdesain->id, 'kode' => 'WSD-007',
            'tahun_ajaran' => '2026/2027', 'status' => 'aktif', 'foto_preview' => 'desain/wsd-007.jpg',
        ]);
        $this->item($order, $this->produk($this->berdesain, 'Wisuda Gradasi'), $desain);

        $tim = $this->timEvent($order);
        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('konfirmasiHariH');

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertDontSee('Desain yang akan dikunci')
            ->assertSee('WSD-007'); // tetap terbaca di daftar item
    }
}
