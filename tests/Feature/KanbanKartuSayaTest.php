<?php

namespace Tests\Feature;

use App\Livewire\Kanban\KartuSaya;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\User;
use App\Services\Kanban\Kabar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Halaman "Kartu saya" + pencarian lintas board. */
class KanbanKartuSayaTest extends TestCase
{
    use RefreshDatabase;

    private User $shanty;

    private Board $board;

    private Kartu $ditugaskan;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->shanty = User::factory()->create(['nama' => 'Shanty', 'cabang_id' => $cabang->id]);
        $this->shanty->assignRole('marketing');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->shanty);
        $kolom = $tata->tambahKolom($this->board, 'To do', $this->shanty);

        $this->ditugaskan = $tata->tambahKartu($kolom, 'SD Harapan', $this->shanty, ['tenggat_pada' => now()->subDay()]);
        $this->ditugaskan->anggota()->attach($this->shanty->id);
        $tata->tambahKartu($kolom, 'SMP Nusantara', $this->shanty, ['deskripsi' => 'perlu revisi warna']);
    }

    private function halaman(array $param = [])
    {
        return Livewire::actingAs($this->shanty)->withQueryParams($param)->test(KartuSaya::class);
    }

    public function test_tab_ditugaskan_ke_saya(): void
    {
        $this->halaman()
            ->assertSee('SD Harapan')
            ->assertDontSee('SMP Nusantara');
    }

    public function test_tab_saya_ikuti(): void
    {
        // Pembuat kartu otomatis mengikuti keduanya.
        $this->halaman()->call('gantiTab', 'ikuti')
            ->assertSee('SD Harapan')
            ->assertSee('SMP Nusantara');

        app(Kabar::class)->berhentiIkut($this->ditugaskan, $this->shanty);
        $this->halaman()->call('gantiTab', 'ikuti')->assertDontSee('SD Harapan');
    }

    public function test_pencarian_lintas_board_termasuk_deskripsi(): void
    {
        $lain = app(Tata::class);
        $board2 = $lain->buatBoard('Desain', 'ungu', 'workspace', $this->shanty);
        $lain->tambahKartu($lain->tambahKolom($board2, 'Masuk', $this->shanty), 'TK Pelita', $this->shanty);

        $this->halaman()->call('gantiTab', 'semua')->set('cari', 'pelita')
            ->assertSee('TK Pelita')->assertDontSee('SD Harapan');

        $this->halaman()->call('gantiTab', 'semua')->set('cari', 'revisi warna')
            ->assertSee('SMP Nusantara');
    }

    public function test_saring_board_dan_tenggat(): void
    {
        $halaman = $this->halaman()->call('gantiTab', 'semua');

        $halaman->set('saringTenggat', 'lewat')->assertSee('SD Harapan')->assertDontSee('SMP Nusantara');
        $halaman->set('saringTenggat', 'tanpa')->assertSee('SMP Nusantara')->assertDontSee('SD Harapan');

        $halaman->set('saringTenggat', '')->set('boardId', $this->board->id)->assertSee('SD Harapan');
        $halaman->call('bersihkan')->assertSet('boardId', null)->assertSee('SD Harapan');
    }

    public function test_kartu_board_privat_orang_lain_tidak_muncul(): void
    {
        $lain = User::factory()->create(['nama' => 'Dodi']);
        $lain->assignRole('editor');
        $tata = app(Tata::class);
        $privat = $tata->buatBoard('Rahasia', 'merah', 'privat', $lain);
        $tata->tambahKartu($tata->tambahKolom($privat, 'Masuk', $lain), 'Kartu rahasia', $lain);

        $this->halaman()->call('gantiTab', 'semua')->assertDontSee('Kartu rahasia');

        // Admin pusat boleh melihat semuanya.
        $admin = User::factory()->create(['nama' => 'Ops']);
        $admin->assignRole('operasional');
        Livewire::actingAs($admin)->test(KartuSaya::class)->call('gantiTab', 'semua')->assertSee('Kartu rahasia');
    }

    public function test_kartu_diarsipkan_hanya_muncul_bila_diminta(): void
    {
        $this->ditugaskan->update(['diarsipkan_at' => now()]);

        $this->halaman()->assertDontSee('SD Harapan')
            ->set('termasukArsip', true)->assertSee('SD Harapan')->assertSee('diarsipkan');
    }

    public function test_halaman_bisa_dibuka_dan_kotak_cari_global_mengisi_pencarian(): void
    {
        $this->actingAs($this->shanty)->get(route('kanban.kartu-saya').'?q=nusantara&tab=semua')
            ->assertOk()
            ->assertSee('SMP Nusantara')
            ->assertDontSee('SD Harapan');
    }

    public function test_hasil_pencarian_menyebut_board_dan_list_kartunya(): void
    {
        $this->halaman(['tab' => 'semua', 'q' => 'harapan'])
            ->assertSee('SD Harapan')
            ->assertSee('5. Editing')
            ->assertSee('To do');
    }

    public function test_kotak_cari_di_navbar_mencari_ke_semua_board(): void
    {
        // Kotak cari di kepala halaman selalu membawa tab "semua".
        $this->actingAs($this->shanty)->get(route('kanban.beranda'))
            ->assertOk()
            ->assertSeeHtml('<input type="hidden" name="tab" value="semua">');
    }
}
