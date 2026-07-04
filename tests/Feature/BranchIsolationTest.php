<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabangA;

    private Cabang $cabangB;

    private Sekolah $sekolahA;

    private Sekolah $sekolahB;

    private Order $orderA;

    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        // Role yang dipakai test.
        foreach (['super_admin', 'operasional', 'marketing'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Dua cabang beserta data masing-masing (dibuat tanpa user login,
        // sehingga CabangScope tidak memfilter proses seeding ini).
        $this->cabangA = Cabang::create(['nama' => 'Cabang A', 'kode_area' => 'A']);
        $this->cabangB = Cabang::create(['nama' => 'Cabang B', 'kode_area' => 'B']);

        $this->sekolahA = Sekolah::create([
            'id_sekolah' => 'SKA-001',
            'nama' => 'SD A',
            'cabang_id' => $this->cabangA->id,
        ]);
        $this->sekolahB = Sekolah::create([
            'id_sekolah' => 'SKB-001',
            'nama' => 'SD B',
            'cabang_id' => $this->cabangB->id,
        ]);

        $this->orderA = Order::create([
            'booking_code' => 'BKA-001',
            'sekolah_id' => $this->sekolahA->id,
            'cabang_id' => $this->cabangA->id,
        ]);
        $this->orderB = Order::create([
            'booking_code' => 'BKB-001',
            'sekolah_id' => $this->sekolahB->id,
            'cabang_id' => $this->cabangB->id,
        ]);
    }

    private function userForCabang(?int $cabangId, string $role): User
    {
        $user = User::factory()->create(['cabang_id' => $cabangId]);
        $user->assignRole($role);

        return $user;
    }

    public function test_user_cabang_a_tidak_bisa_mengakses_data_cabang_b(): void
    {
        $userA = $this->userForCabang($this->cabangA->id, 'marketing');
        $this->actingAs($userA);

        // Hanya melihat data cabang sendiri.
        $this->assertSame(1, Order::count());
        $this->assertSame(1, Sekolah::count());

        // Data cabang sendiri tetap bisa diakses.
        $this->assertNotNull(Order::find($this->orderA->id));
        $this->assertNotNull(Sekolah::find($this->sekolahA->id));

        // Akses lintas-cabang via id langsung => null (diblok CabangScope).
        $this->assertNull(Order::find($this->orderB->id));
        $this->assertNull(Sekolah::find($this->sekolahB->id));

        // Policy juga menolak melihat order cabang lain.
        // (find() dibypass scope agar objeknya ada untuk diuji policy-nya.)
        $orderB = Order::withoutGlobalScopes()->find($this->orderB->id);
        $this->assertFalse($userA->can('view', $orderB));
    }

    public function test_super_admin_bisa_mengakses_semua_cabang(): void
    {
        $super = $this->userForCabang(null, 'super_admin');
        $this->actingAs($super);

        $this->assertSame(2, Order::count());
        $this->assertSame(2, Sekolah::count());
        $this->assertNotNull(Order::find($this->orderA->id));
        $this->assertNotNull(Order::find($this->orderB->id));

        $orderB = Order::withoutGlobalScopes()->find($this->orderB->id);
        $this->assertTrue($super->can('view', $orderB));
    }

    public function test_operasional_juga_melihat_semua_cabang(): void
    {
        $ops = $this->userForCabang(null, 'operasional');
        $this->actingAs($ops);

        $this->assertSame(2, Order::count());
        $this->assertSame(2, Sekolah::count());
        $this->assertNotNull(Order::find($this->orderB->id));
    }
}
