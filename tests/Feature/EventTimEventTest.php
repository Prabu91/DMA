<?php

namespace Tests\Feature;

use App\Livewire\Event\EventIndex;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use App\Support\RoleMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventTimEventTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    private User $mkt;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
        $this->mkt = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $this->mkt->assignRole('marketing');
    }

    private function timEvent(Cabang $cabang): User
    {
        $u = User::factory()->create(['cabang_id' => $cabang->id]);
        $u->assignRole('tim_event');

        return $u;
    }

    private function order(Cabang $cabang, ?int $marketingId = 0): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $cabang->id,
            'marketing_id' => $marketingId === 0 ? $this->mkt->id : $marketingId,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->addDays(10)->toDateString(),
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_menu_tim_event_punya_route_jadwal_event(): void
    {
        $items = RoleMenu::for($this->timEvent($this->jkt));
        $jadwal = collect($items)->firstWhere('label', 'Jadwal Event');

        $this->assertSame('app.event.index', $jadwal['route']);
        // Tak ada lagi item "Order" placeholder mati.
        $this->assertNull(collect($items)->firstWhere('label', 'Order'));
    }

    public function test_event_index_hanya_tampilkan_yang_ditugaskan(): void
    {
        $ditugaskan = $this->order($this->jkt);
        $lain = $this->order($this->jkt); // ada di cabang sama tapi tidak di-assign

        $tim = $this->timEvent($this->jkt);
        $ditugaskan->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventIndex::class)
            ->assertSee($ditugaskan->booking_code)
            ->assertDontSee($lain->booking_code);
    }

    public function test_event_tanpa_marketing_tak_muncul(): void
    {
        // Order di-assign ke tim event tapi marketing belum di-assign → jangan muncul.
        $belumMarketing = $this->order($this->jkt, marketingId: null);
        $tim = $this->timEvent($this->jkt);
        $belumMarketing->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventIndex::class)
            ->assertDontSee($belumMarketing->booking_code);
    }

    public function test_tim_event_ditugaskan_bisa_buka_detail(): void
    {
        $order = $this->order($this->jkt);
        $tim = $this->timEvent($this->jkt);
        $order->timEvent()->attach($tim->id);

        $this->actingAs($tim)
            ->get(route('app.event.show', $order->id))
            ->assertOk()
            ->assertSee($order->booking_code);
    }

    public function test_tim_event_tak_ditugaskan_ditolak(): void
    {
        $order = $this->order($this->jkt);
        $tim = $this->timEvent($this->jkt); // punya role, tapi tak di-assign

        $this->actingAs($tim)
            ->get(route('app.event.show', $order->id))
            ->assertForbidden();
    }

    public function test_admin_bisa_buka_semua_event(): void
    {
        $order = $this->order($this->jkt);
        $admin = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $admin->assignRole('operasional');

        $this->actingAs($admin)
            ->get(route('app.event.show', $order->id))
            ->assertOk();
    }

    public function test_ste_dapat_diakses_tim_event(): void
    {
        $order = $this->order($this->jkt);
        $order->update(['konfirmasi_h2_at' => now()]); // STE terbit setelah H-2
        $tim = $this->timEvent($this->jkt);
        $order->timEvent()->attach($tim->id);

        $res = $this->actingAs($tim)->get(route('app.event.ste', $order->id));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }
}
