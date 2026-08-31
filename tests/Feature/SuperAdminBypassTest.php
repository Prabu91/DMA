<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Katalog\DesainIndex;
use App\Models\Cabang;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SuperAdminBypassTest extends TestCase
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

        $this->jkt = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-000001', 'nama' => 'SDN Uji', 'cabang_id' => $this->jkt->id]);
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole($role);

        return $u;
    }

    private function orderTerkunci(): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah', 'status' => 'dp', 'total' => 100000, 'tanggal_booking' => now(),
            'tanggal_event' => now()->addDays(5)->toDateString(),
            'konfirmasi_hh_at' => now(), // terkunci (Hari-H final)
        ]);
    }

    // ---------- B: super_admin bypass kunci ----------

    public function test_super_admin_bisa_ubah_jadwal_order_terkunci(): void
    {
        $order = $this->orderTerkunci();
        $this->assertTrue($order->isLocked());

        Livewire::actingAs($this->user('super_admin'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('tanggalEvent', now()->addDays(10)->toDateString())
            ->call('simpanJadwal');

        $this->assertSame(now()->addDays(10)->toDateString(), $order->refresh()->tanggal_event->toDateString());
    }

    public function test_non_super_admin_terblokir_ubah_order_terkunci(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->user('operasional'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('tanggalEvent', now()->addDays(10)->toDateString())
            ->call('simpanJadwal')
            ->assertStatus(423);
    }

    // ---------- C: OTP tampil di panel staf utk super_admin & admin_sales ----------

    public function test_super_admin_lihat_kode_otp_di_panel_staf(): void
    {
        $order = $this->orderTerkunci();
        $order->update(['otp_code' => '654321', 'otp_expires' => now()->addMinutes(20)]);

        Livewire::actingAs($this->user('super_admin'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertSee('654321');

        Livewire::actingAs($this->user('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertSee('654321');
    }

    public function test_marketing_tak_lihat_kode_otp(): void
    {
        $order = $this->orderTerkunci();
        $order->update(['otp_code' => '654321', 'otp_expires' => now()->addMinutes(20), 'marketing_id' => $this->user('marketing')->id]);

        Livewire::actingAs($this->user('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertDontSee('654321');
    }

    // ---------- A: hapus order (soft delete) + pulihkan + purge ----------

    public function test_super_admin_soft_delete_order(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->user('super_admin'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('hapusOrder');

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
        $this->assertNull(Order::find($order->id));                 // hilang dari query normal
        $this->assertNotNull(Order::onlyTrashed()->find($order->id)); // masih di sampah
    }

    public function test_non_super_admin_tak_bisa_hapus_order(): void
    {
        $order = $this->orderTerkunci();

        Livewire::actingAs($this->user('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('hapusOrder')
            ->assertStatus(403);

        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_super_admin_pulihkan_dan_hapus_permanen(): void
    {
        $order = $this->orderTerkunci();
        $order->delete();

        $comp = Livewire::actingAs($this->user('super_admin'))
            ->test(\App\Livewire\Booking\OrderIndex::class);

        $comp->call('pulihkan', $order->id);
        $this->assertNotSoftDeleted('orders', ['id' => $order->id]);

        // hapus permanen
        $order->refresh()->delete();
        $comp->call('hapusPermanen', $order->id);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_purge_command_hapus_sampah_lewat_retensi(): void
    {
        $lama = $this->orderTerkunci();
        $lama->delete();
        $lama->forceFill(['deleted_at' => now()->subDays(Order::TRASH_RETENTION_DAYS + 1)])->saveQuietly();

        $baru = $this->orderTerkunci();
        $baru->delete(); // baru dihapus → tak kena purge

        $this->artisan('orders:purge-trash')->assertSuccessful();

        $this->assertDatabaseMissing('orders', ['id' => $lama->id]);      // lewat retensi → hilang
        $this->assertNotNull(Order::onlyTrashed()->find($baru->id));      // masih di sampah
    }

    // ---------- D: force delete desain ----------

    private function desainDipakai(): array
    {
        $kat = Kategori::create(['nama' => 'Yearbook', 'pakai_desain' => true, 'grup' => 'yb']);
        $desain = Desain::create(['kategori_id' => $kat->id, 'kode' => 'DSN-1', 'orientasi' => 'portrait', 'status' => 'aktif', 'tahun_ajaran' => '2026/2027']);
        $order = Order::create([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah', 'status' => 'baru', 'total' => 1, 'tanggal_booking' => now(),
        ]);
        $item = $order->items()->create(['tipe_item' => 'desain', 'desain_id' => $desain->id, 'qty' => 1, 'harga' => 0, 'is_free' => false]);

        return [$desain, $item];
    }

    public function test_delete_biasa_terblokir_bila_desain_dipakai(): void
    {
        [$desain] = $this->desainDipakai();

        Livewire::actingAs($this->user('super_admin'))
            ->test(DesainIndex::class)
            ->call('delete', $desain->id);

        $this->assertDatabaseHas('desain', ['id' => $desain->id]); // tak terhapus
    }

    public function test_super_admin_force_delete_desain_melepas_referensi(): void
    {
        [$desain, $item] = $this->desainDipakai();

        Livewire::actingAs($this->user('super_admin'))
            ->test(DesainIndex::class)
            ->call('forceDelete', $desain->id);

        $this->assertDatabaseMissing('desain', ['id' => $desain->id]);
        $this->assertNull($item->refresh()->desain_id); // referensi dilepas, item tetap ada
    }

    public function test_non_super_admin_tak_bisa_force_delete(): void
    {
        [$desain] = $this->desainDipakai();

        Livewire::actingAs($this->user('operasional'))
            ->test(DesainIndex::class)
            ->call('forceDelete', $desain->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('desain', ['id' => $desain->id]);
    }
}
