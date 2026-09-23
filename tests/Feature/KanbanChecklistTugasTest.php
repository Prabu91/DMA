<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\User;
use App\Notifications\KanbanKabar;
use App\Services\Kanban\Kabar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Item checklist yang bisa ditugaskan, diberi tenggat, dan diubah jadi kartu. */
class KanbanChecklistTugasTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private User $rizky;

    private Board $board;

    private Kolom $todo;

    private Kartu $kartu;

    private ChecklistItem $item;

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
        $this->todo = $tata->tambahKolom($this->board, 'To do', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'SD Harapan', $this->faris);

        $checklist = Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'QC', 'posisi' => 1]);
        $this->item = ChecklistItem::create(['checklist_id' => $checklist->id, 'teks' => 'Foto wisuda', 'posisi' => 1]);
    }

    private function detail()
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    public function test_tugaskan_item_ke_anggota_dan_lepas_lagi(): void
    {
        $this->detail()
            ->call('tugaskanItem', $this->item->id, $this->rizky->id)
            ->assertSee('Rizky');

        $this->assertSame($this->rizky->id, $this->item->fresh()->user_id);

        $this->detail()->call('tugaskanItem', $this->item->id, null);
        $this->assertNull($this->item->fresh()->user_id);
    }

    public function test_penugasan_item_mengabari_dan_membuatnya_mengikuti_kartu(): void
    {
        Notification::fake();

        $this->detail()->call('tugaskanItem', $this->item->id, $this->rizky->id);

        Notification::assertSentTo($this->rizky, function (KanbanKabar $k) {
            return $k->jenis === KanbanKabar::DITUGASKAN && str_contains((string) $k->cuplikan, 'Foto wisuda');
        });
        $this->assertTrue(app(Kabar::class)->mengikuti($this->kartu, $this->rizky));
    }

    public function test_tidak_bisa_menugaskan_ke_orang_di_luar_board(): void
    {
        $luar = User::factory()->create(['nama' => 'Dodi']);
        $luar->assignRole('marketing');

        $this->detail()->call('tugaskanItem', $this->item->id, $luar->id)->assertForbidden();
        $this->assertNull($this->item->fresh()->user_id);
    }

    public function test_tenggat_item_disimpan_dan_bisa_dihapus(): void
    {
        $this->detail()->call('tenggatItem', $this->item->id, '2026-10-02T09:30');

        $item = $this->item->fresh();
        $this->assertSame('2026-10-02 09:30', $item->tenggat_pada->format('Y-m-d H:i'));

        $this->detail()->call('tenggatItem', $this->item->id, null);
        $this->assertNull($this->item->fresh()->tenggat_pada);
        $this->assertNull($this->item->fresh()->keadaanTenggat());
    }

    public function test_keadaan_tenggat_item(): void
    {
        $this->item->update(['tenggat_pada' => now()->addHours(3)]);
        $this->assertSame('segera', $this->item->fresh()->keadaanTenggat());

        $this->item->update(['tenggat_pada' => now()->addDays(3)]);
        $this->assertSame('biasa', $this->item->fresh()->keadaanTenggat());

        $this->item->update(['tenggat_pada' => now()->subHour()]);
        $this->assertSame('lewat', $this->item->fresh()->keadaanTenggat());

        $this->item->update(['selesai_at' => now()]);
        $this->assertSame('selesai', $this->item->fresh()->keadaanTenggat());
    }

    public function test_item_bisa_diubah_jadi_kartu(): void
    {
        $this->detail()
            ->call('tugaskanItem', $this->item->id, $this->rizky->id)
            ->call('tenggatItem', $this->item->id, '2026-10-02T09:30')
            ->call('itemJadiKartu', $this->item->id);

        $baru = Kartu::where('judul', 'Foto wisuda')->firstOrFail();
        $this->assertSame($this->todo->id, $baru->kolom_id);
        $this->assertSame('2026-10-02 09:30', $baru->tenggat_pada->format('Y-m-d H:i'));
        $this->assertTrue($baru->anggota->contains('id', $this->rizky->id));
        $this->assertNull(ChecklistItem::find($this->item->id), 'item asli dipindahkan, bukan digandakan');
        $this->assertDatabaseHas('kanban_aktivitas', ['kartu_id' => $this->kartu->id, 'aksi' => 'item_jadi_kartu']);
    }

    public function test_yang_tidak_boleh_mengubah_board_tidak_bisa_menugaskan(): void
    {
        $lain = User::factory()->create(['nama' => 'Tamu']);
        $lain->assignRole('tim_event');

        Livewire::actingAs($lain)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->call('tugaskanItem', $this->item->id, $this->rizky->id)
            ->assertForbidden();
    }

    public function test_salin_kartu_ikut_membawa_tugas_dan_tenggat_item(): void
    {
        $this->detail()
            ->call('tugaskanItem', $this->item->id, $this->rizky->id)
            ->call('tenggatItem', $this->item->id, '2026-10-02T09:30');

        $this->detail()->set('judulSalinan', 'Salinan')->call('salin');

        $salinan = Kartu::where('judul', 'Salinan')->firstOrFail();
        $item = $salinan->checklistItem()->first();
        $this->assertSame($this->rizky->id, $item->user_id);
        $this->assertSame('2026-10-02 09:30', $item->tenggat_pada->format('Y-m-d H:i'));
        $this->assertNull($item->selesai_at);
    }

    public function test_checklist_baru_bisa_menyalin_item_dari_checklist_lain(): void
    {
        ChecklistItem::create(['checklist_id' => $this->item->checklist_id, 'teks' => 'Cetak album', 'posisi' => 2]);
        // Item yang sudah dicentang pun disalin dalam keadaan kosong lagi.
        $this->item->update(['selesai_at' => now(), 'selesai_oleh' => $this->faris->id]);

        $this->detail()
            ->set('judulChecklist', 'QC ulang')
            ->set('salinItemDari', $this->item->checklist_id)
            ->call('tambahChecklist')
            ->assertSet('salinItemDari', null);

        $baru = Checklist::where('judul', 'QC ulang')->firstOrFail();
        $this->assertSame(['Foto wisuda', 'Cetak album'], $baru->item()->pluck('teks')->all());
        $this->assertSame(0, $baru->item()->whereNotNull('selesai_at')->count());
    }

    public function test_checklist_tanpa_sumber_tetap_kosong(): void
    {
        $this->detail()->set('judulChecklist', 'Kosong')->call('tambahChecklist');

        $this->assertSame(0, Checklist::where('judul', 'Kosong')->firstOrFail()->item()->count());
    }

    public function test_pilihan_salin_hanya_checklist_berisi_di_board_ini(): void
    {
        Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'Masih kosong', 'posisi' => 2]);

        $lain = app(Tata::class)->buatBoard('Board lain', 'hijau', 'workspace', $this->faris);
        $kolomLain = app(Tata::class)->tambahKolom($lain, 'List', $this->faris);
        $kartuLain = app(Tata::class)->tambahKartu($kolomLain, 'Kartu board lain', $this->faris);
        $clLain = Checklist::create(['kartu_id' => $kartuLain->id, 'judul' => 'Punya board lain', 'posisi' => 1]);
        ChecklistItem::create(['checklist_id' => $clLain->id, 'teks' => 'Tak boleh muncul', 'posisi' => 1]);

        $judul = $this->detail()->get('checklistSumber')->pluck('judul')->all();

        $this->assertSame(['QC'], $judul);
    }
}
