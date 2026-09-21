<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
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

/** Sampul kartu ukuran penuh, nama lampiran, dan latar foto board. */
class KanbanSampulLatarTest extends TestCase
{
    use RefreshDatabase;

    private User $faris;

    private Board $board;

    private Kartu $kartu;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->faris = User::factory()->create(['nama' => 'Faris', 'cabang_id' => $cabang->id]);
        $this->faris->assignRole('admin_sales');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->kartu = $tata->tambahKartu($tata->tambahKolom($this->board, 'To do', $this->faris), 'SD Harapan', $this->faris);
    }

    private function detail()
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id]);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    private function unggahGambar(): Lampiran
    {
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 1200, 800)]);

        return Lampiran::firstOrFail();
    }

    // ---------------- Sampul ----------------

    public function test_sampul_bisa_diubah_ke_ukuran_penuh_dan_kembali(): void
    {
        $this->unggahGambar();
        $this->assertNotNull($this->kartu->fresh()->cover_lampiran_id, 'gambar pertama jadi sampul');

        $this->detail()->call('toggleSampulPenuh');
        $this->assertTrue($this->kartu->fresh()->cover_penuh);
        $this->papan()->assertSeeHtml('bg-gradient-to-t from-black/75');

        $this->detail()->call('toggleSampulPenuh');
        $this->assertFalse($this->kartu->fresh()->cover_penuh);
    }

    public function test_sampul_penuh_butuh_gambar(): void
    {
        $this->detail()->call('toggleSampulPenuh')->assertStatus(422);
        $this->assertFalse($this->kartu->fresh()->cover_penuh);
    }

    public function test_ganti_sampul_ke_warna_mengembalikan_ukuran_biasa(): void
    {
        $this->unggahGambar();
        $this->detail()->call('toggleSampulPenuh');

        $this->detail()->call('warnaSampul', 'hijau');

        $kartu = $this->kartu->fresh();
        $this->assertSame('hijau', $kartu->cover_warna);
        $this->assertNull($kartu->cover_lampiran_id);
        $this->assertFalse($kartu->cover_penuh);
    }

    // ---------------- Nama lampiran ----------------

    public function test_nama_lampiran_bisa_diubah(): void
    {
        $lampiran = $this->unggahGambar();

        $this->detail()->call('ubahNamaLampiran', $lampiran->id, '  Foto kelas 6.jpg  ');

        $this->assertSame('Foto kelas 6.jpg', $lampiran->fresh()->nama);
    }

    public function test_nama_lampiran_kosong_diabaikan(): void
    {
        $lampiran = $this->unggahGambar();

        $this->detail()->call('ubahNamaLampiran', $lampiran->id, '   ');

        $this->assertSame('foto.jpg', $lampiran->fresh()->nama);
    }

    public function test_lampiran_kartu_lain_tidak_bisa_diganti_namanya(): void
    {
        $lampiran = $this->unggahGambar();
        $lain = app(Tata::class)->tambahKartu($this->board->kolom()->first(), 'Kartu lain', $this->faris);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $lain->id])
            ->call('ubahNamaLampiran', $lampiran->id, 'Nyelonong');
    }

    // ---------------- Latar board ----------------

    public function test_latar_board_diunggah_dikecilkan_dan_bisa_dibuka(): void
    {
        $this->papan()->set('latar', UploadedFile::fake()->image('latar.jpg', 3000, 2000))
            ->assertHasNoErrors()
            ->assertSee('Latar board diperbarui.');

        $board = $this->board->fresh();
        $this->assertNotNull($board->latar_path);
        Storage::disk('local')->assertExists($board->latar_path);
        $this->assertStringContainsString('kecil-', $board->latar_path, 'yang disimpan versi kecilnya');

        $this->actingAs($this->faris)->get(route('kanban.latar', $board))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=604800, private');

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $board])
            ->assertSeeHtml('background-image: url(');
    }

    public function test_latar_lama_dibuang_saat_diganti_dan_dihapus(): void
    {
        $this->papan()->set('latar', UploadedFile::fake()->image('satu.jpg', 3000, 2000));
        $lama = $this->board->fresh()->latar_path;

        $this->papan()->set('latar', UploadedFile::fake()->image('dua.jpg', 3000, 2000));
        $baru = $this->board->fresh()->latar_path;

        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);

        $this->papan()->call('hapusLatar');
        $this->assertNull($this->board->fresh()->latar_path);
        Storage::disk('local')->assertMissing($baru);
    }

    public function test_bukan_gambar_ditolak(): void
    {
        $this->papan()->set('latar', UploadedFile::fake()->create('berkas.pdf', 20, 'application/pdf'))
            ->assertHasErrors('latar');

        $this->assertNull($this->board->fresh()->latar_path);
    }

    public function test_hanya_pengelola_yang_boleh_mengganti_latar(): void
    {
        $anggota = User::factory()->create(['nama' => 'Anggota']);
        $anggota->assignRole('editor');
        $this->board->anggota()->attach($anggota->id, ['peran' => 'anggota']);

        Livewire::actingAs($anggota)->test(PapanBoard::class, ['board' => $this->board])
            ->set('latar', UploadedFile::fake()->image('latar.jpg', 800, 600))
            ->assertForbidden();

        $this->assertNull($this->board->fresh()->latar_path);
    }

    public function test_latar_board_privat_tidak_bisa_dibuka_orang_luar(): void
    {
        $this->papan()->set('latar', UploadedFile::fake()->image('latar.jpg', 3000, 2000));
        $this->board->update(['visibilitas' => 'privat']);

        $luar = User::factory()->create(['nama' => 'Luar']);
        $luar->assignRole('tim_event');

        $this->actingAs($luar)->get(route('kanban.latar', $this->board->fresh()))->assertForbidden();
    }

    public function test_hapus_board_ikut_membuang_latarnya(): void
    {
        $this->papan()->set('latar', UploadedFile::fake()->image('latar.jpg', 3000, 2000));
        $latar = $this->board->fresh()->latar_path;

        $this->board->update(['diarsipkan_at' => now()]);
        app(Tata::class)->hapusBoard($this->board->fresh(), $this->faris);

        Storage::disk('local')->assertMissing($latar);
    }
}
