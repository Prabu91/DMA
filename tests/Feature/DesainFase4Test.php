<?php

namespace Tests\Feature;

use App\Livewire\Katalog\DesainIndex;
use App\Models\Cabang;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DesainFase4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    private function kategoriDesain(string $nama = 'Wisuda'): Kategori
    {
        return Kategori::create(['nama' => $nama, 'pakai_desain' => true]);
    }

    public function test_marketing_ditolak_akses_desain(): void
    {
        $m = User::factory()->create();
        $m->assignRole('marketing');

        $this->actingAs($m)->get(route('desain.index'))->assertForbidden();
    }

    public function test_membuat_desain_dengan_foto(): void
    {
        Storage::fake('public');
        $kategori = $this->kategoriDesain();
        Livewire::actingAs($this->admin());

        Livewire::test(DesainIndex::class)
            ->call('create')
            ->set('kategori_id', $kategori->id)
            ->set('kode', 'ERP-001')
            ->set('orientasi', 'portrait')
            ->set('tahun_ajaran', '2025/2026')
            ->set('status', 'aktif')
            ->set('foto_preview', UploadedFile::fake()->image('d.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $d = Desain::where('kode', 'ERP-001')->firstOrFail();
        $this->assertSame($kategori->id, $d->kategori_id);
        $this->assertSame('portrait', $d->orientasi);
        $this->assertNotNull($d->foto_preview);
        Storage::disk('public')->assertExists($d->foto_preview);
    }

    public function test_kode_harus_unik(): void
    {
        $kategori = $this->kategoriDesain();
        Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-001', 'tahun_ajaran' => '2025/2026', 'status' => 'aktif']);

        Livewire::actingAs($this->admin());

        Livewire::test(DesainIndex::class)
            ->call('create')
            ->set('kategori_id', $kategori->id)
            ->set('kode', 'ERP-001')
            ->set('tahun_ajaran', '2025/2026')
            ->set('status', 'aktif')
            ->call('save')
            ->assertHasErrors(['kode']);
    }

    public function test_kategori_tanpa_desain_ditolak(): void
    {
        $kategoriTanpaDesain = Kategori::create(['nama' => 'Pas Foto', 'pakai_desain' => false]);
        Livewire::actingAs($this->admin());

        Livewire::test(DesainIndex::class)
            ->call('create')
            ->set('kategori_id', $kategoriTanpaDesain->id)
            ->set('kode', 'XYZ-001')
            ->set('tahun_ajaran', '2025/2026')
            ->set('status', 'aktif')
            ->call('save')
            ->assertHasErrors(['kategori_id']);
    }

    public function test_filter_per_kategori(): void
    {
        $wisuda = $this->kategoriDesain('Wisuda');
        $manasik = $this->kategoriDesain('Manasik');
        Desain::create(['kategori_id' => $wisuda->id, 'kode' => 'ERP-001', 'tahun_ajaran' => '2025/2026', 'status' => 'aktif']);
        Desain::create(['kategori_id' => $manasik->id, 'kode' => 'IND-001', 'tahun_ajaran' => '2025/2026', 'status' => 'aktif']);

        Livewire::actingAs($this->admin());

        Livewire::test(DesainIndex::class)
            ->set('filterKategori', $wisuda->id)
            ->assertSee('ERP-001')
            ->assertDontSee('IND-001');
    }

    public function test_desain_dipakai_order_tidak_bisa_dihapus(): void
    {
        $kategori = $this->kategoriDesain();
        $desain = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-001', 'tahun_ajaran' => '2025/2026', 'status' => 'aktif']);

        $cabang = Cabang::create(['nama' => 'DMA A', 'kode_area' => 'A']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-A-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);
        $order = Order::create(['booking_code' => 'BK-1', 'sekolah_id' => $sekolah->id, 'cabang_id' => $cabang->id]);
        $order->items()->create(['tipe_item' => 'desain', 'desain_id' => $desain->id, 'qty' => 1, 'harga' => 0]);

        Livewire::actingAs($this->admin());

        Livewire::test(DesainIndex::class)
            ->call('delete', $desain->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'tidak bisa dihapus'));

        $this->assertDatabaseHas('desain', ['id' => $desain->id]);
    }
}
