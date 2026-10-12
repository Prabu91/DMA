<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Kunci;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kunci board / list / kartu: yang terkunci tetap bisa dibaca, yang dibatasi
 * hanya mengubahnya, dan hanya orang/peran di daftar akses yang boleh.
 */
class KanbanKunciTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;      // admin pusat — selalu bisa menembus

    private User $rudi;       // editor, anggota board, diberi akses

    private User $marketing;  // anggota board, TIDAK diberi akses

    private Board $board;

    private Kolom $produksi;

    private Kolom $antrian;

    private Kartu $kartu;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->admin = User::factory()->create(['nama' => 'Super', 'cabang_id' => $cabang->id]);
        $this->admin->assignRole('super_admin');
        $this->rudi = User::factory()->create(['nama' => 'Rudi', 'cabang_id' => $cabang->id]);
        $this->rudi->assignRole('editor');
        $this->marketing = User::factory()->create(['nama' => 'Mita', 'cabang_id' => $cabang->id]);
        $this->marketing->assignRole('marketing');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('G. 7 Produksi', 'biru', 'workspace', $this->admin);
        $this->board->anggota()->attach([$this->rudi->id => ['peran' => 'anggota'], $this->marketing->id => ['peran' => 'anggota']]);

        $this->produksi = $tata->tambahKolom($this->board, 'Produksi', $this->admin);
        $this->antrian = $tata->tambahKolom($this->board, 'Antrian', $this->admin);
        $this->kartu = $tata->tambahKartu($this->produksi, 'SMP KARTIKA — Wisuda', $this->admin);
    }

    private function papan(User $sebagai)
    {
        return Livewire::actingAs($sebagai)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private function detail(User $sebagai)
    {
        return Livewire::actingAs($sebagai)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    /** Kunci list produksi, hanya untuk orang/peran yang disebut. */
    private function kunciProduksi(array $user = [], array $peran = []): void
    {
        $this->papan($this->admin)
            ->call('mulaiKunci', 'kolom', $this->produksi->id)
            ->set('kunciUser', $user)
            ->set('kunciPeran', $peran)
            ->call('simpanKunci');
    }

    // ---------------- Inti: yang dibatasi hanya mengubah ----------------

    public function test_kartu_di_list_terkunci_tidak_bisa_dipindah_orang_luar_daftar(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);

        $this->papan($this->marketing)
            ->call('pindahKartuKe', $this->kartu->id, $this->antrian->id)
            ->assertForbidden();

        $this->assertSame($this->produksi->id, $this->kartu->fresh()->kolom_id);
    }

    public function test_orang_di_daftar_tetap_bisa_memindahkan(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);

        $this->papan($this->rudi)->call('pindahKartuKe', $this->kartu->id, $this->antrian->id);

        $this->assertSame($this->antrian->id, $this->kartu->fresh()->kolom_id);
    }

    public function test_akses_boleh_diberikan_lewat_peran(): void
    {
        $this->kunciProduksi(peran: ['editor']);

        // Rudi editor — lolos. Mita marketing — ditolak.
        $this->papan($this->marketing)
            ->call('toggleAnggotaKartu', $this->kartu->id, $this->marketing->id)
            ->assertForbidden();

        $this->papan($this->rudi)->call('toggleAnggotaKartu', $this->kartu->id, $this->rudi->id);
        $this->assertTrue($this->kartu->anggota()->whereKey($this->rudi->id)->exists());
    }

    public function test_admin_pusat_selalu_bisa_menembus(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);

        $this->papan($this->admin)->call('arsipkanKartu', $this->kartu->id);

        $this->assertNotNull($this->kartu->fresh()->diarsipkan_at);
    }

    public function test_kartu_terkunci_tetap_bisa_dibuka_dan_dikerjakan(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);

        // Membuka kartu tidak terhalang kunci.
        $this->papan($this->marketing)->call('bukaKartu', $this->kartu->id)->assertSet('kartuId', $this->kartu->id);

        // Komentar & centang checklist tetap jalan — itu pekerjaannya.
        $detail = $this->detail($this->marketing)
            ->set('komentarBaru', 'Sudah dicetak ya')
            ->call('kirimKomentar');

        $this->assertDatabaseHas('kanban_komentar', ['kartu_id' => $this->kartu->id, 'isi' => 'Sudah dicetak ya']);

        // Tapi mengubah isinya tetap ditolak.
        $detail->set('judul', 'Judul baru')->call('simpanJudul')->assertForbidden();
        $this->assertSame('SMP KARTIKA — Wisuda', $this->kartu->fresh()->judul);
    }

    public function test_kartu_tidak_bisa_dipindahkan_masuk_ke_list_terkunci(): void
    {
        $lepas = app(Tata::class)->tambahKartu($this->antrian, 'Kartu bebas', $this->admin);
        $this->kunciProduksi(user: [$this->rudi->id]);

        $this->papan($this->marketing)
            ->call('pindahKartuKe', $lepas->id, $this->produksi->id)
            ->assertForbidden();

        $this->assertSame($this->antrian->id, $lepas->fresh()->kolom_id);
    }

    // ---------------- Bertingkat ----------------

    public function test_kunci_board_mengunci_semua_list_di_dalamnya(): void
    {
        $this->papan($this->admin)
            ->call('mulaiKunci', 'board')
            ->set('kunciUser', [$this->rudi->id])
            ->call('simpanKunci');

        $this->papan($this->marketing)->call('ubahNamaKolom', $this->antrian->id, 'Coba')->assertForbidden();
        $this->papan($this->rudi)->call('ubahNamaKolom', $this->antrian->id, 'Antrian QC');

        $this->assertSame('Antrian QC', $this->antrian->fresh()->nama);
    }

    public function test_kunci_list_mengalahkan_kunci_board(): void
    {
        // Board hanya untuk Rudi …
        $this->papan($this->admin)
            ->call('mulaiKunci', 'board')
            ->set('kunciUser', [$this->rudi->id])
            ->call('simpanKunci');

        // … tapi list Antrian diberi aturan sendiri: marketing boleh.
        $this->papan($this->admin)
            ->call('mulaiKunci', 'kolom', $this->antrian->id)
            ->set('kunciPeran', ['marketing'])
            ->call('simpanKunci');

        $this->papan($this->marketing)->call('ubahNamaKolom', $this->antrian->id, 'Antrian baru');
        $this->assertSame('Antrian baru', $this->antrian->fresh()->nama);

        // Di list lain aturan board tetap berlaku.
        $this->papan($this->marketing)->call('ubahNamaKolom', $this->produksi->id, 'Coba')->assertForbidden();
    }

    public function test_kunci_kartu_mengalahkan_kunci_list(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);

        Livewire::actingAs($this->admin)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->call('mulaiKunci')
            ->set('kunciPeran', ['marketing'])
            ->call('simpanKunci');

        $this->detail($this->marketing)->set('judul', 'Boleh diubah')->call('simpanJudul');
        $this->assertSame('Boleh diubah', $this->kartu->fresh()->judul);
    }

    // ---------------- Memasang & melepas ----------------

    public function test_hanya_pengelola_board_yang_boleh_mengunci(): void
    {
        $this->papan($this->marketing)->call('mulaiKunci', 'kolom', $this->produksi->id)->assertForbidden();
        $this->assertSame(0, Kunci::count());
    }

    public function test_membuka_kunci_mengembalikan_akses(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);
        $this->papan($this->admin)->call('bukaKunci', 'kolom', $this->produksi->id);

        $this->assertSame(0, Kunci::count());
        $this->papan($this->marketing)->call('pindahKartuKe', $this->kartu->id, $this->antrian->id);
        $this->assertSame($this->antrian->id, $this->kartu->fresh()->kolom_id);
    }

    public function test_daftar_akses_ditulis_ulang_bukan_ditambah(): void
    {
        $this->kunciProduksi(user: [$this->rudi->id]);
        $this->kunciProduksi(peran: ['marketing']);

        $kunci = Kunci::where('jenis', 'kolom')->where('target_id', $this->produksi->id)->firstOrFail();
        $this->assertSame(1, $kunci->akses()->count());
        $this->assertSame('marketing', $kunci->akses()->first()->peran);

        // Rudi tidak lagi punya akses karena daftarnya diganti.
        $this->papan($this->rudi)->call('arsipkanKartu', $this->kartu->id)->assertForbidden();
    }

    public function test_memasang_dan_membuka_kunci_tercatat_di_aktivitas(): void
    {
        $this->kunciProduksi(peran: ['editor']);
        $this->papan($this->admin)->call('bukaKunci', 'kolom', $this->produksi->id);

        $this->assertDatabaseHas('kanban_aktivitas', ['board_id' => $this->board->id, 'aksi' => 'kunci_dipasang']);
        $this->assertDatabaseHas('kanban_aktivitas', ['board_id' => $this->board->id, 'aksi' => 'kunci_dibuka']);
    }

    public function test_kunci_tanpa_daftar_akses_berarti_hanya_admin_pusat(): void
    {
        $this->kunciProduksi();

        $this->papan($this->rudi)->call('arsipkanKartu', $this->kartu->id)->assertForbidden();
        $this->papan($this->admin)->call('arsipkanKartu', $this->kartu->id);

        $this->assertNotNull($this->kartu->fresh()->diarsipkan_at);
    }
}
