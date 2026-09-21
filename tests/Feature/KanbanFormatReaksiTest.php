<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Komentar;
use App\Models\Kanban\Reaksi;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Teks;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Deskripsi & komentar berformat, reaksi emoji, dan pintasan papan ketik. */
class KanbanFormatReaksiTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private User $rizky;

    private Board $board;

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
        $this->kartu = $tata->tambahKartu($tata->tambahKolom($this->board, 'To do', $this->faris), 'SD Harapan', $this->faris);
    }

    private function detail(?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    // ---------------- Teks berformat ----------------

    public function test_markdown_diubah_jadi_html(): void
    {
        $html = (string) Teks::html("**tebal** dan *miring*\n\n- satu\n- dua");

        $this->assertStringContainsString('<strong>tebal</strong>', $html);
        $this->assertStringContainsString('<em>miring</em>', $html);
        $this->assertStringContainsString('<li>satu</li>', $html);
    }

    public function test_html_dari_pengguna_tidak_dieksekusi(): void
    {
        $html = (string) Teks::html('<script>alert(1)</script> <b>bukan tebal</b>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_tautan_dibuka_di_tab_baru_dan_tautan_berbahaya_ditolak(): void
    {
        $html = (string) Teks::html('[situs](https://8mataair.com) [jahat](javascript:alert(1))');

        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer" href="https://8mataair.com"', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_sebutan_disorot_tanpa_merusak_tautan(): void
    {
        $html = (string) Teks::html('halo @Shanty, cek [ini](https://contoh.test/@rina)');

        $this->assertStringContainsString('>@Shanty</span>', $html);
        $this->assertStringContainsString('href="https://contoh.test/@rina"', $html);
    }

    public function test_deskripsi_dan_komentar_tampil_berformat(): void
    {
        $this->detail()->set('deskripsi', "**Redaksi**\n\n- baris satu")->call('simpanDeskripsi');
        $this->detail()->set('komentarBaru', 'ini `kode`')->call('kirimKomentar');

        $this->detail()
            ->assertSeeHtml('<strong>Redaksi</strong>')
            ->assertSeeHtml('<li>baris satu</li>')
            ->assertSeeHtml('<code>kode</code>');
    }

    // ---------------- Reaksi ----------------

    public function test_beri_dan_tarik_reaksi(): void
    {
        $this->detail()->set('komentarBaru', 'Sudah dicek')->call('kirimKomentar');
        $komentar = Komentar::firstOrFail();
        $jempol = Reaksi::PILIHAN[0];

        $this->detail($this->rizky)->call('toggleReaksi', $komentar->id, $jempol);
        $this->assertSame(1, Reaksi::where('komentar_id', $komentar->id)->count());

        // Orang yang sama menekan lagi: reaksinya ditarik.
        $this->detail($this->rizky)->call('toggleReaksi', $komentar->id, $jempol);
        $this->assertSame(0, Reaksi::count());
    }

    public function test_reaksi_dihitung_per_emoji_dan_ditampilkan(): void
    {
        $this->detail()->set('komentarBaru', 'Sudah dicek')->call('kirimKomentar');
        $komentar = Komentar::firstOrFail();
        [$jempol, $tepuk] = Reaksi::PILIHAN;

        $this->detail()->call('toggleReaksi', $komentar->id, $jempol);
        $this->detail($this->rizky)->call('toggleReaksi', $komentar->id, $jempol);
        $this->detail($this->rizky)->call('toggleReaksi', $komentar->id, $tepuk);

        $this->assertSame(2, Reaksi::where('emoji', $jempol)->count());
        $this->detail()->assertSee($jempol.' 2', false)->assertSee($tepuk.' 1', false);
    }

    public function test_emoji_di_luar_pilihan_ditolak(): void
    {
        $this->detail()->set('komentarBaru', 'Halo')->call('kirimKomentar');
        $komentar = Komentar::firstOrFail();

        $this->detail()->call('toggleReaksi', $komentar->id, '💣')->assertStatus(422);
        $this->assertSame(0, Reaksi::count());
    }

    public function test_reaksi_ikut_terhapus_saat_komentar_dihapus(): void
    {
        $this->detail()->set('komentarBaru', 'Halo')->call('kirimKomentar');
        $komentar = Komentar::firstOrFail();
        $this->detail()->call('toggleReaksi', $komentar->id, Reaksi::PILIHAN[0]);

        $this->detail()->call('hapusKomentar', $komentar->id);

        $this->assertSame(0, Reaksi::count());
    }

    public function test_komentar_kartu_lain_tidak_bisa_direaksi(): void
    {
        $lain = app(Tata::class);
        $kartuLain = $lain->tambahKartu($this->board->kolom()->first(), 'Kartu lain', $this->faris);
        $komentar = Komentar::create(['kartu_id' => $kartuLain->id, 'user_id' => $this->faris->id, 'isi' => 'Halo']);

        $this->expectException(ModelNotFoundException::class);
        $this->detail()->call('toggleReaksi', $komentar->id, Reaksi::PILIHAN[0]);
    }

    // ---------------- Pintasan ----------------

    public function test_pintasan_tambah_kartu_membuka_isian_di_list_pertama(): void
    {
        $papan = Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->call('mulaiTambahKartuPertama');

        $papan->assertSet('tambahKartuDi', $this->board->kolom()->first()->id);
    }

    public function test_pintasan_tambah_kartu_aman_saat_board_belum_punya_list(): void
    {
        $kosong = app(Tata::class)->buatBoard('Kosong', 'hijau', 'workspace', $this->faris);

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $kosong])
            ->call('mulaiTambahKartuPertama')
            ->assertSet('tambahKartuDi', null)
            ->assertHasNoErrors();
    }

    public function test_daftar_pintasan_tampil_di_papan(): void
    {
        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->assertSee('Pintasan papan ketik')
            ->assertSee('Tambah kartu di list pertama')
            ->assertSee('Tugaskan / lepaskan diri sendiri');
    }
}
