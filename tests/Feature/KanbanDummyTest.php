<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\User;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Perintah pengisi data contoh — dipakai untuk mencoba kanban saat datanya banyak. */
class KanbanDummyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        User::factory()->create(['nama' => 'Faris', 'cabang_id' => $cabang->id])->assignRole('admin_sales');
    }

    public function test_data_contoh_dibuat_lalu_bisa_dibuang_lagi(): void
    {
        $this->artisan('kanban:dummy --board=2 --kartu=40')->assertSuccessful();

        $board = Board::where('deskripsi', 'like', '%[contoh]%')->get();
        $this->assertCount(2, $board);
        $this->assertGreaterThan(0, Kolom::whereIn('board_id', $board->pluck('id'))->count());
        $this->assertGreaterThanOrEqual(40, Kartu::whereIn('board_id', $board->pluck('id'))->count());

        $this->artisan('kanban:dummy --hapus')->assertSuccessful();

        $this->assertSame(0, Board::where('deskripsi', 'like', '%[contoh]%')->count());
        $this->assertSame(0, Kartu::count(), 'kartu ikut terhapus bersama boardnya');
    }

    public function test_board_asli_tidak_ikut_terhapus(): void
    {
        $order = Board::order();

        $this->artisan('kanban:dummy --board=1 --kartu=10')->assertSuccessful();
        $this->artisan('kanban:dummy --hapus')->assertSuccessful();

        $this->assertNotNull($order->fresh(), 'board Order bukan data contoh');
    }
}
