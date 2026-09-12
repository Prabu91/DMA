<?php

namespace Tests\Feature;

use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Penyelesaian event berhenti di KONFIRMASI HARI-H — tidak ada langkah OTP.
 * Konfirmasi Hari-H sekaligus mengunci order dan menyatakan event selesai.
 */
class EventSelesaiTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT',
            'email_guru' => 'guru@sekolah.test', 'cabang_id' => $this->jkt->id,
        ]);
    }

    private function timEvent(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('tim_event');

        return $u;
    }

    /** Order siap Hari-H: data sekolah & H-2 sudah dikonfirmasi. */
    private function order(bool $siap = true): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->addDays(2)->toDateString(),
            'konfirmasi_lokasi_at' => $siap ? now() : null,
            'konfirmasi_h2_at' => $siap ? now() : null,
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    private function komponen(Order $order, ?User $user = null)
    {
        $user ??= $this->timEvent();
        if ($user->hasRole('tim_event')) {
            $order->timEvent()->attach($user->id);
        }

        return Livewire::actingAs($user)->test(EventDetail::class, ['orderId' => $order->id]);
    }

    public function test_konfirmasi_hari_h_menyelesaikan_event_dan_mengunci_order(): void
    {
        $order = $this->order();

        $this->komponen($order)->call('konfirmasiHariH')->assertHasNoErrors();

        $order->refresh();
        $this->assertNotNull($order->konfirmasi_hh_at);
        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->event_status);
        $this->assertNotNull($order->event_selesai_at);
        $this->assertTrue($order->isLocked());
    }

    public function test_konfirmasi_hari_h_tercatat_di_log_aktivitas(): void
    {
        $order = $this->order();

        $this->komponen($order)->call('konfirmasiHariH');

        foreach (['milestone_hh', 'event_selesai'] as $action) {
            $this->assertDatabaseHas('order_activities', [
                'order_id' => $order->id, 'action' => $action,
            ]);
        }
    }

    public function test_hari_h_ditolak_bila_data_sekolah_belum_dikonfirmasi(): void
    {
        $order = $this->order(siap: false);
        $order->update(['konfirmasi_h2_at' => now()]); // H-2 saja, data sekolah belum

        $this->komponen($order)->call('konfirmasiHariH');

        $order->refresh();
        $this->assertNull($order->konfirmasi_hh_at);
        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->event_status);
    }

    public function test_hari_h_ditolak_bila_h2_belum_dikonfirmasi(): void
    {
        $order = $this->order(siap: false);
        $order->update(['konfirmasi_lokasi_at' => now()]); // data sekolah saja

        $this->komponen($order)->call('konfirmasiHariH');

        $order->refresh();
        $this->assertNull($order->konfirmasi_hh_at);
        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->event_status);
    }

    public function test_hari_h_ditolak_bila_tanggal_event_kosong(): void
    {
        $order = $this->order();
        $order->update(['tanggal_event' => null]);

        $this->komponen($order)->call('konfirmasiHariH')->assertStatus(422);

        $this->assertNull($order->refresh()->konfirmasi_hh_at);
    }

    public function test_admin_lintas_cabang_juga_bisa_konfirmasi_hari_h(): void
    {
        // Menggantikan tombol "selesaikan tanpa OTP" yang lama: admin pusat
        // menyelesaikan event lewat pintu yang sama dengan tim event.
        $order = $this->order();
        $admin = User::factory()->create(); // terpusat → cabang_id null
        $admin->assignRole('admin_sales');

        $this->komponen($order, $admin)->call('konfirmasiHariH')->assertHasNoErrors();

        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->refresh()->event_status);
    }

    public function test_sampai_kantor_bisa_dicatat_setelah_hari_h(): void
    {
        // Dulu tahap ini baru terbuka setelah OTP; sekarang setelah Hari-H.
        $order = $this->order();
        $tim = $this->timEvent();

        $comp = $this->komponen($order, $tim);
        $comp->call('konfirmasiHariH');
        $comp->call('sampaiKantor');

        $this->assertNotNull($order->refresh()->sampai_kantor_at);
    }

    public function test_sampai_kantor_ditolak_sebelum_event_selesai(): void
    {
        $order = $this->order();

        $this->komponen($order)->call('sampaiKantor')->assertStatus(422);

        $this->assertNull($order->refresh()->sampai_kantor_at);
    }

    public function test_kolom_otp_sudah_tidak_ada_di_tabel_orders(): void
    {
        $this->assertFalse(Schema::hasColumn('orders', 'otp_code'));
        $this->assertFalse(Schema::hasColumn('orders', 'otp_expires'));
    }
}
