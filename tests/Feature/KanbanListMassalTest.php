<?php

namespace Tests\Feature;

use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Aksi massal di list: urutkan, pindahkan semua, arsipkan semua, dan warna list. */
class KanbanListMassalTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kolom $todo;

    private Kolom $selesai;

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
        $this->selesai = $tata->tambahKolom($this->board, 'Selesai', $this->faris);

        $tata->tambahKartu($this->todo, 'C kartu', $this->faris, ['tenggat_pada' => now()->addDays(5)]);
        $tata->tambahKartu($this->todo, 'A kartu', $this->faris, ['tenggat_pada' => now()->addDay()]);
        $tata->tambahKartu($this->todo, 'B kartu', $this->faris);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private function urutan(Kolom $kolom): array
    {
        return $kolom->kartu()->pluck('judul')->all();
    }

    public function test_urutkan_kartu_per_judul_tenggat_dan_waktu_dibuat(): void
    {
        $papan = $this->papan();

        $papan->call('urutkanKartu', $this->todo->id, 'judul');
        $this->assertSame(['A kartu', 'B kartu', 'C kartu'], $this->urutan($this->todo));

        $papan->call('urutkanKartu', $this->todo->id, 'tenggat');
        $this->assertSame(['A kartu', 'C kartu', 'B kartu'], $this->urutan($this->todo), 'tanpa tenggat di belakang');

        $papan->call('urutkanKartu', $this->todo->id, 'lama');
        $this->assertSame(['C kartu', 'A kartu', 'B kartu'], $this->urutan($this->todo));

        $papan->call('urutkanKartu', $this->todo->id, 'baru');
        $this->assertSame(['B kartu', 'A kartu', 'C kartu'], $this->urutan($this->todo));

        $this->assertDatabaseHas('kanban_aktivitas', ['board_id' => $this->board->id, 'aksi' => 'kartu_diurutkan']);
    }

    public function test_urutan_ngawur_ditolak(): void
    {
        $this->papan()->call('urutkanKartu', $this->todo->id, 'ngawur')->assertStatus(422);
    }

    public function test_pindahkan_semua_kartu_ke_list_lain(): void
    {
        app(Tata::class)->tambahKartu($this->selesai, 'Sudah ada', $this->faris);

        $this->papan()
            ->call('pindahSemuaKartu', $this->todo->id, $this->selesai->id)
            ->assertSee('3 cards moved to &quot;Selesai&quot;.', false);

        $this->assertSame([], $this->urutan($this->todo));
        $this->assertSame(['Sudah ada', 'C kartu', 'A kartu', 'B kartu'], $this->urutan($this->selesai));
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kartu_pindah_massal']);
    }

    public function test_pindahkan_semua_dari_list_kosong(): void
    {
        $this->papan()
            ->call('pindahSemuaKartu', $this->selesai->id, $this->todo->id)
            ->assertSee('has no cards');

        $this->assertSame(3, $this->todo->kartu()->count());
    }

    public function test_pindahkan_semua_ke_list_yang_sama_ditolak(): void
    {
        $this->papan()->call('pindahSemuaKartu', $this->todo->id, $this->todo->id)->assertStatus(422);
    }

    public function test_arsipkan_semua_kartu_di_list(): void
    {
        $this->papan()
            ->call('arsipkanSemuaKartu', $this->todo->id)
            ->assertSee('3 cards in &quot;To do&quot; archived.', false);

        $this->assertSame(0, $this->todo->kartu()->count());
        $this->assertSame(3, Kartu::where('kolom_id', $this->todo->id)->whereNotNull('diarsipkan_at')->count());
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kartu_arsip_massal']);

        // Kartunya bisa dipulihkan satu per satu dari menu board.
        $kartu = Kartu::where('kolom_id', $this->todo->id)->first();
        $this->papan()->call('pulihkanKartu', $kartu->id);
        $this->assertSame(1, $this->todo->kartu()->count());
    }

    public function test_warna_kepala_list(): void
    {
        $papan = $this->papan();

        $papan->call('warnaKolom', $this->todo->id, 'hijau');
        $this->assertSame('hijau', $this->todo->fresh()->warna);

        $papan->call('warnaKolom', $this->todo->id, null);
        $this->assertNull($this->todo->fresh()->warna);

        $papan->call('warnaKolom', $this->todo->id, 'emas')->assertStatus(422);
    }

    public function test_aksi_massal_butuh_hak_mengubah_board(): void
    {
        $tamu = User::factory()->create(['nama' => 'Tamu']);
        $tamu->assignRole('tim_event');
        $papan = fn () => Livewire::actingAs($tamu)->test(PapanBoard::class, ['board' => $this->board]);

        $papan()->call('arsipkanSemuaKartu', $this->todo->id)->assertForbidden();
        $papan()->call('urutkanKartu', $this->todo->id, 'judul')->assertForbidden();
        $papan()->call('warnaKolom', $this->todo->id, 'hijau')->assertForbidden();
        $this->assertSame(3, $this->todo->kartu()->count());
    }

    public function test_list_terlipat_disimpan_di_peramban_pengguna(): void
    {
        // Papan menyediakan kunci penyimpanan per board dan tombol lipat.
        $this->papan()
            ->assertSeeHtml("kanban:lipat:{$this->board->id}")
            ->assertSee('Collapse list');
    }
}
