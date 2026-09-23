<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Bidang;
use App\Models\Kanban\BidangNilai;
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

/** Bidang khusus per board (padanan Custom Fields Trello). */
class KanbanBidangTest extends TestCase
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
        $this->todo = $tata->tambahKolom($this->board, 'Antrian', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'RA MADANI', $this->faris);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private function detail()
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    private function bidang(string $jenis = 'teks', array $atribut = []): Bidang
    {
        return Bidang::create(array_merge([
            'board_id' => $this->board->id,
            'nama' => 'No. invoice',
            'jenis' => $jenis,
            'posisi' => 1,
        ], $atribut));
    }

    // ---------------- Kelola bidang ----------------

    public function test_bidang_baru_dibuat_dari_menu_board(): void
    {
        $this->papan()
            ->set('namaBidangBaru', 'No. invoice')
            ->set('jenisBidangBaru', 'teks')
            ->call('tambahBidang')
            ->assertSet('namaBidangBaru', '');

        $bidang = Bidang::firstOrFail();
        $this->assertSame('No. invoice', $bidang->nama);
        $this->assertSame($this->board->id, $bidang->board_id);
        $this->assertFalse($bidang->di_depan);
    }

    public function test_bidang_pilihan_wajib_punya_daftar_pilihan(): void
    {
        $papan = $this->papan()
            ->set('namaBidangBaru', 'Jenis paket')
            ->set('jenisBidangBaru', 'pilihan')
            ->call('tambahBidang')
            ->assertHasErrors('opsiBidangBaru');

        $this->assertSame(0, Bidang::count());

        $papan->set('opsiBidangBaru', "Wisuda\nYearbook, Wisuda")->call('tambahBidang');

        // Ditulis per baris atau dipisah koma, dan yang kembar dibuang.
        $this->assertSame(['Wisuda', 'Yearbook'], Bidang::firstOrFail()->opsi);
    }

    public function test_bidang_tanpa_nama_ditolak(): void
    {
        $this->papan()->call('tambahBidang')->assertHasErrors('namaBidangBaru');
    }

    public function test_nama_bidang_bisa_diubah_dan_ditampilkan_di_kartu(): void
    {
        $bidang = $this->bidang();

        $papan = $this->papan()->call('ubahNamaBidang', $bidang->id, 'Nomor invoice');
        $this->assertSame('Nomor invoice', $bidang->fresh()->nama);

        $papan->call('toggleDepanBidang', $bidang->id);
        $this->assertTrue($bidang->fresh()->di_depan);

        $papan->call('toggleDepanBidang', $bidang->id);
        $this->assertFalse($bidang->fresh()->di_depan);
    }

    public function test_bidang_dihapus_beserta_isinya(): void
    {
        $bidang = $this->bidang();
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'INV-01']);

        $this->papan()->call('hapusBidang', $bidang->id);

        $this->assertSame(0, Bidang::count());
        $this->assertSame(0, BidangNilai::count());
    }

    public function test_anggota_biasa_tidak_boleh_mengelola_bidang(): void
    {
        $rizky = User::factory()->create(['nama' => 'Rizky', 'cabang_id' => $this->faris->cabang_id]);
        $rizky->assignRole('editor');
        $this->board->anggota()->attach($rizky->id, ['peran' => 'anggota']);
        $bidang = $this->bidang();

        Livewire::actingAs($rizky)->test(PapanBoard::class, ['board' => $this->board])
            ->call('hapusBidang', $bidang->id)->assertForbidden();
    }

    public function test_bidang_board_lain_tidak_bisa_disentuh(): void
    {
        $lain = app(Tata::class)->buatBoard('Board lain', 'hijau', 'workspace', $this->faris);
        $bidangLain = Bidang::create(['board_id' => $lain->id, 'nama' => 'Punya board lain', 'jenis' => 'teks', 'posisi' => 1]);

        $this->expectException(ModelNotFoundException::class);
        $this->papan()->call('hapusBidang', $bidangLain->id);
    }

    // ---------------- Isi bidang di kartu ----------------

    public function test_isi_bidang_disimpan_dan_dikosongkan_lagi(): void
    {
        $bidang = $this->bidang();

        $detail = $this->detail()->set('bidangIsi.'.$bidang->id, 'INV-2026-01');
        $this->assertDatabaseHas('kanban_bidang_nilai', [
            'bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'INV-2026-01',
        ]);

        $detail->set('bidangIsi.'.$bidang->id, '');
        $this->assertSame(0, BidangNilai::count(), 'isian kosong berarti bidangnya dilepas');
    }

    public function test_isian_dibaca_lagi_saat_kartu_dibuka(): void
    {
        $bidang = $this->bidang();
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'INV-9']);

        $this->detail()->assertSet('bidangIsi.'.$bidang->id, 'INV-9')->assertSee('No. invoice');
    }

    public function test_centang_disimpan_sebagai_ya_tidak(): void
    {
        $bidang = $this->bidang('centang', ['nama' => 'Sudah dicetak']);

        $detail = $this->detail()->set('bidangIsi.'.$bidang->id, true);
        $this->assertSame('1', BidangNilai::firstOrFail()->nilai);

        $detail->set('bidangIsi.'.$bidang->id, false);
        $this->assertSame(0, BidangNilai::count());
    }

    public function test_angka_dan_tanggal_ngawur_ditolak(): void
    {
        $angka = $this->bidang('angka', ['nama' => 'Jumlah cetak']);
        $tanggal = $this->bidang('tanggal', ['nama' => 'Kirim', 'posisi' => 2]);

        // Isian ngawur tidak disimpan, dan alasannya disebut lewat kabar sesaat.
        $this->detail()->set('bidangIsi.'.$angka->id, 'seratus')
            ->assertDispatched('toast')
            ->assertSet('bidangIsi.'.$angka->id, '');
        $this->detail()->set('bidangIsi.'.$tanggal->id, '31-02-2026')->assertDispatched('toast');
        $this->assertSame(0, BidangNilai::count());

        $this->detail()->set('bidangIsi.'.$angka->id, '120');
        $this->assertSame('120', BidangNilai::firstOrFail()->nilai);
    }

    public function test_pilihan_di_luar_daftar_ditolak(): void
    {
        $bidang = $this->bidang('pilihan', ['nama' => 'Jenis paket', 'opsi' => ['Wisuda', 'Yearbook']]);

        $this->detail()->set('bidangIsi.'.$bidang->id, 'Prewedding')->assertDispatched('toast');
        $this->assertSame(0, BidangNilai::count());

        $this->detail()->set('bidangIsi.'.$bidang->id, 'Wisuda');
        $this->assertSame('Wisuda', BidangNilai::firstOrFail()->nilai);
    }

    // ---------------- Tampil di papan & ikut tersalin ----------------

    public function test_nilai_tampil_di_muka_kartu_hanya_bila_diminta(): void
    {
        $bidang = $this->bidang();
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'INV-77']);

        $this->papan()->assertDontSee('INV-77');

        $this->papan()->call('toggleDepanBidang', $bidang->id)->assertSee('INV-77');
    }

    public function test_salinan_kartu_membawa_isi_bidang(): void
    {
        $bidang = $this->bidang();
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'INV-5']);

        $this->papan()->call('salinKartu', $this->kartu->id);

        $salinan = Kartu::where('judul', 'RA MADANI (copy)')->firstOrFail();
        $this->assertSame('INV-5', $salinan->bidangNilai()->firstOrFail()->nilai);
    }

    public function test_salinan_board_membawa_bidang_beserta_isinya(): void
    {
        $bidang = $this->bidang('pilihan', ['nama' => 'Jenis paket', 'opsi' => ['Wisuda'], 'di_depan' => true]);
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => 'Wisuda']);

        $salinan = app(Tata::class)->salinBoard($this->board, 'Editing 2027', true, $this->faris);

        $bidangBaru = $salinan->bidang()->firstOrFail();
        $this->assertSame('Jenis paket', $bidangBaru->nama);
        $this->assertSame(['Wisuda'], $bidangBaru->opsi);
        $this->assertTrue($bidangBaru->di_depan);
        $this->assertSame('Wisuda', $bidangBaru->nilai()->firstOrFail()->nilai);
    }

    public function test_ekspor_csv_memuat_kolom_bidang(): void
    {
        $bidang = $this->bidang('angka', ['nama' => 'Jumlah cetak']);
        BidangNilai::create(['bidang_id' => $bidang->id, 'kartu_id' => $this->kartu->id, 'nilai' => '120']);

        $isi = $this->actingAs($this->faris)
            ->get(route('kanban.ekspor', $this->board))
            ->streamedContent();

        $this->assertStringContainsString('Jumlah cetak', $isi);
        $this->assertStringContainsString('120', $isi);
    }
}
