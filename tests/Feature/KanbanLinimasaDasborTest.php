<?php

namespace Tests\Feature;

use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kolom;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Tampilan Linimasa (batang per tanggal) dan Dasbor (ringkasan board). */
class KanbanLinimasaDasborTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private User $rizky;

    private Board $board;

    private Kolom $antre;

    private Kolom $edit;

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
        $this->rizky = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $cabang->id]);
        $this->rizky->assignRole('editor');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->board->anggota()->attach($this->rizky->id, ['peran' => 'anggota']);
        $this->antre = $tata->tambahKolom($this->board, 'Antrian edit', $this->faris);
        $this->edit = $tata->tambahKolom($this->board, 'Sedang diedit', $this->faris);
    }

    private function papan(array $param = [])
    {
        return Livewire::actingAs($this->faris)->withQueryParams($param)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private function kartu(Kolom $kolom, string $judul, array $atribut = [])
    {
        return app(Tata::class)->tambahKartu($kolom, $judul, $this->faris, $atribut);
    }

    // ---------------- Linimasa ----------------

    public function test_linimasa_hanya_memuat_kartu_bertanggal_di_rentangnya(): void
    {
        $pekan = now()->startOfWeek();
        $ada = $this->kartu($this->antre, 'Dalam rentang', ['tenggat_pada' => $pekan->copy()->addDays(3)->setTime(9, 0)]);
        $this->kartu($this->antre, 'Jauh di depan', ['tenggat_pada' => $pekan->copy()->addDays(90)]);
        $this->kartu($this->antre, 'Tanpa tanggal');

        $lini = $this->papan()->call('gantiTampilan', 'linimasa');

        $judul = $lini->get('linimasa')->flatten()->pluck('judul');
        $this->assertSame(['Dalam rentang'], $judul->all());
        $lini->assertSee('#'.$ada->id);
    }

    public function test_linimasa_memuat_kartu_yang_rentangnya_melewati_jendela(): void
    {
        $pekan = now()->startOfWeek();
        $this->kartu($this->antre, 'Proyek panjang', [
            'mulai_pada' => $pekan->copy()->subDays(30)->toDateString(),
            'tenggat_pada' => $pekan->copy()->addDays(60),
        ]);

        $this->assertSame(
            ['Proyek panjang'],
            $this->papan()->call('gantiTampilan', 'linimasa')->get('linimasa')->flatten()->pluck('judul')->all()
        );
    }

    public function test_linimasa_bisa_digeser_dan_kembali_ke_pekan_ini(): void
    {
        $lini = $this->papan()->call('gantiTampilan', 'linimasa');
        $awal = $lini->get('awalLinimasa');

        $lini->call('geserLinimasa', 2);
        $this->assertSame($awal->copy()->addWeeks(2)->toDateString(), $lini->get('awalLinimasa')->toDateString());

        $lini->call('pekanIni');
        $this->assertSame(now()->startOfWeek()->toDateString(), $lini->get('awalLinimasa')->toDateString());
    }

    public function test_awal_linimasa_selalu_jatuh_di_awal_pekan(): void
    {
        $lini = $this->papan(['sejak' => now()->startOfWeek()->addDays(3)->toDateString()])->call('gantiTampilan', 'linimasa');

        $this->assertSame(now()->startOfWeek()->toDateString(), $lini->get('awalLinimasa')->toDateString());
        $this->assertTrue($lini->get('awalLinimasa')->isMonday());
    }

    public function test_tanggal_ngawur_di_url_jatuh_ke_pekan_ini(): void
    {
        $this->papan(['sejak' => 'bukan-tanggal'])->call('gantiTampilan', 'linimasa')
            ->assertSee('Pekan ini');
    }

    public function test_linimasa_ikut_penyaring_board(): void
    {
        $pekan = now()->startOfWeek();
        $this->kartu($this->antre, 'SD Harapan', ['tenggat_pada' => $pekan->copy()->addDay()]);
        $this->kartu($this->edit, 'TK Pelita', ['tenggat_pada' => $pekan->copy()->addDays(2)]);

        $hasil = $this->papan()->call('gantiTampilan', 'linimasa')->set('cari', 'pelita')
            ->get('linimasa')->flatten()->pluck('judul');

        $this->assertSame(['TK Pelita'], $hasil->all());
    }

    // ---------------- Dasbor ----------------

    public function test_dasbor_menghitung_keadaan_tenggat(): void
    {
        $this->kartu($this->antre, 'Lewat', ['tenggat_pada' => now()->subDay()]);
        $this->kartu($this->antre, 'Minggu ini', ['tenggat_pada' => now()->addDays(2)]);
        $this->kartu($this->edit, 'Selesai', ['tenggat_pada' => now()->addDays(3), 'tenggat_selesai_at' => now()]);
        $this->kartu($this->edit, 'Tanpa tenggat');

        $d = $this->papan()->call('gantiTampilan', 'dasbor')->get('dasbor');

        $this->assertSame(4, $d['total']);
        $this->assertSame(1, $d['lewat']);
        $this->assertSame(1, $d['pekanIni']);
        $this->assertSame(1, $d['selesai']);
        $this->assertSame(1, $d['tanpaTenggat']);
        $this->assertSame(4, $d['tanpaAnggota']);
    }

    public function test_dasbor_menghitung_per_list_anggota_dan_label(): void
    {
        $label = $this->board->label()->first();
        $satu = $this->kartu($this->antre, 'Satu');
        $satu->anggota()->attach($this->rizky->id);
        $satu->label()->attach($label->id);
        $dua = $this->kartu($this->antre, 'Dua');
        $dua->anggota()->attach($this->rizky->id);
        $this->kartu($this->edit, 'Tiga');

        $dasbor = $this->papan()->call('gantiTampilan', 'dasbor');
        $d = $dasbor->get('dasbor');

        $this->assertSame(
            [['nama' => 'Antrian edit', 'jml' => 2], ['nama' => 'Sedang diedit', 'jml' => 1]],
            $d['perKolom']->all()
        );
        $this->assertSame('Rizky', $d['perAnggota']->first()->nama);
        $this->assertSame(2, (int) $d['perAnggota']->first()->jml);
        $this->assertSame(1, (int) $d['perLabel']->first()->jml);

        $dasbor->assertSee('Kartu per list')->assertSee('Kartu per anggota')->assertSee('Kartu per label');
    }

    public function test_dasbor_ikut_penyaring_board(): void
    {
        $this->kartu($this->antre, 'SD Harapan', ['tenggat_pada' => now()->subDay()]);
        $this->kartu($this->antre, 'TK Pelita');

        $d = $this->papan()->call('gantiTampilan', 'dasbor')->set('saringTenggat', 'lewat')->get('dasbor');

        $this->assertSame(1, $d['total']);
        $this->assertSame(1, $d['lewat']);
    }

    public function test_kartu_diarsipkan_tidak_dihitung(): void
    {
        $arsip = $this->kartu($this->antre, 'Arsip');
        $arsip->update(['diarsipkan_at' => now()]);
        $this->kartu($this->antre, 'Aktif');

        $this->assertSame(1, $this->papan()->call('gantiTampilan', 'dasbor')->get('dasbor')['total']);
    }

    public function test_pemilih_tampilan_memuat_lima_pilihan(): void
    {
        $this->papan()
            ->assertSee('Timeline')
            ->assertSee('Dashboard')
            ->call('gantiTampilan', 'linimasa')->assertSet('tampilan', 'linimasa')
            ->call('gantiTampilan', 'dasbor')->assertSet('tampilan', 'dasbor');
    }
}
