<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Lampiran;
use App\Models\User;
use App\Services\Kanban\Gambar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Unggahan yang gagal harus terdengar — bukan diam-diam batal. */
class KanbanUnggahGagalTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kolom $todo;

    private Kartu $kartu;

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
        $this->board = $tata->buatBoard('A. 1 ORDER', 'biru', 'workspace', $this->faris);
        $this->todo = $tata->tambahKolom($this->board, 'Antrian', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'RA MADANI', $this->faris);
    }

    private function detail()
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    public function test_berkas_kebesaran_memunculkan_kabar_dan_pesan_galat(): void
    {
        Storage::fake('local');
        $maks = (int) config('kanban.maks_lampiran_kb');

        $this->detail()
            ->set('berkas', [UploadedFile::fake()->create('mentahan.psd', $maks + 1024)])
            ->assertHasErrors('berkas.0')
            ->assertDispatched('toast');

        $this->assertSame(0, Lampiran::count());
    }

    public function test_lampiran_yang_gagal_disimpan_dilaporkan(): void
    {
        Storage::fake('local');
        $this->mock(Gambar::class)->shouldReceive('kecilkan')->andThrow(new RuntimeException('GD mati'));
        // Berkas tetap tersimpan; yang gagal hanya versi kecilnya.
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 1600, 1200)])
            ->assertNotDispatched('toast');

        $lampiran = Lampiran::firstOrFail();
        $this->assertNull($lampiran->thumb_path, 'versi kecil gagal, lampirannya tetap ada');
        Storage::disk('local')->assertExists($lampiran->path);
    }

    public function test_kegagalan_menyimpan_berkas_memunculkan_kabar(): void
    {
        Storage::fake('local');
        // Tiruan gangguan saat menyimpan baris lampiran (basis data penuh, dll).
        Lampiran::creating(fn () => throw new RuntimeException('gagal menyimpan'));

        $this->detail()->set('berkas', [UploadedFile::fake()->image('gagal.jpg', 400, 300)])
            ->assertDispatched('toast');

        $this->assertSame(0, Lampiran::count());
    }

    public function test_latar_board_yang_gagal_memunculkan_kabar(): void
    {
        Storage::fake('local');
        $this->mock(Gambar::class)->shouldReceive('kecilkan')->andThrow(new RuntimeException('GD mati'));

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->set('latar', UploadedFile::fake()->image('latar.jpg', 2400, 1600))
            ->assertDispatched('toast');

        $this->assertNull($this->board->fresh()->latar_path);
    }

    public function test_gambar_raksasa_dilewati_bukan_mematikan_php(): void
    {
        $gambar = app(Gambar::class);

        // 108 MP butuh ratusan MB saat dibongkar GD — dilewati, tidak dipaksakan.
        $this->assertFalse($gambar->siapkanMemori(12000, 9000, 5_000_000));
        $this->assertTrue($gambar->siapkanMemori(2000, 1500, 500_000));
    }

    public function test_keterangan_berkas_tercatat_lengkap(): void
    {
        Storage::fake('local');

        $this->detail()->set('berkas', [UploadedFile::fake()->image('denah.jpg', 900, 600)]);

        $lampiran = Lampiran::firstOrFail();
        $this->assertSame('denah.jpg', $lampiran->nama);
        $this->assertSame('image/jpeg', $lampiran->mime);
        // Ukuran dibaca sebelum berkas dipindahkan; kalau sesudahnya, nilainya hilang.
        $this->assertSame(Storage::disk('local')->size($lampiran->path), $lampiran->ukuran);
    }

    public function test_batas_ukuran_disebut_di_kartu(): void
    {
        $mb = round((int) config('kanban.maks_lampiran_kb') / 1024);

        $this->detail()->assertSee('Maksimal '.$mb.' MB per berkas');
    }
}
