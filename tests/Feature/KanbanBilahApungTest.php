<?php

namespace Tests\Feature;

use App\Livewire\Kanban\BilahApung;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Bilah mengambang: tombol Board & panel Switch boards. */
class KanbanBilahApungTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $editing;

    private Board $desain;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->faris = User::factory()->create(['nama' => 'Faris', 'cabang_id' => $cabang->id]);
        $this->faris->assignRole('editor');

        $tata = app(Tata::class);
        $this->editing = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->desain = $tata->buatBoard('6. Desain', 'hijau', 'workspace', $this->faris);
    }

    private function bilah()
    {
        return Livewire::actingAs($this->faris)->test(BilahApung::class);
    }

    public function test_bilah_tampil_di_halaman_kanban(): void
    {
        $this->actingAs($this->faris)->get(route('kanban.beranda'))
            ->assertOk()
            ->assertSee('Switch boards');
    }

    public function test_tombol_board_menunjuk_board_terakhir_dibuka(): void
    {
        DB::table('kanban_kunjungan')->insert([
            ['user_id' => $this->faris->id, 'board_id' => $this->editing->id, 'dibuka_at' => now()->subHour()],
            ['user_id' => $this->faris->id, 'board_id' => $this->desain->id, 'dibuka_at' => now()],
        ]);

        $this->assertSame($this->desain->id, $this->bilah()->get('boardTujuan')->id);
    }

    public function test_tombol_board_mati_saat_belum_ada_board_dibuka(): void
    {
        $this->bilah()
            ->assertSet('boardId', null)
            ->assertSee('No board opened yet');

        $this->assertNull($this->bilah()->get('boardTujuan'));
    }

    public function test_panel_hanya_memuat_board_setelah_dibuka(): void
    {
        $bilah = $this->bilah();

        $this->assertSame([], $bilah->get('kelompok'));

        $bilah->call('togglePanel')->assertSet('buka', true)
            ->assertSee('5. Editing')->assertSee('6. Desain')->assertSee('See all boards');

        $bilah->call('togglePanel')->assertSet('buka', false)->assertDontSee('See all boards');
    }

    public function test_panel_punya_tampilan_grid_dan_daftar(): void
    {
        $this->bilah()->call('togglePanel')
            ->assertSeeHtml('aria-label="Grid view"')
            ->assertSeeHtml('aria-label="List view"')
            ->assertSeeHtml("localStorage.getItem('kanban:tata-board')");
    }

    public function test_panel_bisa_dicari(): void
    {
        $bilah = $this->bilah()->call('togglePanel')->set('cari', 'desain');

        $judul = collect($bilah->get('kelompok'))->pluck('judul')->all();
        $this->assertSame(['Search results'], $judul);
        $bilah->assertSee('6. Desain')->assertDontSee('5. Editing');
    }

    public function test_pencarian_direset_saat_panel_ditutup(): void
    {
        $this->bilah()->call('togglePanel')->set('cari', 'desain')
            ->call('tutupPanel')->assertSet('buka', false)->assertSet('cari', '');
    }

    public function test_board_privat_orang_lain_tidak_muncul(): void
    {
        $lain = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $this->faris->cabang_id]);
        $lain->assignRole('marketing');
        app(Tata::class)->buatBoard('Rahasia Rizky', 'merah', 'privat', $lain);

        Livewire::actingAs($lain)->test(BilahApung::class)->call('togglePanel')->assertSee('Rahasia Rizky');

        $this->bilah()->call('togglePanel')->assertDontSee('Rahasia Rizky');
    }

    public function test_board_diarsipkan_tidak_muncul_di_panel(): void
    {
        $this->desain->update(['diarsipkan_at' => now()]);

        $this->bilah()->call('togglePanel')->assertSee('5. Editing')->assertDontSee('6. Desain');
    }
}
