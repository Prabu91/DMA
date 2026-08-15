<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fase F: komponen visual tracking status order.
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function area(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('admin_sales');

        return $u;
    }

    public function test_tracking_tampil_di_order_detail_staf(): void
    {
        $order = Order::create([
            'booking_code' => 'BK-1', 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah', 'status' => 'dp', 'total' => 1000, 'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->area())
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertSee('Lacak status pesanan')
            ->assertSee('Pesanan dibuat')
            ->assertSee('DP dibayar')
            ->assertSee('Tim sampai kantor');
    }

    public function test_tracking_tandai_batal(): void
    {
        $order = Order::create([
            'booking_code' => 'BK-2', 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah', 'status' => OrderStatus::BATAL, 'total' => 1000, 'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->area())
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertSee('Pesanan dibatalkan');
    }
}
