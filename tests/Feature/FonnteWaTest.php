<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Notifications\FonnteService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * #4: OTP & konfirmasi dikirim via WhatsApp (Fonnte, unofficial).
 * Non-fatal: tanpa token, WA dilewati & alur tetap jalan (fallback portal).
 */
class FonnteWaTest extends TestCase
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
        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT',
            'pic_sekolah' => 'Bu Ani', 'no_telp_pic' => '081234567890',
            'cabang_id' => $this->jkt->id,
        ]);
    }

    public function test_normalisasi_nomor_ke_format_62(): void
    {
        $svc = new FonnteService;
        $this->assertSame('6281234567890', $svc->normalize('081234567890'));
        $this->assertSame('6281234567890', $svc->normalize('+62 812-3456-7890'));
        $this->assertSame('6281234567890', $svc->normalize('81234567890'));
        $this->assertNull($svc->normalize(''));
        $this->assertNull($svc->normalize(null));
        $this->assertNull($svc->normalize('123')); // terlalu pendek
    }

    private function timEvent(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('tim_event');

        return $u;
    }

    private function eventOrder(): Order
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
            'konfirmasi_hh_at' => now(), // OTP butuh data sekolah + Hari-H
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_otp_dikirim_via_wa_saat_token_diset(): void
    {
        config()->set('services.fonnte.token', 'test-token');
        Http::fake(['*' => Http::response(['status' => true, 'id' => ['x']], 200)]);

        $order = $this->eventOrder();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('generateOtp')
            ->assertHasNoErrors();

        $code = $order->refresh()->otp_code;
        $this->assertNotNull($code);

        Http::assertSent(function ($request) use ($code) {
            return str_contains($request->url(), 'fonnte.com')
                && $request['target'] === '6281234567890'
                && str_contains($request['message'], $code);
        });

        $this->assertDatabaseHas('order_activities', [
            'order_id' => $order->id, 'action' => 'otp_wa_terkirim',
        ]);
    }

    public function test_tanpa_token_wa_dilewati_alur_tetap_jalan(): void
    {
        config()->set('services.fonnte.token', null);
        Http::fake();

        $order = $this->eventOrder();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('generateOtp')
            ->assertHasNoErrors();

        // OTP tetap dibuat (fallback tampil di portal sekolah), tapi tak ada WA terkirim.
        $this->assertNotNull($order->refresh()->otp_code);
        Http::assertNothingSent();
    }

    public function test_konfirmasi_h7_kirim_wa_ke_sekolah(): void
    {
        config()->set('services.fonnte.token', 'test-token');
        config()->set('services.fonnte.kirim_konfirmasi', true); // saklar konfirmasi aktif
        Http::fake(['*' => Http::response(['status' => true], 200)]);

        $adminSales = User::factory()->create(); // terpusat → cabang_id null
        $adminSales->assignRole('admin_sales');

        $order = Order::create([
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'dp', // DP sudah masuk → H-7 boleh dikonfirmasi
            'tanggal_event' => now()->addDays(7)->toDateString(),
            'total' => 100000,
            'jumlah_siswa' => 20,
            'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($adminSales)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'h7');

        $this->assertNotNull($order->refresh()->konfirmasi_h7_at);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'H-7') && $r['target'] === '6281234567890');
    }
}
