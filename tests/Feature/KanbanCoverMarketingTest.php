<?php

namespace Tests\Feature;

use App\Livewire\Kanban\PapanBoard;
use App\Livewire\PengaturanIndex;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Kanban\CoverMarketing;
use App\Support\Kanban\Akses;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Thumbnail bawaan marketing yang otomatis jadi cover kartu order. */
class KanbanCoverMarketingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rudi;

    private User $anisa;

    private Cabang $cabang;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_merge(Akses::PERAN_STAF, ['marketing']) as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->admin = User::factory()->create(['nama' => 'Super', 'cabang_id' => $this->cabang->id]);
        $this->admin->assignRole('super_admin');
        $this->rudi = User::factory()->create(['nama' => 'Rudi Setiawan', 'cabang_id' => $this->cabang->id]);
        $this->rudi->assignRole('marketing');
        $this->anisa = User::factory()->create(['nama' => 'Anisa', 'cabang_id' => $this->cabang->id]);
        $this->anisa->assignRole('marketing');

        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-000032', 'nama' => 'RA MADANI', 'alamat' => 'Jl. Contoh 1',
            'kota' => 'Bandung', 'cabang_id' => $this->cabang->id,
        ]);
    }

    private function order(?User $marketing): Order
    {
        return Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $marketing?->id,
            'cabang_id' => $this->cabang->id,
            'status' => OrderStatus::BARU,
            'total' => 90000,
            'tanggal_event' => now()->addDays(10)->toDateString(),
        ]);
    }

    private function beriCover(User $marketing): void
    {
        app(CoverMarketing::class)->simpan($marketing, UploadedFile::fake()->image('bandung-rudi.jpg', 1400, 900));
    }

    public function test_admin_mengunggah_thumbnail_marketing(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)->test(PengaturanIndex::class)
            ->set('coverMarketing.'.$this->rudi->id, UploadedFile::fake()->image('rudi.jpg', 1400, 900));

        $path = $this->rudi->fresh()->kanban_cover_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_thumbnail_bisa_dihapus_lagi(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);
        $path = $this->rudi->fresh()->kanban_cover_path;

        Livewire::actingAs($this->admin)->test(PengaturanIndex::class)
            ->call('hapusCoverMarketing', $this->rudi->id);

        $this->assertNull($this->rudi->fresh()->kanban_cover_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_hanya_gambar_yang_diterima(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin)->test(PengaturanIndex::class)
            ->set('coverMarketing.'.$this->rudi->id, UploadedFile::fake()->create('rencana.pdf', 100))
            ->assertSet('error', 'Thumbnail harus berupa gambar.');

        $this->assertNull($this->rudi->fresh()->kanban_cover_path);
    }

    public function test_kartu_order_baru_memakai_thumbnail_marketingnya(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);

        $order = $this->order($this->rudi);
        $kartu = Kartu::where('order_id', $order->id)->firstOrFail();

        $this->assertSame($this->rudi->id, $kartu->cover_marketing_id);
        $this->assertTrue($kartu->cover_penuh, 'tampil sebagai cover penuh seperti di Trello');
        $this->assertStringContainsString('/cover-marketing/'.$this->rudi->id, (string) $kartu->coverUrl());
    }

    public function test_marketing_tanpa_thumbnail_tidak_memberi_cover(): void
    {
        Storage::fake('local');

        $order = $this->order($this->anisa);
        $kartu = Kartu::where('order_id', $order->id)->firstOrFail();

        $this->assertNull($kartu->cover_marketing_id);
        $this->assertNull($kartu->coverUrl());
    }

    public function test_ganti_marketing_menukar_thumbnailnya(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);
        $this->beriCover($this->anisa);

        $order = $this->order($this->rudi);
        $kartu = Kartu::where('order_id', $order->id)->firstOrFail();
        $this->assertSame($this->rudi->id, $kartu->cover_marketing_id);

        $order->update(['marketing_id' => $this->anisa->id]);

        $this->assertSame($this->anisa->id, $kartu->fresh()->cover_marketing_id);
    }

    public function test_cover_pilihan_sendiri_tidak_ditimpa(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);
        $this->beriCover($this->anisa);

        $order = $this->order($this->rudi);
        $kartu = Kartu::where('order_id', $order->id)->firstOrFail();
        $kartu->update(['cover_marketing_id' => null, 'cover_warna' => 'hijau']);

        $order->update(['marketing_id' => $this->anisa->id]);

        $kartu->refresh();
        $this->assertSame('hijau', $kartu->cover_warna);
        $this->assertNull($kartu->cover_marketing_id);
    }

    public function test_gambar_disajikan_lewat_route_khusus(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);

        $this->actingAs($this->admin)
            ->get(route('kanban.cover-marketing', ['user' => $this->rudi->id]))
            ->assertOk()
            ->assertHeader('cache-control', 'max-age=604800, private');

        $this->actingAs($this->admin)
            ->get(route('kanban.cover-marketing', ['user' => $this->anisa->id]))
            ->assertNotFound();
    }

    public function test_halaman_pengaturan_menampilkan_daftar_marketing(): void
    {
        Livewire::actingAs($this->admin)->test(PengaturanIndex::class)
            ->assertSee('Thumbnail marketing')
            ->assertSee('Rudi Setiawan')
            ->assertSee('Anisa');
    }

    public function test_board_order_menampilkan_cover_marketing(): void
    {
        Storage::fake('local');
        $this->beriCover($this->rudi);
        $order = $this->order($this->rudi);

        Livewire::actingAs($this->admin)
            ->test(PapanBoard::class, ['board' => Board::order()])
            ->assertSeeHtml('/cover-marketing/'.$this->rudi->id);

        $this->assertNotNull(Kartu::where('order_id', $order->id)->value('cover_marketing_id'));
    }
}
