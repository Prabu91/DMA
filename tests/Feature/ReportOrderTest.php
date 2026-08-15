<?php

namespace Tests\Feature;

use App\Livewire\ReportOrder;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportOrderTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Produk $produkA;

    private Produk $produkB;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
        $this->produkA = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan Kunci', 'harga' => 15000, 'status' => 'aktif']);
        $this->produkB = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Pin Enamel', 'harga' => 10000, 'status' => 'aktif']);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function marketing(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id, 'nama' => 'Marketing Jaksel']);
        $u->assignRole('marketing');

        return $u;
    }

    private function orderDengan3Item(?User $marketing = null): Order
    {
        $sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-000001', 'nama' => 'SDN Merdeka',
            'alamat' => 'Jl. Merdeka 1', 'cabang_id' => $this->jkt->id,
        ]);
        $order = Order::create([
            'booking_code' => 'BK-XYZ', 'sekolah_id' => $sekolah->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => ($marketing ?? $this->marketing())->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'total' => 100000, 'tanggal_booking' => now(),
        ]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produkA->id, 'qty' => 4, 'harga' => 15000, 'is_free' => false]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produkB->id, 'qty' => 2, 'harga' => 10000, 'is_free' => false]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produkA->id, 'qty' => 1, 'harga' => 0, 'is_free' => true]);

        return $order;
    }

    public function test_per_item_satu_order_tampil_banyak_baris(): void
    {
        $this->orderDengan3Item();

        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->assertViewHas('totalBaris', 3)          // 3 item → 3 baris
            ->assertViewHas('totalQty', 7)            // 4 + 2 + 1
            ->assertSee('Gantungan Kunci')
            ->assertSee('Pin Enamel')
            ->assertSee('BK-XYZ')
            ->assertSee('SDN Merdeka');
    }

    public function test_filter_produk(): void
    {
        $this->orderDengan3Item();

        // Catatan: nama produk juga muncul di dropdown filter, jadi cukup uji
        // ringkasan (baris/qty) untuk memastikan filter bekerja.
        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->set('produkId', (string) $this->produkB->id)
            ->assertViewHas('totalBaris', 1)
            ->assertViewHas('totalQty', 2);
    }

    public function test_filter_hanya_berbayar(): void
    {
        $this->orderDengan3Item();

        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->set('jenis', 'berbayar')
            ->assertViewHas('totalBaris', 2)  // item free dikecualikan
            ->assertViewHas('totalQty', 6);
    }

    public function test_pencarian_sekolah(): void
    {
        $this->orderDengan3Item();

        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->set('q', 'Merdeka')
            ->assertViewHas('totalBaris', 3);
    }

    public function test_nominal_ikut_diskon_per_item(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000009', 'nama' => 'SDN Diskon', 'cabang_id' => $this->jkt->id]);
        $order = Order::create([
            'booking_code' => 'BK-DISK', 'sekolah_id' => $sekolah->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => $this->marketing()->id, 'sumber' => 'sekolah', 'status' => 'baru', 'total' => 60000, 'tanggal_booking' => now(),
        ]);
        // harga 15.000 × 4, diskon 5.000/satuan → nominal = (15.000−5.000) × 4 = 40.000
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produkA->id, 'qty' => 4, 'harga' => 15000, 'diskon' => 5000, 'is_free' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->set('produkId', (string) $this->produkA->id)
            ->assertViewHas('totalQty', 4)
            ->assertViewHas('totalNominal', 40000);
    }

    public function test_order_belum_ditugaskan_marketing_dikecualikan(): void
    {
        // Order tanpa marketing_id (belum di-assign) tidak boleh tampil.
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000002', 'nama' => 'SDN Menunggu', 'cabang_id' => $this->jkt->id]);
        $order = Order::create([
            'booking_code' => null, 'sekolah_id' => $sekolah->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => null, 'sumber' => 'sekolah', 'status' => 'baru', 'total' => 50000, 'tanggal_booking' => now(),
        ]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produkA->id, 'qty' => 5, 'harga' => 15000, 'is_free' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(ReportOrder::class)
            ->assertViewHas('totalBaris', 0)
            ->assertViewHas('totalQty', 0)
            ->assertDontSee('SDN Menunggu');
    }

    public function test_sort_toggle_dan_field_invalid_diabaikan(): void
    {
        $this->orderDengan3Item();

        $comp = Livewire::actingAs($this->superAdmin())->test(ReportOrder::class);

        // Klik pertama → asc; klik kedua field sama → desc.
        $comp->call('sortBy', 'qty')->assertSet('sortField', 'qty')->assertSet('sortDir', 'asc');
        $comp->call('sortBy', 'qty')->assertSet('sortField', 'qty')->assertSet('sortDir', 'desc');

        // Ganti field → reset ke asc.
        $comp->call('sortBy', 'sekolah')->assertSet('sortField', 'sekolah')->assertSet('sortDir', 'asc');

        // Field di luar whitelist → no-op (anti-injeksi).
        $comp->call('sortBy', 'oi.qty; drop table orders')->assertSet('sortField', 'sekolah');
    }

    public function test_non_super_admin_ditolak(): void
    {
        $u = User::factory()->create();
        $u->assignRole('operasional');

        Livewire::actingAs($u)
            ->test(ReportOrder::class)
            ->assertStatus(403);
    }
}
