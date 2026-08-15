<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\AdminDashboard;
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

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Cabang $bdg;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['cabang_id' => null]);
        $u->assignRole('operasional');

        return $u;
    }

    private function order(Cabang $cabang, array $attr = []): Order
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-'.uniqid(), 'nama' => 'SD', 'cabang_id' => $cabang->id]);

        return Order::create(array_merge([
            'sekolah_id' => $sekolah->id,
            'cabang_id' => $cabang->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'total' => 100000,
            'tanggal_booking' => now(),
        ], $attr));
    }

    private function marketing(Cabang $cabang, string $nama): User
    {
        Role::findOrCreate('marketing', 'web');
        $u = User::factory()->create(['cabang_id' => $cabang->id, 'nama' => $nama, 'name' => $nama]);
        $u->assignRole('marketing');

        return $u;
    }

    public function test_admin_lihat_semua_cabang_dan_ringkasan(): void
    {
        $this->order($this->jkt);
        $this->order($this->jkt, ['status' => 'lunas', 'total' => 500000]);
        $this->order($this->bdg);

        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->assertSee('DMA Jakarta')
            ->assertSee('DMA Bandung')
            ->assertSee('JKT')  // kode area di card per-cabang
            ->assertSee('BDG');
    }

    public function test_filter_cabang_menyaring(): void
    {
        $this->order($this->jkt);
        $this->order($this->bdg);

        // Nama cabang tetap ada di dropdown; card per-cabang yang menyaring.
        // kode_area hanya muncul di card, jadi dipakai untuk cek.
        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->set('cabangId', (string) $this->jkt->id)
            ->assertSee('JKT')
            ->assertDontSee('BDG');
    }

    public function test_filter_tanggal_pengaruhi_kinerja(): void
    {
        $mA = $this->marketing($this->jkt, 'Marketing Alfa');
        $mB = $this->marketing($this->jkt, 'Marketing Beta');
        $this->order($this->jkt, ['marketing_id' => $mA->id, 'tanggal_booking' => now()]);
        $this->order($this->jkt, ['marketing_id' => $mB->id, 'tanggal_booking' => now()->subDays(20)]);

        // Rentang 3 hari terakhir → hanya order Alfa masuk → kinerja hanya Alfa.
        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->set('dari', now()->subDays(3)->toDateString())
            ->assertSee('Marketing Alfa')
            ->assertDontSee('Marketing Beta');
    }

    public function test_toggle_basis_event(): void
    {
        // Order masuk hari ini (marketing Alfa) tapi event bulan depan.
        $mA = $this->marketing($this->jkt, 'Marketing Alfa');
        $this->order($this->jkt, ['marketing_id' => $mA->id, 'tanggal_booking' => now(), 'tanggal_event' => now()->addDays(30)->toDateString()]);

        // basis=event + sampai minggu ini → order (event +30h) di luar rentang → kinerja kosong.
        Livewire::actingAs($this->admin())
            ->test(AdminDashboard::class)
            ->set('basis', 'event')
            ->set('sampai', now()->addDays(7)->toDateString())
            ->assertSee('Belum ada order dengan marketing')
            ->assertDontSee('Marketing Alfa');
    }

    public function test_admin_sales_terpusat_lihat_semua_cabang(): void
    {
        // admin_sales kini TERPUSAT → dashboard menampilkan semua cabang.
        $this->order($this->jkt);
        $this->order($this->bdg);

        $adminSales = User::factory()->create(); // terpusat → cabang_id null
        $adminSales->assignRole('admin_sales');

        Livewire::actingAs($adminSales)
            ->test(AdminDashboard::class)
            ->assertSee('DMA Jakarta')
            ->assertSee('DMA Bandung');
    }
}
