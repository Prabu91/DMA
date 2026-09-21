<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Komentar;
use App\Models\Kanban\Lampiran;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Salin kartu, list, dan board, serta kartu templat — padanan fitur Copy di Trello. */
class KanbanSalinTest extends TestCase
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
        $tata->tambahKolom($this->board, 'Selesai', $this->faris);
        $this->kartu = $tata->tambahKartu($this->todo, 'SD Harapan', $this->faris, [
            'deskripsi' => 'Cek foto kelas',
            'tenggat_pada' => now()->addDays(2),
        ]);
        $this->kartu->label()->attach($this->board->label()->first()->id);
        $this->kartu->anggota()->attach($this->faris->id);
        $cl = Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'QC', 'posisi' => 1]);
        ChecklistItem::create(['checklist_id' => $cl->id, 'teks' => 'Foto kelas', 'posisi' => 1, 'selesai_at' => now()]);
        Komentar::create(['kartu_id' => $this->kartu->id, 'user_id' => $this->faris->id, 'isi' => 'jangan ikut']);
    }

    private function detail(?int $kartuId = null)
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $kartuId ?? $this->kartu->id]);
    }

    public function test_salin_kartu_membawa_isi_yang_dipilih(): void
    {
        $selesai = $this->board->kolom()->get()[1];

        $this->detail()
            ->set('judulSalinan', 'SD Harapan (salinan)')
            ->set('salinKolom', $selesai->id)
            ->call('salin')
            ->assertHasNoErrors()
            ->assertDispatched('buka-kartu');

        $salinan = Kartu::where('judul', 'SD Harapan (salinan)')->firstOrFail();
        $this->assertSame($selesai->id, $salinan->kolom_id);
        $this->assertSame('Cek foto kelas', $salinan->deskripsi);
        $this->assertSame($this->kartu->tenggat_pada->format('Y-m-d H:i'), $salinan->tenggat_pada->format('Y-m-d H:i'));
        $this->assertCount(1, $salinan->label);
        $this->assertCount(1, $salinan->anggota);
        $this->assertSame(1, $salinan->checklist()->count());
        $this->assertSame('Foto kelas', $salinan->checklistItem()->first()->teks);
        $this->assertNull($salinan->checklistItem()->first()->selesai_at, 'centang checklist tidak ikut');
        $this->assertSame(0, Komentar::where('kartu_id', $salinan->id)->count(), 'komentar tidak ikut');
        $this->assertNull($salinan->order_id);
    }

    public function test_salin_kartu_tanpa_membawa_apa_pun(): void
    {
        $this->detail()
            ->set('judulSalinan', 'Polos')
            ->set('bawaSalinan', [])
            ->call('salin');

        $salinan = Kartu::where('judul', 'Polos')->firstOrFail();
        $this->assertCount(0, $salinan->label);
        $this->assertCount(0, $salinan->anggota);
        $this->assertSame(0, $salinan->checklist()->count());
    }

    public function test_salin_kartu_dengan_lampiran_menyalin_berkas_dan_sampul(): void
    {
        Storage::fake('local');
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 20, 20)]);
        $asli = Lampiran::firstOrFail();

        $this->detail()
            ->set('judulSalinan', 'Dengan lampiran')
            ->set('bawaSalinan', ['lampiran'])
            ->call('salin');

        $salinan = Kartu::where('judul', 'Dengan lampiran')->firstOrFail();
        $lampiran = Lampiran::where('kartu_id', $salinan->id)->firstOrFail();
        $this->assertNotSame($asli->path, $lampiran->path, 'berkas disalin, bukan dipakai bersama');
        Storage::disk('local')->assertExists($lampiran->path);
        $this->assertSame($lampiran->id, $salinan->cover_lampiran_id);
    }

    public function test_salin_kartu_butuh_judul_dan_list(): void
    {
        $this->detail()->set('judulSalinan', '')->call('salin')->assertHasErrors('judulSalinan');
    }

    public function test_salin_ke_board_yang_tidak_boleh_diubah_ditolak(): void
    {
        $asing = User::factory()->create(['nama' => 'Asing']);
        $asing->assignRole('editor');
        $boardAsing = app(Tata::class)->buatBoard('Board Asing', 'hijau', 'workspace', $asing);
        $kolomAsing = app(Tata::class)->tambahKolom($boardAsing, 'Masuk', $asing);

        $marketing = User::factory()->create(['nama' => 'Shanty']);
        $marketing->assignRole('marketing');
        $this->board->anggota()->attach($marketing->id, ['peran' => 'anggota']);

        Livewire::actingAs($marketing)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('judulSalinan', 'Nyelonong')
            ->set('salinKolom', $kolomAsing->id)
            ->call('salin')
            ->assertForbidden();
    }

    public function test_salin_list_menyalin_kartunya(): void
    {
        app(Tata::class)->tambahKartu($this->todo, 'SMP 3', $this->faris);

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->call('salinKolom', $this->todo->id)
            ->assertSee('To do (copy)');

        $salinan = Kolom::where('nama', 'To do (copy)')->firstOrFail();
        $this->assertSame(['SD Harapan', 'SMP 3'], $salinan->kartu()->pluck('judul')->all());
        $this->assertSame(2, $this->todo->kartu()->count(), 'list asli tidak berubah');
    }

    public function test_salin_board(): void
    {
        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->assertSet('namaSalinanBoard', '5. Editing (copy)')
            ->set('namaSalinanBoard', 'Editing 2027')
            ->call('salinBoard')
            ->assertRedirect();

        $baru = Board::where('nama', 'Editing 2027')->firstOrFail();
        $this->assertSame(['To do', 'Selesai'], $baru->kolom()->pluck('nama')->all());
        $this->assertSame(6, $baru->label()->count());
        $this->assertTrue(Akses::bolehKelola($this->faris, $baru));

        $kartu = Kartu::where('board_id', $baru->id)->firstOrFail();
        $this->assertSame('SD Harapan', $kartu->judul);
        $this->assertSame($baru->id, $kartu->label->first()->board_id, 'label ikut board baru');
    }

    public function test_salin_board_tanpa_kartu(): void
    {
        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->set('namaSalinanBoard', 'Kosong')
            ->set('salinDenganKartu', false)
            ->call('salinBoard');

        $baru = Board::where('nama', 'Kosong')->firstOrFail();
        $this->assertSame(2, $baru->kolom()->count());
        $this->assertSame(0, Kartu::where('board_id', $baru->id)->count());
    }

    public function test_kartu_templat_dan_membuat_kartu_darinya(): void
    {
        $this->detail()->call('toggleTemplat');
        $this->assertTrue($this->kartu->fresh()->templat);

        $selesai = $this->board->kolom()->get()[1];
        $papan = Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])
            ->assertSee('Templat')
            ->call('dariTemplat', $this->kartu->id, $selesai->id);

        $baru = Kartu::where('kolom_id', $selesai->id)->firstOrFail();
        $this->assertFalse($baru->templat, 'kartu hasil templat bukan templat lagi');
        $this->assertSame('SD Harapan', $baru->judul);
        $this->assertSame(1, $baru->checklist()->count());
        $papan->assertSet('kartuId', $baru->id);

        $this->detail()->call('toggleTemplat');
        $this->assertFalse($this->kartu->fresh()->templat);
    }

    public function test_kartu_order_tidak_bisa_dijadikan_templat(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000123', 'nama' => 'TK Miftahul', 'cabang_id' => $this->faris->cabang_id]);
        $order = Order::create([
            'booking_code' => 'BK-SALIN-1',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $this->faris->id,
            'cabang_id' => $this->faris->cabang_id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'total' => 1000,
            'tanggal_booking' => now(),
        ]);
        $kartuOrder = Kartu::where('order_id', $order->id)->firstOrFail();

        $this->detail($kartuOrder->id)->call('toggleTemplat')->assertStatus(422);

        // Kartu biasa tetap boleh dijadikan templat.
        $this->detail()->call('toggleTemplat')->assertHasNoErrors();
    }
}
