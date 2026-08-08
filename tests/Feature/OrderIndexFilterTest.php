<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderIndex;
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

class OrderIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function marketing(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('marketing');

        return $u;
    }

    private function order(array $attr = []): Order
    {
        return Order::create(array_merge([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'total' => 100000,
            'tanggal_booking' => now(),
        ], $attr));
    }

    public function test_marketing_hanya_lihat_order_miliknya(): void
    {
        $mkt = $this->marketing();
        $milik = $this->order(['marketing_id' => $mkt->id]);
        $lain = $this->order(['marketing_id' => $this->marketing()->id]);
        $belum = $this->order(['marketing_id' => null]);

        Livewire::actingAs($mkt)
            ->test(OrderIndex::class)
            ->assertSee($milik->booking_code)
            ->assertDontSee($lain->booking_code)
            ->assertDontSee($belum->booking_code);
    }

    public function test_admin_lihat_semua(): void
    {
        $milikMkt = $this->order(['marketing_id' => $this->marketing()->id]);
        $belum = $this->order(['marketing_id' => null]);

        $admin = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $admin->assignRole('operasional');

        Livewire::actingAs($admin)
            ->test(OrderIndex::class)
            ->assertSee($milikMkt->booking_code)
            ->assertSee($belum->booking_code);
    }

    public function test_filter_status_event(): void
    {
        $admin = $this->admin();
        $dijadwalkan = $this->order(['event_status' => OrderStatus::EVENT_DIJADWALKAN]);
        $selesai = $this->order(['event_status' => OrderStatus::EVENT_SELESAI]);

        Livewire::actingAs($admin)
            ->test(OrderIndex::class)
            ->set('eventStatus', OrderStatus::EVENT_SELESAI)
            ->assertSee($selesai->booking_code)
            ->assertDontSee($dijadwalkan->booking_code);
    }

    public function test_filter_rentang_tanggal_event(): void
    {
        $admin = $this->admin();
        $dalam = $this->order(['tanggal_event' => now()->addDays(3)->toDateString()]);
        $luar = $this->order(['tanggal_event' => now()->addDays(40)->toDateString()]);

        Livewire::actingAs($admin)
            ->test(OrderIndex::class)
            ->set('dari', now()->toDateString())
            ->set('sampai', now()->addDays(10)->toDateString())
            ->assertSee($dalam->booking_code)
            ->assertDontSee($luar->booking_code);
    }

    public function test_filter_tahap_butuh_h7(): void
    {
        $admin = $this->admin();
        $butuh = $this->order(['tanggal_event' => now()->addDays(3)->toDateString()]); // dalam H-7, belum konfirmasi
        // event_status NULL harus tetap dianggap "belum selesai".
        $butuhNull = $this->order(['tanggal_event' => now()->addDays(1)->toDateString(), 'event_status' => null]);
        $jauh = $this->order(['tanggal_event' => now()->addDays(30)->toDateString()]); // belum masuk H-7
        $sudah = $this->order(['tanggal_event' => now()->addDays(2)->toDateString(), 'event_status' => OrderStatus::EVENT_SELESAI]);

        Livewire::actingAs($admin)
            ->test(OrderIndex::class)
            ->set('tahap', 'butuh_h7')
            ->assertSee($butuh->booking_code)
            ->assertSee($butuhNull->booking_code)
            ->assertDontSee($jauh->booking_code)
            ->assertDontSee($sudah->booking_code);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('operasional');

        return $u;
    }

    public function test_admin_filter_cabang_dan_hitung_per_cabang(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekolahBdg = Sekolah::create(['id_sekolah' => 'SKL-BDG-1', 'nama' => 'SD BDG', 'cabang_id' => $bdg->id]);

        $orderJkt = $this->order();
        $orderBdg = Order::create([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $sekolahBdg->id, 'cabang_id' => $bdg->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'total' => 100000, 'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(OrderIndex::class)
            ->assertSee($orderJkt->booking_code)
            ->assertSee($orderBdg->booking_code)
            ->set('cabangId', (string) $this->jkt->id)
            ->assertSee($orderJkt->booking_code)
            ->assertDontSee($orderBdg->booking_code);
    }
}
