<?php

namespace Tests\Feature;

use App\Livewire\Event\EventDetail;
use App\Mail\EventOtpMail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EventOtpTest extends TestCase
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

    private function order(bool $konfirmasi = true): Order
    {
        $o = Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->addDays(2)->toDateString(),
            'konfirmasi_lokasi_at' => $konfirmasi ? now() : null,
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);

        return $o;
    }

    public function test_generate_otp_wajib_konfirmasi_lokasi_dulu(): void
    {
        Mail::fake();
        $order = $this->order(konfirmasi: false);
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('generateOtp')
            ->assertHasErrors('otpInput');

        $this->assertNull($order->refresh()->otp_code);
        Mail::assertNothingSent();
    }

    public function test_generate_otp_membuat_kode_dan_kirim_email_guru(): void
    {
        Mail::fake();
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('generateOtp');

        $order->refresh();
        $this->assertNotNull($order->otp_code);
        $this->assertTrue($order->eventOtpActive());
        Mail::assertSent(EventOtpMail::class, fn ($m) => $m->hasTo('guru@sekolah.test'));
    }

    public function test_input_otp_benar_menyelesaikan_event(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);
        $code = $order->generateEventOtp();

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->set('otpInput', $code)
            ->call('selesaikanDenganOtp')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->event_status);
        $this->assertNull($order->otp_code);
        $this->assertNotNull($order->event_selesai_at); // waktu selesai tercatat
    }

    public function test_otp_salah_ditolak(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);
        $order->generateEventOtp();

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->set('otpInput', '000000')
            ->call('selesaikanDenganOtp')
            ->assertHasErrors('otpInput');

        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->refresh()->event_status);
    }

    public function test_otp_kedaluwarsa_ditolak(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);
        $order->update(['otp_code' => '123456', 'otp_expires' => now()->subMinute()]);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->set('otpInput', '123456')
            ->call('selesaikanDenganOtp')
            ->assertHasErrors('otpInput');

        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->refresh()->event_status);
    }

    public function test_otp_berlaku_30_menit(): void
    {
        $order = $this->order();
        $order->generateEventOtp();

        $order->refresh();
        $menit = now()->diffInMinutes($order->otp_expires);
        $this->assertGreaterThanOrEqual(29, $menit);
        $this->assertLessThanOrEqual(30, $menit);
    }

    public function test_kirim_ulang_terkena_cooldown(): void
    {
        Mail::fake();
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        $comp = Livewire::actingAs($tim)->test(EventDetail::class, ['orderId' => $order->id]);
        $comp->call('generateOtp'); // OTP pertama
        $kode1 = $order->refresh()->otp_code;
        $this->assertNotNull($kode1);

        // Kirim ulang langsung → kena cooldown 60 detik → ditolak, kode tak berubah.
        $comp->call('generateOtp')->assertHasErrors('otpInput');
        $this->assertSame($kode1, $order->refresh()->otp_code);
        Mail::assertSentCount(1); // hanya email OTP pertama
    }

    public function test_kirim_ulang_boleh_setelah_cooldown(): void
    {
        Mail::fake();
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);
        $order->generateEventOtp();
        $kode1 = $order->refresh()->otp_code;

        // Majukan waktu melewati cooldown (61 detik).
        $this->travel(61)->seconds();

        Livewire::actingAs($tim)->test(EventDetail::class, ['orderId' => $order->id])
            ->call('generateOtp')->assertHasNoErrors();

        $this->assertNotSame($kode1, $order->refresh()->otp_code); // kode baru
    }

    public function test_admin_override_selesai_tanpa_otp(): void
    {
        $order = $this->order();
        $admin = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $admin->assignRole('operasional');

        Livewire::actingAs($admin)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('selesaikanOverride');

        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->refresh()->event_status);
    }

    public function test_tim_event_tak_bisa_override(): void
    {
        $order = $this->order();
        $tim = $this->timEvent();
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->call('selesaikanOverride')
            ->assertStatus(403);

        $this->assertSame(OrderStatus::EVENT_DIJADWALKAN, $order->refresh()->event_status);
    }
}
