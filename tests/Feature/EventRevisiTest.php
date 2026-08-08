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

class EventRevisiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD Lama',
            'alamat' => 'Jl. Lama', 'kota' => 'Jakarta', 'cabang_id' => $this->jkt->id,
        ]);
    }

    private function timEvent(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('tim_event');

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
            'tanggal_event' => now()->addDays(5)->toDateString(),
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_konfirmasi_lokasi_menyimpan_waktu(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('konfirmasiLokasi');

        $this->assertNotNull($order->refresh()->konfirmasi_lokasi_at);
    }

    public function test_revisi_edit_nama_dan_alamat_sekolah(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('mulaiRevisi')
            ->assertSet('namaSekolah', 'SD Lama')
            ->set('namaSekolah', 'SD Baru')
            ->set('alamatSekolah', 'Jl. Baru No. 1')
            ->set('kotaSekolah', 'Depok')
            ->call('simpanRevisi')
            ->assertHasNoErrors()
            ->assertSet('revisiMode', false);

        $this->sekolah->refresh();
        $this->assertSame('SD Baru', $this->sekolah->nama);
        $this->assertSame('Jl. Baru No. 1', $this->sekolah->alamat);
        $this->assertSame('Depok', $this->sekolah->kota);
    }

    public function test_revisi_nama_sekolah_wajib(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('mulaiRevisi')
            ->set('namaSekolah', '')
            ->call('simpanRevisi')
            ->assertHasErrors('namaSekolah');
    }

    public function test_revisi_ganti_desain_item(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda Gradasi', 'harga' => 89000, 'status' => 'aktif']);
        $d1 = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'WIS-01', 'status' => 'aktif']);
        $d2 = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'WIS-02', 'status' => 'aktif']);

        $order = $this->order();
        $item = $order->items()->create([
            'tipe_item' => 'produk', 'produk_id' => $produk->id, 'desain_id' => $d1->id, 'qty' => 30, 'harga' => 89000, 'is_free' => false,
        ]);
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('mulaiRevisi')
            ->assertSet('itemDesain.'.$item->id, $d1->id)
            ->set('itemDesain.'.$item->id, $d2->id)
            ->call('simpanRevisi')
            ->assertHasNoErrors();

        $this->assertSame($d2->id, $item->refresh()->desain_id);
    }

    public function test_revisi_desain_asing_ditolak(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $lain = Kategori::create(['nama' => 'Angkatan', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Wisuda Gradasi', 'harga' => 89000, 'status' => 'aktif']);
        $d1 = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'WIS-01', 'status' => 'aktif']);
        $asing = Desain::create(['kategori_id' => $lain->id, 'kode' => 'ANG-01', 'status' => 'aktif']);

        $order = $this->order();
        $item = $order->items()->create([
            'tipe_item' => 'produk', 'produk_id' => $produk->id, 'desain_id' => $d1->id, 'qty' => 30, 'harga' => 89000, 'is_free' => false,
        ]);
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('mulaiRevisi')
            ->set('itemDesain.'.$item->id, $asing->id)
            ->call('simpanRevisi')
            ->assertHasErrors('itemDesain.'.$item->id);

        $this->assertSame($d1->id, $item->refresh()->desain_id); // tak berubah
    }

    public function test_event_selesai_tak_bisa_revisi(): void
    {
        $order = $this->order();
        $order->update(['event_status' => OrderStatus::EVENT_SELESAI]);
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('konfirmasiLokasi')
            ->assertStatus(422);
    }
}
