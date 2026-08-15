<?php

namespace Tests\Feature;

use App\Livewire\ActivityIndex;
use App\Livewire\Booking\OrderDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderActivityTest extends TestCase
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
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-1', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function order(): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'total' => 100000, 'tanggal_booking' => now(),
        ]);
    }

    private function marketing(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('marketing');

        return $u;
    }

    public function test_catat_pembayaran_mencatat_aktivitas_dengan_pelaku(): void
    {
        $order = $this->order();
        $mkt = $this->marketing();

        Livewire::actingAs($mkt)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJenis', 'dp')
            ->set('bayarJumlah', 40000)
            ->set('bayarTanggal', now()->toDateString())
            ->call('catatPembayaran');

        $act = OrderActivity::where('order_id', $order->id)->where('action', 'pembayaran_dp')->first();
        $this->assertNotNull($act);
        $this->assertSame($mkt->id, $act->user_id);
    }

    public function test_catat_jalur_sekolah_pelaku_null(): void
    {
        // Tanpa user web login → pelaku null (bukan FK error).
        $order = $this->order();
        $act = $order->catat('dibuat', 'via portal sekolah');

        $this->assertNull($act->user_id);
        $this->assertSame('Order dibuat', $act->label());
    }

    public function test_halaman_aktivitas_tampil_dan_filter_aksi(): void
    {
        $order = $this->order();
        // Deskripsi unik (label aksi juga ada di dropdown, jadi tak bisa dipakai assert).
        $order->catat('status_dp', 'ket-dp-unik');
        $order->catat('status_lunas', 'ket-lunas-unik');

        $admin = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $admin->assignRole('operasional');

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertSee('ket-dp-unik')
            ->assertSee('ket-lunas-unik')
            ->set('action', 'status_lunas')
            ->assertSee('ket-lunas-unik')
            ->assertDontSee('ket-dp-unik');
    }

    public function test_area_hanya_aktivitas_cabangnya(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekBdg = Sekolah::create(['id_sekolah' => 'SKL-BDG-1', 'nama' => 'SD BDG', 'cabang_id' => $bdg->id]);
        $orderBdg = Order::create([
            'booking_code' => 'BKBDG', 'sekolah_id' => $sekBdg->id, 'cabang_id' => $bdg->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'total' => 1, 'tanggal_booking' => now(),
        ]);
        $orderBdg->catat('status_dp');

        $orderJkt = $this->order();
        $orderJkt->catat('status_lunas');

        $area = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $area->assignRole('admin_sales');

        Livewire::actingAs($area)
            ->test(ActivityIndex::class)
            ->assertSee('BK') // order jkt (booking code prefix)
            ->assertDontSee('BKBDG');
    }
}
