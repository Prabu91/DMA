<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderCatatanTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cabang = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
    }

    private function staf(string $role): User
    {
        $u = User::factory()->create(['cabang_id' => $this->cabang->id, 'nama' => 'Staf '.$role]);
        $u->assignRole($role);

        return $u;
    }

    private function order(): Order
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD Uji', 'cabang_id' => $this->cabang->id]);
        $marketing = $this->staf('marketing');

        return Order::create([
            'booking_code' => 'UJI-CATATAN-1',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $marketing->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'baru',
            'tanggal_booking' => now(),
        ]);
    }

    public function test_staf_bisa_menambah_catatan(): void
    {
        $order = $this->order();
        $staf = $this->staf('admin_sales');

        Livewire::actingAs($staf)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Guru minta jadwal digeser ke siang.')
            ->call('tambahCatatan')
            ->assertHasNoErrors()
            ->assertSet('catatanBaru', '')   // kolom dikosongkan setelah terkirim
            ->assertSee('Guru minta jadwal digeser ke siang.');

        $this->assertDatabaseHas('order_catatan', [
            'order_id' => $order->id,
            'user_id' => $staf->id,
            'isi' => 'Guru minta jadwal digeser ke siang.',
        ]);
    }

    public function test_catatan_kosong_ditolak(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', '   ')
            ->call('tambahCatatan')
            ->assertHasErrors('catatanBaru');

        $this->assertSame(0, $order->catatan()->count());
    }

    public function test_semua_role_staf_bisa_menambah_catatan(): void
    {
        // "Semua bisa nambah catatan" — bukan hanya pemilik order.
        $order = $this->order();

        foreach (['marketing', 'operasional', 'admin_sales', 'super_admin'] as $role) {
            Livewire::actingAs($this->staf($role))
                ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
                ->set('catatanBaru', 'Catatan dari '.$role)
                ->call('tambahCatatan')
                ->assertHasNoErrors();
        }

        $this->assertSame(4, $order->catatan()->count());
    }

    public function test_catatan_tetap_bisa_ditulis_walau_order_terkunci(): void
    {
        // Penguncian melindungi data order; catatan justru paling dibutuhkan
        // saat ada persoalan di akhir.
        $order = $this->order();
        $order->update(['konfirmasi_hh_at' => now()]);

        $this->assertTrue($order->isLocked());

        Livewire::actingAs($this->staf('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Album kurang 2 pcs, menyusul.')
            ->call('tambahCatatan')
            ->assertHasNoErrors();

        $this->assertSame(1, $order->catatan()->count());
    }

    public function test_penulis_bisa_menghapus_catatannya_sendiri(): void
    {
        $order = $this->order();
        $staf = $this->staf('admin_sales');

        $comp = Livewire::actingAs($staf)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Salah tulis')
            ->call('tambahCatatan');

        $catatan = $order->catatan()->first();
        $comp->call('hapusCatatan', $catatan->id);

        $this->assertSame(0, $order->catatan()->count());
    }

    public function test_staf_lain_tak_bisa_menghapus_catatan_orang(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Punya orang lain')
            ->call('tambahCatatan');

        $catatan = $order->catatan()->first();

        Livewire::actingAs($this->staf('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('hapusCatatan', $catatan->id)
            ->assertForbidden();

        $this->assertSame(1, $order->catatan()->count());
    }

    public function test_super_admin_bisa_menghapus_catatan_siapa_pun(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->staf('marketing'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Catatan marketing')
            ->call('tambahCatatan');

        $catatan = $order->catatan()->first();

        Livewire::actingAs($this->staf('super_admin'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('hapusCatatan', $catatan->id);

        $this->assertSame(0, $order->catatan()->count());
    }

    public function test_sekolah_tidak_melihat_catatan_internal(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->staf('admin_sales'))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatanBaru', 'Jangan sampai terbaca guru.')
            ->call('tambahCatatan');

        $sekolah = $order->sekolah;

        Livewire::actingAs($sekolah, 'sekolah')
            ->test(OrderDetail::class, ['konteks' => 'sekolah', 'orderId' => $order->id])
            ->assertDontSee('Jangan sampai terbaca guru.');
    }
}
