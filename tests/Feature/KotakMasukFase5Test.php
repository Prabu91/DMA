<?php

namespace Tests\Feature;

use App\Livewire\Booking\KotakMasuk;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KotakMasukFase5Test extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Cabang $bdg;

    private Sekolah $sekolahJkt;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $this->sekolahJkt = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function marketing(Cabang $cabang): User
    {
        $u = User::factory()->create(['cabang_id' => $cabang->id]);
        $u->assignRole('marketing');

        return $u;
    }

    private function orderSekolah(Cabang $cabang, ?int $marketingId = null): Order
    {
        return Order::create([
            'sekolah_id' => $this->sekolahJkt->id,
            'cabang_id' => $cabang->id,
            'marketing_id' => $marketingId,
            'sumber' => 'sekolah',
            'total' => 100000,
            'jumlah_siswa' => 20,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_kotak_masuk_hanya_order_sekolah_belum_ditugaskan(): void
    {
        $belum = $this->orderSekolah($this->jkt);
        $sudah = $this->orderSekolah($this->jkt, $this->marketing($this->jkt)->id);
        // Order jalur marketing (bukan sumber sekolah) — tak boleh muncul.
        $marketingOrder = Order::create([
            'sekolah_id' => $this->sekolahJkt->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => $this->marketing($this->jkt)->id, 'sumber' => 'marketing',
            'total' => 50000, 'jumlah_siswa' => 5, 'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->marketing($this->jkt));
        Livewire::test(KotakMasuk::class)
            ->assertSee('Booking #'.$belum->id)
            ->assertDontSee('Booking #'.$sudah->id)
            ->assertDontSee('Booking #'.$marketingOrder->id);
    }

    public function test_marketing_ambil_klaim_ke_diri_sendiri(): void
    {
        $order = $this->orderSekolah($this->jkt);
        $m = $this->marketing($this->jkt);

        Livewire::actingAs($m);
        Livewire::test(KotakMasuk::class)->call('ambil', $order->id)->assertSet('error', null);

        $this->assertSame($m->id, $order->fresh()->marketing_id);
    }

    public function test_klaim_atomik_dobel_hanya_satu_menang(): void
    {
        $order = $this->orderSekolah($this->jkt);
        $m1 = $this->marketing($this->jkt);
        $m2 = $this->marketing($this->jkt);

        Livewire::actingAs($m1);
        Livewire::test(KotakMasuk::class)->call('ambil', $order->id);
        $this->assertSame($m1->id, $order->fresh()->marketing_id);

        // m2 mencoba klaim order yang sama → gagal (tetap milik m1).
        Livewire::actingAs($m2);
        Livewire::test(KotakMasuk::class)
            ->call('ambil', $order->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'sudah diambil'));

        $this->assertSame($m1->id, $order->fresh()->marketing_id);
    }

    public function test_marketing_cabang_lain_tidak_melihat_dan_tidak_bisa_klaim(): void
    {
        $orderJkt = $this->orderSekolah($this->jkt);
        $mBdg = $this->marketing($this->bdg);

        Livewire::actingAs($mBdg);
        Livewire::test(KotakMasuk::class)
            ->assertDontSee('Booking #'.$orderJkt->id)
            ->call('ambil', $orderJkt->id); // scoped → tak ditemukan

        $this->assertNull($orderJkt->fresh()->marketing_id);
    }

    public function test_area_assign_ke_marketing(): void
    {
        $order = $this->orderSekolah($this->jkt);
        $m = $this->marketing($this->jkt);
        $area = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $area->assignRole('admin_sales');

        Livewire::actingAs($area);
        Livewire::test(KotakMasuk::class)
            ->set('pilihMarketing.'.$order->id, $m->id)
            ->call('tugaskan', $order->id)
            ->assertSet('error', null);

        $this->assertSame($m->id, $order->fresh()->marketing_id);
    }

    public function test_area_reassign_order_yang_sudah_ditugaskan(): void
    {
        $m1 = $this->marketing($this->jkt);
        $m2 = $this->marketing($this->jkt);
        $order = $this->orderSekolah($this->jkt, $m1->id);
        $area = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $area->assignRole('admin_sales');

        Livewire::actingAs($area);
        Livewire::test(KotakMasuk::class)
            ->set('tampil', 'ditugaskan')
            ->set('pilihMarketing.'.$order->id, $m2->id)
            ->call('reassign', $order->id)
            ->assertSet('error', null);

        $this->assertSame($m2->id, $order->fresh()->marketing_id);
    }

    public function test_marketing_tidak_bisa_assign_via_tugaskan(): void
    {
        $order = $this->orderSekolah($this->jkt);
        $m = $this->marketing($this->jkt);

        Livewire::actingAs($m);
        Livewire::test(KotakMasuk::class)
            ->set('pilihMarketing.'.$order->id, $m->id)
            ->call('tugaskan', $order->id)
            ->assertForbidden();
    }
}
