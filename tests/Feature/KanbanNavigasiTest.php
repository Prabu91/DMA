<?php

namespace Tests\Feature;

use App\Livewire\Kanban\Beranda;
use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\KartuSaya;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Saringan;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Nomor kartu, saringan tersimpan, dan board yang baru dibuka. */
class KanbanNavigasiTest extends TestCase
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
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->todo = $tata->tambahKolom($this->board, 'To do', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'SD Harapan', $this->faris);
        $tata->tambahKartu($this->todo, 'TK Pelita', $this->faris);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private static function kartuHtml(string $judul): string
    {
        return 'text-sm text-ink">'.$judul.'</span>';
    }

    // ---------------- Nomor kartu ----------------

    public function test_nomor_kartu_tampil_di_papan_dan_kartu(): void
    {
        $this->papan()->assertSee('#'.$this->kartu->id);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->assertSee('#'.$this->kartu->id);
    }

    public function test_cari_nomor_kartu_di_board(): void
    {
        $papan = $this->papan()->set('cari', '#'.$this->kartu->id);

        $papan->assertSeeHtml(self::kartuHtml('SD Harapan'))->assertDontSeeHtml(self::kartuHtml('TK Pelita'));

        // Tanpa pagar pun tetap ketemu.
        $this->papan()->set('cari', (string) $this->kartu->id)->assertSeeHtml(self::kartuHtml('SD Harapan'));
    }

    public function test_cari_nomor_kartu_lintas_board(): void
    {
        Livewire::actingAs($this->faris)->withQueryParams(['tab' => 'semua', 'q' => '#'.$this->kartu->id])
            ->test(KartuSaya::class)
            ->assertSee('SD Harapan')
            ->assertDontSee('TK Pelita');
    }

    // ---------------- Saringan tersimpan ----------------

    public function test_simpan_pakai_dan_hapus_saringan(): void
    {
        $label = $this->board->label()->first();
        $this->kartu->label()->attach($label->id);

        $papan = $this->papan()
            ->set('saringLabel', [$label->id])
            ->set('saringTenggat', 'tanpa')
            ->set('namaSaringan', 'Revisi saya')
            ->call('simpanSaringan')
            ->assertHasNoErrors();

        $saringan = Saringan::firstOrFail();
        $this->assertSame([$label->id], $saringan->isi['label']);
        $this->assertSame('tanpa', $saringan->isi['tenggat']);
        $papan->assertSee('Revisi saya');

        // Dipakai ulang sesudah saringan dibersihkan.
        $papan->call('bersihkanSaringan')->assertSet('saringLabel', []);
        $papan->call('pakaiSaringan', $saringan->id)
            ->assertSet('saringLabel', [$label->id])
            ->assertSet('saringTenggat', 'tanpa');

        $papan->call('hapusSaringan', $saringan->id);
        $this->assertSame(0, Saringan::count());
    }

    public function test_saringan_kosong_tidak_bisa_disimpan(): void
    {
        $this->papan()->set('namaSaringan', 'Kosong')->call('simpanSaringan')->assertStatus(422);
        $this->assertSame(0, Saringan::count());
    }

    public function test_saringan_milik_orang_lain_tidak_terlihat_dan_tidak_bisa_dipakai(): void
    {
        $lain = User::factory()->create(['nama' => 'Lain']);
        $lain->assignRole('editor');
        $saringan = Saringan::create([
            'board_id' => $this->board->id, 'user_id' => $lain->id,
            'nama' => 'Punya Lain', 'isi' => ['cari' => 'x'],
        ]);

        $this->papan()->assertDontSee('Punya Lain');

        $this->expectException(ModelNotFoundException::class);
        $this->papan()->call('pakaiSaringan', $saringan->id);
    }

    // ---------------- Baru dibuka ----------------

    public function test_membuka_board_tercatat_sebagai_baru_dibuka(): void
    {
        $this->assertSame(0, DB::table('kanban_kunjungan')->count());

        $this->papan();

        $this->assertDatabaseHas('kanban_kunjungan', [
            'user_id' => $this->faris->id,
            'board_id' => $this->board->id,
        ]);

        Livewire::actingAs($this->faris)->test(Beranda::class)->assertSee('Baru dibuka');
    }

    public function test_kunjungan_dicatat_per_orang(): void
    {
        $lain = User::factory()->create(['nama' => 'Lain']);
        $lain->assignRole('editor');

        $this->papan();

        Livewire::actingAs($lain)->test(Beranda::class)->assertDontSee('Baru dibuka');
        $this->assertSame(1, DB::table('kanban_kunjungan')->count());
    }

    public function test_kunjungan_diperbarui_bukan_ditumpuk(): void
    {
        $this->papan();
        $this->papan();

        $this->assertSame(1, DB::table('kanban_kunjungan')
            ->where('user_id', $this->faris->id)->where('board_id', $this->board->id)->count());
    }

    public function test_daftar_pintasan_memuat_yang_baru(): void
    {
        $this->papan()
            ->assertSee('Bersihkan saringan')
            ->assertSee('Beri / hapus bintang board');
    }
}
