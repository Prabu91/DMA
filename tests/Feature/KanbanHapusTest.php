<?php

namespace Tests\Feature;

use App\Livewire\Kanban\Beranda;
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
use App\Services\Kanban\Kabar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Hapus permanen: board, list, dan kartu. */
class KanbanHapusTest extends TestCase
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

        $cl = Checklist::create(['kartu_id' => $this->kartu->id, 'judul' => 'QC', 'posisi' => 1]);
        ChecklistItem::create(['checklist_id' => $cl->id, 'teks' => 'Foto kelas', 'posisi' => 1]);
        Komentar::create(['kartu_id' => $this->kartu->id, 'user_id' => $this->faris->id, 'isi' => 'Halo']);
    }

    private function detail(?int $kartuId = null)
    {
        return Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $kartuId ?? $this->kartu->id]);
    }

    private function papan()
    {
        return Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board]);
    }

    // ---------------- Kartu ----------------

    public function test_kartu_bisa_dihapus_langsung_tanpa_diarsipkan_dulu(): void
    {
        Storage::fake('local');
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 10, 10)]);
        $lampiran = Lampiran::firstOrFail();

        $this->detail()->assertSee('Hapus kartu')->call('hapus')->assertDispatched('kartu-ditutup');

        $this->assertDatabaseMissing('kanban_kartu', ['id' => $this->kartu->id]);
        $this->assertSame(0, Komentar::count(), 'komentar ikut terhapus');
        $this->assertSame(0, ChecklistItem::count(), 'checklist ikut terhapus');
        Storage::disk('local')->assertMissing($lampiran->path);
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kartu_dihapus', 'keterangan' => 'SD Harapan']);
    }

    public function test_kartu_yang_diarsipkan_juga_bisa_dihapus(): void
    {
        $this->detail()->call('arsipkan');

        $this->detail()->assertSee('Hapus permanen')->call('hapus');

        $this->assertDatabaseMissing('kanban_kartu', ['id' => $this->kartu->id]);
    }

    public function test_kartu_order_tetap_tidak_bisa_dihapus(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000123', 'nama' => 'TK Miftahul', 'cabang_id' => $this->faris->cabang_id]);
        $order = Order::create([
            'booking_code' => 'BK-HAPUS-1',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $this->faris->id,
            'cabang_id' => $this->faris->cabang_id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'total' => 1000,
            'tanggal_booking' => now(),
        ]);
        $kartuOrder = Kartu::where('order_id', $order->id)->firstOrFail();

        $this->detail($kartuOrder->id)->assertDontSee('Hapus kartu')->call('hapus')->assertStatus(422);
        $this->assertModelExists($kartuOrder);
    }

    public function test_yang_hanya_boleh_membaca_tidak_bisa_menghapus_kartu(): void
    {
        $tamu = User::factory()->create(['nama' => 'Tamu']);
        $tamu->assignRole('tim_event');

        Livewire::actingAs($tamu)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->call('hapus')->assertForbidden();
        $this->assertModelExists($this->kartu);
    }

    // ---------------- List ----------------

    public function test_list_kosong_bisa_dihapus(): void
    {
        $kosong = app(Tata::class)->tambahKolom($this->board, 'List kosong', $this->faris);

        $this->papan()->call('hapusKolom', $kosong->id)->assertHasNoErrors();

        $this->assertDatabaseMissing('kanban_kolom', ['id' => $kosong->id]);
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kolom_dihapus', 'keterangan' => 'List kosong']);
    }

    public function test_list_berisi_kartu_ikut_menghapus_kartunya(): void
    {
        Storage::fake('local');
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 10, 10)]);
        $lampiran = Lampiran::firstOrFail();
        $arsip = app(Tata::class)->tambahKartu($this->todo, 'Kartu arsip', $this->faris);
        $arsip->update(['diarsipkan_at' => now()]);

        $this->papan()
            ->call('hapusKolom', $this->todo->id)
            ->assertSee('2 kartunya dihapus permanen');

        $this->assertDatabaseMissing('kanban_kolom', ['id' => $this->todo->id]);
        $this->assertSame(0, Kartu::count(), 'kartu aktif & arsip ikut terhapus');
        $this->assertSame(0, Komentar::count());
        Storage::disk('local')->assertMissing($lampiran->path);
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kolom_dihapus', 'keterangan' => 'To do (2 kartu)']);
    }

    public function test_list_berisi_kartu_order_ditolak(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000555', 'nama' => 'TK Order', 'cabang_id' => $this->faris->cabang_id]);
        $order = Order::create([
            'booking_code' => 'BK-HAPUS-2',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $this->faris->id,
            'cabang_id' => $this->faris->cabang_id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'total' => 1000,
            'tanggal_booking' => now(),
        ]);
        $kartuOrder = Kartu::where('order_id', $order->id)->firstOrFail();
        $board = Board::find($kartuOrder->board_id);

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $board])
            ->call('hapusKolom', $kartuOrder->kolom_id)
            ->assertSee('berisi kartu order');

        $this->assertModelExists($kartuOrder);
    }

    // ---------------- Board ----------------

    public function test_board_harus_diarsipkan_dulu_sebelum_dihapus(): void
    {
        $this->papan()->call('hapusBoard')->assertStatus(422);
        $this->assertModelExists($this->board);

        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board])->call('arsipkanBoard');
        Livewire::actingAs($this->faris)->test(PapanBoard::class, ['board' => $this->board->fresh()])
            ->assertSee('Hapus board permanen')
            ->call('hapusBoard')
            ->assertRedirect(route('kanban.beranda'));

        $this->assertDatabaseMissing('kanban_board', ['id' => $this->board->id]);
    }

    public function test_hapus_board_membawa_serta_seluruh_isinya(): void
    {
        Storage::fake('local');
        $this->detail()->set('berkas', [UploadedFile::fake()->image('foto.jpg', 10, 10)]);
        $lampiran = Lampiran::firstOrFail();
        app(Kabar::class)->ditugaskan($this->kartu, $this->faris, $this->faris);
        $this->board->update(['diarsipkan_at' => now()]);

        Livewire::actingAs($this->faris)->test(Beranda::class)
            ->set('lihatArsip', true)
            ->call('hapus', $this->board->id)
            ->assertSee('dihapus permanen');

        $this->assertDatabaseMissing('kanban_board', ['id' => $this->board->id]);
        $this->assertSame(0, Kolom::count());
        $this->assertSame(0, Kartu::count());
        $this->assertSame(0, Komentar::count());
        $this->assertSame(0, Lampiran::count());
        Storage::disk('local')->assertMissing($lampiran->path);
    }

    public function test_hapus_board_membersihkan_notifikasi_yang_menunjuk_board_itu(): void
    {
        $rekan = User::factory()->create(['nama' => 'Rekan']);
        $rekan->assignRole('editor');
        $this->board->anggota()->attach($rekan->id, ['peran' => 'anggota']);
        app(Kabar::class)->ditugaskan($this->kartu, $rekan, $this->faris);
        $this->assertSame(1, $rekan->unreadNotifications()->count());

        $this->board->update(['diarsipkan_at' => now()]);
        Livewire::actingAs($this->faris)->test(Beranda::class)->call('hapus', $this->board->id);

        $this->assertSame(0, $rekan->fresh()->unreadNotifications()->count());
    }

    public function test_board_order_tidak_bisa_dihapus(): void
    {
        $admin = User::factory()->create(['nama' => 'Super']);
        $admin->assignRole('super_admin');
        $order = Board::order();
        $order->update(['diarsipkan_at' => now()]);

        Livewire::actingAs($admin)->test(Beranda::class)->call('hapus', $order->id)->assertStatus(422);
        $this->assertModelExists($order);
    }

    public function test_yang_bukan_pengelola_tidak_bisa_menghapus_board(): void
    {
        $lain = User::factory()->create(['nama' => 'Lain']);
        $lain->assignRole('editor');
        $this->board->anggota()->attach($lain->id, ['peran' => 'anggota']);
        $this->board->update(['diarsipkan_at' => now()]);

        Livewire::actingAs($lain)->test(Beranda::class)->call('hapus', $this->board->id)->assertForbidden();
        $this->assertModelExists($this->board);
    }
}
