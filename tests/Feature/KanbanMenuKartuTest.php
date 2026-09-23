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
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Menu cepat kartu di papan (padanan menu klik-kanan Trello) & cover dari kartu. */
class KanbanMenuKartuTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private User $rizky;

    private Board $board;

    private Kolom $todo;

    private Kolom $selesai;

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
        $this->rizky = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $cabang->id]);
        $this->rizky->assignRole('editor');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->board->anggota()->attach($this->rizky->id, ['peran' => 'anggota']);
        $this->todo = $tata->tambahKolom($this->board, 'Antrian', $this->faris);
        $this->selesai = $tata->tambahKolom($this->board, 'Selesai', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'RA MADANI', $this->faris);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    public function test_label_bisa_dipasang_dan_dilepas_dari_papan(): void
    {
        $label = $this->board->label()->first();

        $papan = $this->papan()->call('toggleLabelKartu', $this->kartu->id, $label->id);
        $this->assertTrue($this->kartu->label()->whereKey($label->id)->exists());

        $papan->call('toggleLabelKartu', $this->kartu->id, $label->id);
        $this->assertFalse($this->kartu->label()->whereKey($label->id)->exists());
    }

    public function test_anggota_bisa_ditugaskan_dari_papan(): void
    {
        $this->papan()->call('toggleAnggotaKartu', $this->kartu->id, $this->rizky->id);

        $this->assertTrue($this->kartu->anggota()->whereKey($this->rizky->id)->exists());
        $this->assertDatabaseHas('kanban_aktivitas', [
            'kartu_id' => $this->kartu->id,
            'aksi' => 'anggota_kartu_ditambah',
        ]);
    }

    public function test_orang_luar_board_tidak_bisa_ditugaskan(): void
    {
        $luar = User::factory()->create(['nama' => 'Bukan anggota', 'cabang_id' => $this->faris->cabang_id]);
        $luar->assignRole('tim_event');

        $this->papan()->call('toggleAnggotaKartu', $this->kartu->id, $luar->id)->assertForbidden();
    }

    public function test_cover_warna_dipasang_dan_dilepas(): void
    {
        $papan = $this->papan()->call('sampulKartu', $this->kartu->id, 'hijau');
        $this->assertSame('hijau', $this->kartu->fresh()->cover_warna);

        $papan->call('sampulKartu', $this->kartu->id, null);
        $this->assertNull($this->kartu->fresh()->cover_warna);
    }

    public function test_warna_cover_ngawur_ditolak(): void
    {
        $this->papan()->call('sampulKartu', $this->kartu->id, 'pelangi')->assertStatus(422);
    }

    public function test_tenggat_diatur_dan_dihapus_dari_papan(): void
    {
        $besok = now()->addDay()->format('Y-m-d');

        $papan = $this->papan()->call('tenggatKartu', $this->kartu->id, $besok);
        $this->assertSame($besok, $this->kartu->fresh()->tenggat_pada->format('Y-m-d'));

        $papan->call('tenggatKartu', $this->kartu->id, null);
        $this->assertNull($this->kartu->fresh()->tenggat_pada);
    }

    public function test_kartu_dipindahkan_ke_list_lain(): void
    {
        $this->papan()->call('pindahKartuKe', $this->kartu->id, $this->selesai->id);

        $this->assertSame($this->selesai->id, $this->kartu->fresh()->kolom_id);
    }

    public function test_copy_card_membuat_salinan_di_list_yang_sama(): void
    {
        $label = $this->board->label()->first();
        $this->kartu->label()->attach($label->id);

        $papan = $this->papan()->call('salinKartu', $this->kartu->id);

        $salinan = Kartu::where('judul', 'RA MADANI (copy)')->firstOrFail();
        $this->assertSame($this->todo->id, $salinan->kolom_id);
        $this->assertTrue($salinan->label()->whereKey($label->id)->exists());
        $papan->assertSet('kartuId', $salinan->id);
    }

    public function test_kartu_diarsipkan_dari_papan(): void
    {
        $this->papan()->call('arsipkanKartu', $this->kartu->id);

        $this->assertNotNull($this->kartu->fresh()->diarsipkan_at);
    }

    public function test_pengamat_tidak_boleh_memakai_menu_kartu(): void
    {
        $tamu = User::factory()->create(['nama' => 'Tamu', 'cabang_id' => $this->faris->cabang_id]);
        $tamu->assignRole('tim_event');

        Livewire::actingAs($tamu)->test(PapanBoard::class, ['board' => $this->board])
            ->call('arsipkanKartu', $this->kartu->id)->assertForbidden();
    }

    public function test_kartu_board_lain_tidak_bisa_disentuh(): void
    {
        $lain = app(Tata::class)->buatBoard('Board lain', 'hijau', 'workspace', $this->faris);
        $kolomLain = app(Tata::class)->tambahKolom($lain, 'List', $this->faris);
        $kartuLain = app(Tata::class)->tambahKartu($kolomLain, 'Bukan punya board ini', $this->faris);

        $this->expectException(ModelNotFoundException::class);
        $this->papan()->call('arsipkanKartu', $kartuLain->id);
    }

    public function test_menu_kartu_tampil_di_papan(): void
    {
        $this->papan()
            ->assertSee('Open card')
            ->assertSee('Change labels')
            ->assertSee('Change members')
            ->assertSee('Change cover')
            ->assertSee('Change dates')
            ->assertSee('Copy card')
            ->assertSee('Copy link')
            ->assertSee('Collapse list');
    }

    public function test_cover_bisa_diunggah_langsung_dari_kartu(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('berkasCover', UploadedFile::fake()->image('sampul.jpg', 1200, 800));

        $lampiran = Lampiran::firstOrFail();
        $this->assertSame($lampiran->id, $this->kartu->fresh()->cover_lampiran_id);
        Storage::disk('local')->assertExists($lampiran->path);
    }

    public function test_cover_menolak_berkas_yang_bukan_gambar(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('berkasCover', UploadedFile::fake()->create('rencana.pdf', 200))
            ->assertHasErrors('berkasCover')
            ->assertDispatched('toast');

        $this->assertSame(0, Lampiran::count());
    }
}
