<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderPembayaran;
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
 * Permintaan pemilik: harga item order tetap bisa dikoreksi walau order sudah
 * TERKUNCI, dan hanya oleh admin sales / super admin.
 */
class OrderHargaItemTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Produk $produk;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cabang = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $kategori = Kategori::create(['nama' => 'Yearbook']);
        $this->produk = Produk::create([
            'kategori_id' => $kategori->id, 'nama' => 'Yearbook 30 Hal', 'harga' => 100000, 'aktif' => true,
        ]);
    }

    private function staf(string $role): User
    {
        $u = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $u->assignRole($role);

        return $u;
    }

    /** Order TERKUNCI (Hari-H sudah dikonfirmasi) dengan satu item Rp100.000 x 2. */
    private function orderTerkunci(): Order
    {
        $sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD Uji', 'cabang_id' => $this->cabang->id,
        ]);

        $order = Order::create([
            'booking_code' => 'UJI-HARGA-1',
            'sekolah_id' => $sekolah->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_SELESAI,
            'konfirmasi_hh_at' => now(),
            'total' => 200000,
            'tanggal_booking' => now(),
        ]);

        $order->items()->create([
            'tipe_item' => 'produk',
            'produk_id' => $this->produk->id,
            'qty' => 2,
            'harga' => 100000,
            'is_free' => false,
        ]);

        return $order;
    }

    private function item(Order $order)
    {
        return $order->items()->where('is_free', false)->first();
    }

    public function test_admin_sales_bisa_ubah_harga_walau_order_terkunci(): void
    {
        $order = $this->orderTerkunci();
        $this->assertTrue($order->isLocked());

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->assertSet('hargaBaru', 100000)
            ->set('hargaBaru', 75000)
            ->call('simpanHarga')
            ->assertHasNoErrors();

        $this->assertSame(75000, (int) $this->item($order)->harga);
        $this->assertSame(150000, (int) $order->refresh()->total); // 75.000 x 2
    }

    public function test_super_admin_juga_bisa(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->staf('super_admin'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->set('hargaBaru', 120000)
            ->call('simpanHarga')
            ->assertHasNoErrors();

        $this->assertSame(240000, (int) $order->refresh()->total);
    }

    public function test_marketing_tidak_boleh_ubah_harga(): void
    {
        $order = $this->orderTerkunci();
        $marketing = $this->staf('marketing');
        $order->update(['marketing_id' => $marketing->id]);

        Livewire::actingAs($marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->assertForbidden();

        $this->assertSame(100000, (int) $this->item($order)->harga);
    }

    public function test_tim_event_tidak_boleh_ubah_harga(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->staf('tim_event'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('simpanHarga')
            ->assertForbidden();
    }

    public function test_portal_sekolah_tidak_boleh_ubah_harga(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($order->sekolah, 'sekolah')
            ->test(OrderDetail::class, ['konteks' => 'sekolah', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->assertForbidden();
    }

    public function test_harga_minus_ditolak(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->set('hargaBaru', -1)
            ->call('simpanHarga')
            ->assertHasErrors('hargaBaru');

        $this->assertSame(100000, (int) $this->item($order)->harga);
    }

    public function test_order_batal_tidak_bisa_diubah_harganya(): void
    {
        $order = $this->orderTerkunci();
        $order->update(['status' => OrderStatus::BATAL]);

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->assertStatus(422);
    }

    public function test_perubahan_harga_menghitung_ulang_status_pembayaran(): void
    {
        // Sudah bayar Rp150.000 dari tagihan Rp200.000 (status DP). Harga
        // dikoreksi turun sampai tagihan <= yang sudah dibayar -> jadi LUNAS.
        $order = $this->orderTerkunci();
        $order->pembayaran()->create([
            'jenis' => 'dp',
            'jumlah' => 150000,
            'status' => OrderPembayaran::STATUS_APPROVED,
            'tanggal_bayar' => now(),
        ]);
        $order->load('pembayaran')->recalcStatusPembayaran();
        $this->assertSame(OrderStatus::DP, $order->refresh()->status);

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->set('hargaBaru', 70000) // 70.000 x 2 = 140.000
            ->call('simpanHarga');

        $this->assertSame(OrderStatus::LUNAS, $order->refresh()->status);
    }

    public function test_perubahan_harga_tercatat_di_log_aktivitas(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->set('hargaBaru', 80000)
            ->call('simpanHarga');

        $log = $order->activities()->where('action', 'harga_item_diubah')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Rp100.000', (string) $log->description);
        $this->assertStringContainsString('Rp80.000', (string) $log->description);
    }

    public function test_diskon_ikut_dipangkas_bila_harga_turun_di_bawahnya(): void
    {
        $order = $this->orderTerkunci();
        $this->item($order)->update(['diskon' => 30000]);

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $this->item($order)->id)
            ->set('hargaBaru', 20000)
            ->call('simpanHarga');

        // Diskon per satuan tak boleh melebihi harga satuan barunya.
        $this->assertSame(20000, (int) $this->item($order)->diskon);
    }
}
