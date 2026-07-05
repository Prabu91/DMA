<?php

namespace Tests\Feature;

use App\Livewire\Katalog\Etalase;
use App\Livewire\Katalog\EtalaseDetail;
use App\Models\Cabang;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EtalaseFase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'area', 'marketing', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function user(string $role): User
    {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    }

    // ---------- Akses ----------

    public function test_marketing_bisa_akses_etalase_staf(): void
    {
        $this->actingAs($this->user('marketing'))->get(route('etalase.index'))->assertOk();
    }

    public function test_editor_tidak_bisa_akses_etalase(): void
    {
        $this->actingAs($this->user('editor'))->get(route('etalase.index'))->assertForbidden();
    }

    public function test_sekolah_bisa_akses_katalog(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);

        $this->actingAs($sekolah, 'sekolah')->get(route('sekolah.katalog.index'))->assertOk();
    }

    public function test_staf_tidak_bisa_akses_katalog_sekolah(): void
    {
        $this->actingAs($this->user('marketing'))
            ->get(route('sekolah.katalog.index'))
            ->assertRedirect(route('sekolah.login'));
    }

    // ---------- Etalase ----------

    public function test_etalase_hanya_menampilkan_item_aktif(): void
    {
        $kategori = Kategori::create(['nama' => 'Foto Kelas', 'pakai_desain' => false]);
        Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Produk Aktif', 'harga' => 1000, 'status' => 'aktif']);
        Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Produk Nonaktif', 'harga' => 1000, 'status' => 'nonaktif']);
        Paket::create(['nama' => 'Paket Aktif', 'harga' => 5000, 'status' => 'aktif']);
        Paket::create(['nama' => 'Paket Nonaktif', 'harga' => 5000, 'status' => 'nonaktif']);

        Livewire::test(Etalase::class, ['konteks' => 'staf'])
            ->assertSee('Produk Aktif')
            ->assertDontSee('Produk Nonaktif')
            ->assertSee('Paket Aktif')
            ->assertDontSee('Paket Nonaktif');
    }

    // ---------- Detail: desain pool + opsi ----------

    public function test_detail_produk_menampilkan_pool_desain_dan_opsi(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Foto Wisuda', 'harga' => 50000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => true]);

        Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-2026', 'tahun_ajaran' => '2026/2027', 'status' => 'aktif']);
        Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-2025', 'tahun_ajaran' => '2025/2026', 'status' => 'aktif']);
        Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-OFF', 'tahun_ajaran' => '2026/2027', 'status' => 'nonaktif']);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSet('tahunAjaran', '2026/2027')   // tahun terbaru = default aktif
            ->assertSee('10RP')
            ->assertSee('Wajib')
            ->assertSee('ERP-2026')                    // desain tahun aktif
            ->assertDontSee('ERP-2025')                // tahun lain tersembunyi
            ->assertDontSee('ERP-OFF')                 // desain nonaktif tersembunyi
            ->set('tahunAjaran', '2025/2026')
            ->assertSee('ERP-2025')
            ->assertDontSee('ERP-2026');
    }

    public function test_detail_paket_menampilkan_produk_termasuk(): void
    {
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);
        $a = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Produk A', 'harga' => 1000, 'status' => 'aktif']);
        $paket = Paket::create(['nama' => 'Paket Hemat', 'harga' => 5000, 'status' => 'aktif']);
        $paket->produk()->attach($a->id);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'sekolah', 'tipe' => 'paket', 'id' => $paket->id])
            ->assertSee('Paket Hemat')
            ->assertSee('Produk A');
    }
}
