<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
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

/** Lampiran tautan, seret item checklist, ekspor CSV, dan aktivitas bertahap. */
class KanbanLampiranEksporTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kartu $kartu;

    private Checklist $checklist;

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
        $kolom = $tata->tambahKolom($this->board, 'To do', $this->faris);
        $this->kartu = $tata->tambahKartu($kolom, 'SD Harapan', $this->faris, ['tenggat_pada' => '2026-10-02 09:30']);

        $this->checklist = Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'QC', 'posisi' => 1]);
        foreach (['Satu', 'Dua', 'Tiga'] as $i => $teks) {
            ChecklistItem::create(['checklist_id' => $this->checklist->id, 'teks' => $teks, 'posisi' => ($i + 1) * 65536]);
        }
    }

    private function detail()
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    private function urutanItem(): array
    {
        return $this->checklist->item()->pluck('teks')->all();
    }

    // ---------------- Lampiran tautan ----------------

    public function test_lampirkan_tautan(): void
    {
        $this->detail()
            ->set('tautanUrl', 'https://drive.google.com/folder/abc')
            ->set('tautanNama', 'Folder hasil edit')
            ->call('tambahTautan')
            ->assertHasNoErrors()
            ->assertSee('Folder hasil edit');

        $lampiran = Lampiran::firstOrFail();
        $this->assertTrue($lampiran->isTautan());
        $this->assertFalse($lampiran->isGambar());
        $this->assertSame('TAUTAN', $lampiran->ekstensi());
        $this->assertNull($lampiran->path);
    }

    public function test_tautan_tanpa_nama_memakai_alamatnya(): void
    {
        $this->detail()->set('tautanUrl', 'https://8mataair.com/panduan')->call('tambahTautan');

        $this->assertSame('https://8mataair.com/panduan', Lampiran::firstOrFail()->nama);
    }

    public function test_tautan_harus_alamat_web_yang_sah(): void
    {
        $this->detail()->set('tautanUrl', '')->call('tambahTautan')->assertHasErrors('tautanUrl');
        $this->detail()->set('tautanUrl', 'bukan tautan')->call('tambahTautan')->assertHasErrors('tautanUrl');
        $this->detail()->set('tautanUrl', 'javascript:alert(1)')->call('tambahTautan')->assertHasErrors('tautanUrl');

        $this->assertSame(0, Lampiran::count());
    }

    public function test_membuka_lampiran_tautan_mengarahkan_ke_alamatnya(): void
    {
        $this->detail()->set('tautanUrl', 'https://drive.google.com/folder/abc')->call('tambahTautan');

        $this->actingAs($this->faris)->get(route('kanban.lampiran', Lampiran::firstOrFail()))
            ->assertRedirect('https://drive.google.com/folder/abc');
    }

    public function test_hapus_lampiran_tautan_tidak_menyentuh_disk(): void
    {
        Storage::fake('local');
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 10, 10)]);
        $this->detail()->set('tautanUrl', 'https://8mataair.com/x')->call('tambahTautan');

        $berkas = Lampiran::whereNotNull('path')->firstOrFail();
        $tautan = Lampiran::whereNotNull('url')->firstOrFail();

        $this->detail()->call('hapusLampiran', $tautan->id);

        Storage::disk('local')->assertExists($berkas->path);
        $this->assertSame(1, Lampiran::count());
    }

    // ---------------- Seret item checklist ----------------

    public function test_seret_item_checklist_mengubah_urutan(): void
    {
        $tiga = ChecklistItem::where('teks', 'Tiga')->firstOrFail();

        $this->detail()->call('urutItem', $tiga->id, 0, $this->checklist->id);

        $this->assertSame(['Tiga', 'Satu', 'Dua'], $this->urutanItem());
    }

    public function test_seret_item_ke_checklist_lain_di_kartu_yang_sama(): void
    {
        $lain = Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'Cetak', 'posisi' => 2]);
        $dua = ChecklistItem::where('teks', 'Dua')->firstOrFail();

        $this->detail()->call('urutItem', $dua->id, 0, $lain->id);

        $this->assertSame(['Satu', 'Tiga'], $this->urutanItem());
        $this->assertSame(['Dua'], $lain->item()->pluck('teks')->all());
    }

    public function test_seret_item_menolak_checklist_kartu_lain(): void
    {
        $kartuLain = app(Tata::class)->tambahKartu($this->board->kolom()->first(), 'Kartu lain', $this->faris);
        $checklistLain = Checklist::create(['kartu_id' => $kartuLain->id, 'judul' => 'Lain', 'posisi' => 1]);
        $satu = ChecklistItem::where('teks', 'Satu')->firstOrFail();

        $this->expectException(ModelNotFoundException::class);
        $this->detail()->call('urutItem', $satu->id, 0, $checklistLain->id);
    }

    public function test_seret_item_menomori_ulang_saat_celah_habis(): void
    {
        ChecklistItem::where('teks', 'Satu')->update(['posisi' => 1.0]);
        ChecklistItem::where('teks', 'Dua')->update(['posisi' => 1.0000000001]);
        $tiga = ChecklistItem::where('teks', 'Tiga')->firstOrFail();

        $this->detail()->call('urutItem', $tiga->id, 1, $this->checklist->id);

        $this->assertSame(['Satu', 'Tiga', 'Dua'], $this->urutanItem());
    }

    // ---------------- Ekspor CSV ----------------

    public function test_ekspor_csv_berisi_kartu_board(): void
    {
        $this->detail()->set('komentarBaru', 'Halo')->call('kirimKomentar');

        $isi = $this->actingAs($this->faris)->get(route('kanban.ekspor', $this->board))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('List,Card,Description', $isi);
        $this->assertStringContainsString('"To do","SD Harapan"', $isi);
        $this->assertStringContainsString('2026-10-02 09:30', $isi);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi, 'BOM supaya rapi di Excel');
    }

    public function test_ekspor_melewatkan_kartu_diarsipkan_kecuali_diminta(): void
    {
        $arsip = app(Tata::class)->tambahKartu($this->board->kolom()->first(), 'Kartu lama', $this->faris);
        $arsip->update(['diarsipkan_at' => now()]);

        $tanpa = $this->actingAs($this->faris)->get(route('kanban.ekspor', $this->board))->streamedContent();
        $dengan = $this->actingAs($this->faris)->get(route('kanban.ekspor', $this->board).'?arsip=1')->streamedContent();

        $this->assertStringNotContainsString('Kartu lama', $tanpa);
        $this->assertStringContainsString('Kartu lama', $dengan);
    }

    public function test_ekspor_board_privat_ditolak_untuk_orang_luar(): void
    {
        $this->board->update(['visibilitas' => 'privat']);
        $luar = User::factory()->create(['nama' => 'Luar']);
        $luar->assignRole('tim_event');

        $this->actingAs($luar)->get(route('kanban.ekspor', $this->board))->assertForbidden();
    }

    // ---------------- Aktivitas bertahap ----------------

    public function test_aktivitas_dimuat_bertahap(): void
    {
        for ($i = 0; $i < 25; $i++) {
            app(Tata::class)->tambahKartu($this->board->kolom()->first(), 'Kartu '.$i, $this->faris);
        }

        $papan = Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
        $this->assertCount(20, $papan->get('aktivitas'));
        $papan->assertSee('Load more');

        $papan->call('aktivitasLagi')->assertSet('jumlahAktivitas', 40);
        $this->assertGreaterThan(20, $papan->get('aktivitas')->count());
    }
}
