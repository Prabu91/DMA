<?php

namespace Tests\Feature;

use App\Livewire\Katalog\KategoriIndex;
use App\Livewire\Sekolah\SekolahIndex;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KatalogFase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing', 'area'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function user(string $role, ?int $cabangId = null): User
    {
        $u = User::factory()->create(['cabang_id' => $cabangId]);
        $u->assignRole($role);

        return $u;
    }

    // ---------- id_sekolah ----------

    public function test_generate_id_sekolah_format_dan_sekuens_per_cabang(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);

        $this->assertSame('SKL-JKT-0001', Sekolah::generateIdSekolah($jkt));

        Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'A', 'cabang_id' => $jkt->id]);
        $this->assertSame('SKL-JKT-0002', Sekolah::generateIdSekolah($jkt));

        // Sekuens terpisah per cabang.
        $this->assertSame('SKL-BDG-0001', Sekolah::generateIdSekolah($bdg));
    }

    // ---------- Kategori (katalog global) ----------

    public function test_marketing_tidak_bisa_akses_kategori(): void
    {
        $this->actingAs($this->user('marketing'))
            ->get(route('kategori.index'))
            ->assertForbidden();
    }

    public function test_operasional_bisa_membuat_kategori(): void
    {
        Livewire::actingAs($this->user('operasional'));

        Livewire::test(KategoriIndex::class)
            ->call('create')
            ->set('nama', 'Wisuda')
            ->set('pakai_desain', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('kategori', ['nama' => 'Wisuda', 'pakai_desain' => true]);
    }

    public function test_kategori_dipakai_tidak_bisa_dihapus(): void
    {
        $kategori = Kategori::create(['nama' => 'Pas Foto', 'pakai_desain' => false]);
        $kategori->produk()->create(['nama' => 'Produk X', 'harga' => 1000]);

        Livewire::actingAs($this->user('super_admin'));

        Livewire::test(KategoriIndex::class)
            ->call('delete', $kategori->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'tidak bisa dihapus'));

        $this->assertDatabaseHas('kategori', ['id' => $kategori->id]);
    }

    // ---------- Sekolah (per-cabang) ----------

    public function test_marketing_membuat_sekolah_cabang_auto_dan_id_tergenerate(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        Livewire::actingAs($this->user('marketing', $jkt->id));

        Livewire::test(SekolahIndex::class)
            ->call('create')
            ->set('nama', 'SDN 1 Merdeka')
            ->set('kota', 'Jakarta')
            ->call('save')
            ->assertHasNoErrors();

        $s = Sekolah::withoutGlobalScopes()->where('nama', 'SDN 1 Merdeka')->firstOrFail();
        $this->assertSame($jkt->id, $s->cabang_id);
        $this->assertSame('SKL-JKT-0001', $s->id_sekolah);
    }

    public function test_marketing_hanya_melihat_sekolah_cabangnya(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);

        Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'Sekolah Jakarta', 'cabang_id' => $jkt->id]);
        Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'Sekolah Bandung', 'cabang_id' => $bdg->id]);

        Livewire::actingAs($this->user('marketing', $jkt->id));

        Livewire::test(SekolahIndex::class)
            ->assertSee('Sekolah Jakarta')
            ->assertDontSee('Sekolah Bandung');
    }

    public function test_super_admin_bisa_memilih_cabang_saat_membuat_sekolah(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        Livewire::actingAs($this->user('super_admin'));

        Livewire::test(SekolahIndex::class)
            ->call('create')
            ->set('nama', 'SD Kenangan')
            ->set('cabang_id', $bdg->id)
            ->call('save')
            ->assertHasNoErrors();

        $s = Sekolah::withoutGlobalScopes()->where('nama', 'SD Kenangan')->firstOrFail();
        $this->assertSame($bdg->id, $s->cabang_id);
        $this->assertSame('SKL-BDG-0001', $s->id_sekolah);
    }
}
