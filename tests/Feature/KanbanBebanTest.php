<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Lampiran;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Penahan beban: kartu dimuat bertahap, tabel per halaman, gambar punya versi kecil. */
class KanbanBebanTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kolom $todo;

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
        $this->todo = $tata->tambahKolom($this->board, 'To do', $this->faris);
    }

    private function papan(array $param = [])
    {
        return Livewire::actingAs($this->faris)->withQueryParams($param)->test(PapanBoard::class, ['board' => $this->board]);
    }

    /** Potongan HTML judul kartu di baris tabel (bukan di panel aktivitas). */
    private static function baris(string $judul): string
    {
        return 'hover:underline">'.$judul.'</button>';
    }

    private function isiKartu(int $jumlah): void
    {
        $tata = app(Tata::class);
        for ($i = 1; $i <= $jumlah; $i++) {
            $tata->tambahKartu($this->todo, 'Kartu '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), $this->faris);
        }
    }

    public function test_papan_hanya_memuat_sebagian_kartu_dan_bisa_dimuat_lagi(): void
    {
        $this->isiKartu(60);

        $papan = $this->papan();
        $kolom = $papan->get('kolom')->first();

        $this->assertSame(PapanBoard::BATAS_AWAL, $kolom->kartu->count(), 'hanya sebagian yang dimuat');
        $this->assertSame(60, $kolom->jumlah_kartu, 'jumlah sebenarnya tetap terlihat');
        $papan->assertSee('kartu lagi');

        $papan->call('muatLagi', $this->todo->id);
        $this->assertSame(60, $papan->get('kolom')->first()->kartu->count(), 'sisanya ikut termuat');
    }

    public function test_kartu_paling_atas_yang_dimuat_lebih_dulu(): void
    {
        $this->isiKartu(30);

        $judul = $this->papan()->get('kolom')->first()->kartu->pluck('judul');

        $this->assertSame('Kartu 001', $judul->first());
        $this->assertSame('Kartu 025', $judul->last());
    }

    public function test_batas_berlaku_per_list(): void
    {
        $tata = app(Tata::class);
        $lain = $tata->tambahKolom($this->board, 'Selesai', $this->faris);
        $this->isiKartu(30);
        for ($i = 1; $i <= 30; $i++) {
            $tata->tambahKartu($lain, 'Lain '.$i, $this->faris);
        }

        $papan = $this->papan()->call('muatLagi', $this->todo->id);
        $kolom = $papan->get('kolom');

        $this->assertSame(30, $kolom->firstWhere('id', $this->todo->id)->kartu->count(), 'list yang diklik ikut bertambah');
        $this->assertSame(PapanBoard::BATAS_AWAL, $kolom->firstWhere('id', $lain->id)->kartu->count(), 'list lain tidak ikut');
    }

    public function test_pembatasan_menghormati_saringan(): void
    {
        $this->isiKartu(30);

        $papan = $this->papan()->set('cari', 'Kartu 00');
        $kolom = $papan->get('kolom')->first();

        $this->assertSame(9, $kolom->jumlah_kartu, 'hanya kartu 001-009 yang cocok');
        $this->assertSame(9, $kolom->kartu->count());
        $papan->assertDontSee('kartu lagi');
    }

    public function test_tabel_dipecah_per_halaman(): void
    {
        $this->isiKartu(30);

        $tabel = $this->papan()->call('gantiTampilan', 'tabel');

        $this->assertSame(PapanBoard::BATAS_TABEL, $tabel->get('baris')->count());
        $this->assertSame(30, $tabel->get('baris')->total());
        $tabel->assertSeeHtml(self::baris('Kartu 001'))->assertDontSeeHtml(self::baris('Kartu 030'));

        $tabel->call('gotoPage', 2)->assertSeeHtml(self::baris('Kartu 030'));
    }

    public function test_tabel_punya_kotak_cari_sendiri(): void
    {
        $this->isiKartu(3);

        $this->papan()->call('gantiTampilan', 'tabel')
            ->assertSee('Cari judul kartu di board ini')
            ->set('cari', 'Kartu 002')
            ->assertSeeHtml(self::baris('Kartu 002'))
            ->assertDontSeeHtml(self::baris('Kartu 001'));
    }

    public function test_gambar_lampiran_punya_versi_kecil(): void
    {
        Storage::fake('local');
        $kartu = app(Tata::class)->tambahKartu($this->todo, 'SD Harapan', $this->faris);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('berkas', [UploadedFile::fake()->image('foto.jpg', 1600, 1200)]);

        $lampiran = Lampiran::firstOrFail();
        $this->assertNotNull($lampiran->thumb_path, 'gambar besar dikecilkan');
        $this->assertSame($lampiran->thumb_path, $lampiran->pathKecil());
        Storage::disk('local')->assertExists($lampiran->thumb_path);
        $this->assertLessThan(
            Storage::disk('local')->size($lampiran->path),
            Storage::disk('local')->size($lampiran->thumb_path),
        );

        $this->actingAs($this->faris)->get(route('kanban.lampiran', ['lampiran' => $lampiran, 'kecil' => 1]))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=604800, private');
    }

    public function test_gambar_kecil_tidak_digandakan(): void
    {
        Storage::fake('local');
        $kartu = app(Tata::class)->tambahKartu($this->todo, 'SD Harapan', $this->faris);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('berkas', [UploadedFile::fake()->image('kecil.jpg', 200, 150)]);

        $lampiran = Lampiran::firstOrFail();
        $this->assertNull($lampiran->thumb_path);
        $this->assertSame($lampiran->path, $lampiran->pathKecil(), 'yang asli sudah cukup kecil');
    }

    public function test_hapus_lampiran_ikut_membuang_versi_kecil(): void
    {
        Storage::fake('local');
        $kartu = app(Tata::class)->tambahKartu($this->todo, 'SD Harapan', $this->faris);

        $detail = fn () => Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $kartu->id]);
        $detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 1600, 1200)]);
        $lampiran = Lampiran::firstOrFail();

        $detail()->call('hapusLampiran', $lampiran->id);

        Storage::disk('local')->assertMissing($lampiran->path);
        Storage::disk('local')->assertMissing($lampiran->thumb_path);
    }
}
