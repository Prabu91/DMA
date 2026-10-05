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
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    // ---------------- Move list ----------------

    public function test_move_list_menggeser_posisi_di_board_yang_sama(): void
    {
        $papan = $this->papan()
            ->call('mulaiPindahList', $this->selesai->id)
            ->assertSet('pindahListBoard', $this->board->id)
            ->assertSet('pindahListUrutan', 2)
            ->set('pindahListUrutan', 1)
            ->call('pindahkanList');

        $this->assertSame(['Selesai', 'To do'], $this->board->kolom()->pluck('nama')->all());
        $papan->assertSet('pindahListId', null);
    }

    public function test_move_list_ke_posisi_tengah_di_board_berisi_banyak_list(): void
    {
        $tata = app(Tata::class);
        foreach (['C list', 'D list', 'E list'] as $nama) {
            $tata->tambahKolom($this->board, $nama, $this->faris);
        }
        // Urutan awal: To do, Selesai, C list, D list, E list.
        $e = Kolom::where('board_id', $this->board->id)->where('nama', 'E list')->firstOrFail();

        $this->papan()
            ->call('mulaiPindahList', $e->id)
            ->assertSet('pindahListUrutan', 5)
            ->set('pindahListUrutan', 2)
            ->call('pindahkanList');

        $this->assertSame(
            ['To do', 'E list', 'Selesai', 'C list', 'D list'],
            $this->board->kolom()->pluck('nama')->all(),
        );
    }

    public function test_urutan_awal_move_list_memakai_posisi_asli_bukan_yang_disematkan(): void
    {
        $tata = app(Tata::class);
        $c = $tata->tambahKolom($this->board, 'C list', $this->faris);

        // "C list" disematkan: tampil paling kiri, tapi posisinya tetap ketiga.
        $papan = $this->papan()->call('togglePinKolom', $c->id);
        $this->assertSame('C list', $papan->get('kolom')->first()->nama, 'list yang disematkan tampil pertama');

        $papan->call('mulaiPindahList', $c->id)->assertSet('pindahListUrutan', 3);
    }

    public function test_move_list_ke_board_lain_membawa_kartunya(): void
    {
        $tujuan = app(Tata::class)->buatBoard('6. QC', 'hijau', 'workspace', $this->faris);
        $label = $this->board->label()->first();
        $kartu = $this->todo->kartu()->first();
        $kartu->label()->attach($label->id);

        $this->papan()
            ->call('mulaiPindahList', $this->todo->id)
            ->call('pilihBoardList', $tujuan->id)
            ->call('pindahkanList');

        $this->todo->refresh();
        $this->assertSame($tujuan->id, $this->todo->board_id);
        $this->assertSame(3, Kartu::where('kolom_id', $this->todo->id)->where('board_id', $tujuan->id)->count());
        // Label milik board lama tidak ikut, seperti saat memindahkan kartu antar board.
        $this->assertFalse($kartu->fresh()->label()->exists());
        $this->assertDatabaseHas('kanban_aktivitas', ['board_id' => $tujuan->id, 'aksi' => 'kolom_pindah']);
    }

    public function test_move_list_ke_board_yang_tidak_boleh_diubah_ditolak(): void
    {
        // Faris admin_sales (lintas cabang), jadi pemeriksaannya dilakukan sebagai editor biasa.
        $rizky = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $this->faris->cabang_id]);
        $rizky->assignRole('editor');
        $this->board->anggota()->attach($rizky->id, ['peran' => 'anggota']);

        $dewi = User::factory()->create(['nama' => 'Dewi', 'cabang_id' => $this->faris->cabang_id]);
        $dewi->assignRole('editor');
        $rahasia = app(Tata::class)->buatBoard('Rahasia Dewi', 'merah', 'privat', $dewi);

        Livewire::actingAs($rizky)->test(PapanBoard::class, ['board' => $this->board])
            ->call('mulaiPindahList', $this->todo->id)
            ->call('pilihBoardList', $rahasia->id)
            ->assertForbidden();

        $this->assertSame($this->board->id, $this->todo->fresh()->board_id);
    }

    public function test_kartu_baru_bisa_ditaruh_di_atas_list(): void
    {
        $this->papan()
            ->call('mulaiTambahKartu', $this->todo->id, true)
            ->assertSet('tambahKartuAtas', true)
            ->set('judulKartuBaru', 'Kartu paling atas')
            ->call('tambahKartu');

        $this->assertSame('Kartu paling atas', $this->urutan($this->todo)[0]);
    }

    public function test_kartu_baru_tetap_di_bawah_bila_lewat_tombol_bawah(): void
    {
        $this->papan()
            ->call('mulaiTambahKartu', $this->todo->id)
            ->assertSet('tambahKartuAtas', false)
            ->set('judulKartuBaru', 'Kartu paling bawah')
            ->call('tambahKartu');

        $urutan = $this->urutan($this->todo);
        $this->assertSame('Kartu paling bawah', end($urutan));
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

    // ---------------- Sematkan list & warna ----------------

    public function test_list_disematkan_pindah_ke_paling_kiri(): void
    {
        $papan = $this->papan();
        $this->assertSame(['To do', 'Selesai'], $papan->get('kolom')->pluck('nama')->all());

        $papan->call('togglePinKolom', $this->selesai->id);

        // Yang disematkan naik ke kiri, sisanya tetap urut aslinya.
        $this->assertSame(['Selesai', 'To do'], $this->papan()->get('kolom')->pluck('nama')->all());
        $this->assertTrue($this->papan()->get('kolom')->firstWhere('nama', 'Selesai')->disematkan);
    }

    public function test_hanya_sematan_paling_kanan_yang_diberi_pembatas(): void
    {
        $tata = app(Tata::class);
        $c = $tata->tambahKolom($this->board, 'C list', $this->faris);

        // Satu sematan: dialah batas antara wilayah beku dan wilayah yang bergeser.
        $papan = $this->papan()->call('togglePinKolom', $this->selesai->id);
        $this->assertSame(1, substr_count($papan->html(), 'batas-sematan'));

        // Dua sematan: pembatasnya tetap satu, di sematan paling kanan.
        $papan->call('togglePinKolom', $c->id);
        $html = $this->papan()->html();
        $this->assertSame(1, substr_count($html, 'batas-sematan'));
    }

    public function test_papan_tanpa_sematan_tidak_punya_pembatas(): void
    {
        $this->assertStringNotContainsString('batas-sematan', $this->papan()->html());
    }

    public function test_sematan_bisa_dilepas_lagi(): void
    {
        $papan = $this->papan()->call('togglePinKolom', $this->selesai->id);
        $papan->call('togglePinKolom', $this->selesai->id);

        $this->assertSame(['To do', 'Selesai'], $this->papan()->get('kolom')->pluck('nama')->all());
        $this->assertDatabaseCount('kanban_kolom_pin', 0);
    }

    public function test_sematan_hanya_berlaku_untuk_orang_yang_menyematkan(): void
    {
        $rizky = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $this->faris->cabang_id]);
        $rizky->assignRole('editor');
        $this->board->anggota()->attach($rizky->id, ['peran' => 'anggota']);

        $this->papan()->call('togglePinKolom', $this->selesai->id);

        $kolomRizky = Livewire::actingAs($rizky)->test(PapanBoard::class, ['board' => $this->board])->get('kolom');
        $this->assertSame(['To do', 'Selesai'], $kolomRizky->pluck('nama')->all());
    }

    public function test_list_board_lain_tidak_bisa_disematkan(): void
    {
        $lain = app(Tata::class)->buatBoard('Board lain', 'hijau', 'workspace', $this->faris);
        $kolomLain = app(Tata::class)->tambahKolom($lain, 'List', $this->faris);

        $this->expectException(ModelNotFoundException::class);
        $this->papan()->call('togglePinKolom', $kolomLain->id);
    }

    public function test_warna_list_mewarnai_seluruh_kolom(): void
    {
        $this->papan()->call('warnaKolom', $this->todo->id, 'hijau');

        $this->papan()
            // Warnanya menempel di wadah list ("kolom-list"), …
            ->assertSeeHtml('bg-[#4BCE97] kolom-list"')
            // … bukan lagi garis tipis di kepala list.
            ->assertDontSeeHtml('h-1.5 rounded-t-xl');
    }

    public function test_menu_board_punya_lipat_dan_buka_semua(): void
    {
        $this->papan()->assertSee('Collapse all lists')->assertSee('Expand all lists');
    }
}
