<?php

namespace Tests\Feature;

use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Tampilan tabel & kalender pada board — padanan Table view dan Calendar view Trello. */
class KanbanTampilanTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kartu $lewat;

    private Kartu $nanti;

    private Kartu $tanpa;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->faris = User::factory()->create(['nama' => 'Faris', 'cabang_id' => $cabang->id]);
        $this->faris->assignRole('admin_sales');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $antre = $tata->tambahKolom($this->board, 'Antrian edit', $this->faris);
        $selesai = $tata->tambahKolom($this->board, 'Selesai', $this->faris);

        // 15 Juni 2026 dipakai sebagai patokan kalender.
        $this->lewat = $tata->tambahKartu($antre, 'SD Harapan', $this->faris, ['tenggat_pada' => '2026-06-10 09:00']);
        $this->nanti = $tata->tambahKartu($selesai, 'TK Pelita', $this->faris, ['tenggat_pada' => '2026-06-20 14:30']);
        $this->tanpa = $tata->tambahKartu($antre, 'SMP Nusantara', $this->faris);
    }

    private function papan(array $param = [])
    {
        return Livewire::actingAs($this->faris)->withQueryParams($param)->test(PapanBoard::class, ['board' => $this->board]);
    }

    public function test_tampilan_bawaan_papan_dan_bisa_diganti(): void
    {
        $this->papan()
            ->assertSet('tampilan', 'papan')
            ->assertSee('Antrian edit')
            ->call('gantiTampilan', 'tabel')
            ->assertSet('tampilan', 'tabel')
            ->assertSee('Checklist')
            ->call('gantiTampilan', 'kalender')
            ->assertSet('tampilan', 'kalender')
            ->call('gantiTampilan', 'ngawur')
            ->assertSet('tampilan', 'papan');
    }

    public function test_tampilan_dari_url(): void
    {
        $this->actingAs($this->faris)->get(route('kanban.board', $this->board).'?tampilan=tabel')
            ->assertOk()
            ->assertSee('Klik baris untuk membuka kartu.');
    }

    public function test_tabel_menampilkan_semua_kartu_dengan_list_dan_tenggat(): void
    {
        $tabel = $this->papan()->call('gantiTampilan', 'tabel');

        $tabel->assertSee('SD Harapan')->assertSee('TK Pelita')->assertSee('SMP Nusantara')
            ->assertSee('Antrian edit')->assertSee('10 Jun 2026');
    }

    public function test_tabel_bisa_diurutkan_dan_arahnya_dibalik(): void
    {
        $tabel = $this->papan()->call('gantiTampilan', 'tabel');

        $tabel->call('urutkan', 'judul')->assertSet('urutTabel', 'judul')->assertSet('arahTabel', 'asc');
        $this->assertSame(['SD Harapan', 'SMP Nusantara', 'TK Pelita'], $tabel->get('baris')->pluck('judul')->all());

        $tabel->call('urutkan', 'judul')->assertSet('arahTabel', 'desc');
        $this->assertSame(['TK Pelita', 'SMP Nusantara', 'SD Harapan'], $tabel->get('baris')->pluck('judul')->all());

        // Tenggat: yang tanpa tenggat selalu di belakang.
        $tabel->call('urutkan', 'tenggat');
        $this->assertSame(['SD Harapan', 'TK Pelita', 'SMP Nusantara'], $tabel->get('baris')->pluck('judul')->all());

        // Urutan list mengikuti posisi list di papan.
        $tabel->call('urutkan', 'list');
        $this->assertSame(['Antrian edit', 'Antrian edit', 'Selesai'], $tabel->get('baris')->pluck('kolom.nama')->all());

        $tabel->call('urutkan', 'ngawur')->assertSet('urutTabel', 'list');
    }

    /** Potongan HTML judul kartu di baris tabel (bukan di panel aktivitas). */
    private static function baris(string $judul): string
    {
        return 'hover:underline">'.$judul.'</button>';
    }

    public function test_tabel_ikut_penyaring_board(): void
    {
        $this->papan()->call('gantiTampilan', 'tabel')
            ->set('cari', 'pelita')
            ->assertSeeHtml(self::baris('TK Pelita'))
            ->assertDontSeeHtml(self::baris('SD Harapan'));

        $this->papan()->call('gantiTampilan', 'tabel')
            ->set('saringTenggat', 'tanpa')
            ->assertSeeHtml(self::baris('SMP Nusantara'))
            ->assertDontSeeHtml(self::baris('TK Pelita'));
    }

    public function test_klik_baris_tabel_membuka_kartu(): void
    {
        $this->papan()->call('gantiTampilan', 'tabel')
            ->call('bukaKartu', $this->nanti->id)
            ->assertSet('kartuId', $this->nanti->id);
    }

    public function test_kalender_menaruh_kartu_pada_tanggal_tenggat(): void
    {
        $kalender = $this->papan(['bulan' => '2026-06'])->call('gantiTampilan', 'kalender');

        $isi = $kalender->get('kalender');
        $this->assertSame(['SD Harapan'], $isi['2026-06-10']->pluck('judul')->all());
        $this->assertSame(['TK Pelita'], $isi['2026-06-20']->pluck('judul')->all());
        $kalender->assertSee('Juni 2026')->assertSee('1 kartu tanpa tenggat');
    }

    public function test_kalender_bisa_geser_bulan_dan_kembali_ke_bulan_ini(): void
    {
        $kalender = $this->papan(['bulan' => '2026-06'])->call('gantiTampilan', 'kalender');

        $kalender->call('geserBulan', 1)->assertSet('bulan', '2026-07')->assertSee('Juli 2026');
        $this->assertCount(0, $kalender->get('kalender'));

        $kalender->call('geserBulan', -1)->assertSet('bulan', '2026-06')->assertSee('Juni 2026');
        $kalender->call('bulanIni')->assertSet('bulan', now()->format('Y-m'));
    }

    public function test_bulan_ngawur_di_url_jatuh_ke_bulan_ini(): void
    {
        $this->papan(['bulan' => 'bukan-bulan'])->call('gantiTampilan', 'kalender')
            ->assertSee(now()->year);
    }

    public function test_kalender_hanya_menampilkan_kartu_bulan_yang_dilihat(): void
    {
        app(Tata::class)->tambahKartu($this->board->kolom()->first(), 'Bulan lain', $this->faris, ['tenggat_pada' => '2026-09-05 08:00']);

        $kalender = $this->papan(['bulan' => '2026-06'])->call('gantiTampilan', 'kalender');

        $this->assertSame(
            ['SD Harapan', 'TK Pelita'],
            $kalender->get('kalender')->flatten()->pluck('judul')->sort()->values()->all()
        );
    }
}
