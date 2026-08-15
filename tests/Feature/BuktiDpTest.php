<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fase E: upload foto bukti bayar DP (marketing/admin & tim event).
 */
class BuktiDpTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function order(): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'total' => 100000,
            'tanggal_booking' => now(),
        ]);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_marketing_upload_bukti_dp(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->user('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('buktiDp', UploadedFile::fake()->image('dp.jpg'))
            ->call('uploadBuktiDp')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertNotNull($order->bukti_dp_path);
        Storage::disk('public')->assertExists($order->bukti_dp_path);
        $this->assertDatabaseHas('order_activities', ['order_id' => $order->id, 'action' => 'bukti_dp']);
    }

    public function test_non_image_ditolak(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->user('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('buktiDp', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
            ->call('uploadBuktiDp')
            ->assertHasErrors('buktiDp');

        $this->assertNull($order->refresh()->bukti_dp_path);
    }

    public function test_tim_event_upload_bukti_dp(): void
    {
        $order = $this->order();
        $tim = $this->user('tim_event');
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->set('buktiDp', UploadedFile::fake()->image('dp.png'))
            ->call('uploadBuktiDp');

        $this->assertNotNull($order->refresh()->bukti_dp_path);
        Storage::disk('public')->assertExists($order->bukti_dp_path);
    }

    public function test_ganti_bukti_hapus_file_lama(): void
    {
        $order = $this->order();
        $mkt = $this->user('marketing');

        $comp = Livewire::actingAs($mkt)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('buktiDp', UploadedFile::fake()->image('a.jpg'))
            ->call('uploadBuktiDp');
        $lama = $order->refresh()->bukti_dp_path;

        $comp->set('buktiDp', UploadedFile::fake()->image('b.jpg'))
            ->call('uploadBuktiDp');
        $baru = $order->refresh()->bukti_dp_path;

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }
}
