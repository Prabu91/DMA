<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventCompleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Cabang $cabang): Order
    {
        $sekolah = Sekolah::create([
            'id_sekolah' => 'SK-'.$cabang->id,
            'nama' => 'SD '.$cabang->nama,
            'cabang_id' => $cabang->id,
        ]);

        return Order::create([
            'booking_code' => 'BK-'.$cabang->id,
            'sekolah_id' => $sekolah->id,
            'cabang_id' => $cabang->id,
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('tim_event', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_anggota_tim_event_bisa_menandai_event_selesai(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang A', 'kode_area' => 'A']);
        $order = $this->makeOrder($cabang);

        $user = User::factory()->create(['cabang_id' => $cabang->id]);
        $user->assignRole('tim_event');
        $order->timEvent()->attach($user->id);

        $this->actingAs($user)
            ->post(route('events.complete', $order))
            ->assertRedirect();

        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->refresh()->event_status);
    }

    public function test_user_tidak_ditugaskan_ditolak(): void
    {
        $cabang = Cabang::create(['nama' => 'Cabang A', 'kode_area' => 'A']);
        $order = $this->makeOrder($cabang);

        $user = User::factory()->create(['cabang_id' => $cabang->id]);
        $user->assignRole('tim_event'); // punya role, tapi tidak di-assign ke order ini

        $this->actingAs($user)
            ->post(route('events.complete', $order))
            ->assertForbidden();

        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->refresh()->event_status);
    }
}
