<?php

namespace Tests\Feature;

use App\Livewire\Katalog\ProdukForm;
use App\Livewire\Katalog\ProdukIndex;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProdukFase2Test extends TestCase
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

    private function kategori(): Kategori
    {
        return Kategori::create(['nama' => 'Foto Kelas', 'pakai_desain' => false]);
    }

    public function test_marketing_ditolak_akses_produk(): void
    {
        $m = User::factory()->create();
        $m->assignRole('marketing');

        $this->actingAs($m)->get(route('produk.index'))->assertForbidden();
    }

    public function test_membuat_produk_dengan_opsi_bonus_dan_foto(): void
    {
        Storage::fake('public');
        $kategori = $this->kategori();
        $bonusProduk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan', 'harga' => 5000, 'status' => 'aktif']);

        Livewire::actingAs($this->admin());

        Livewire::test(ProdukForm::class)
            ->set('kategori_id', $kategori->id)
            ->set('nama', 'Foto 10R')
            ->set('gaya', 'MINIMALIS')
            ->set('harga', 35000)
            ->set('status', 'aktif')
            ->set('foto', UploadedFile::fake()->image('f.jpg'))
            ->call('addOpsi')
            ->set('opsi.0.nilai_opsi', '10RP')
            ->set('opsi.0.harga_override', 40000)
            ->set('opsi.0.is_wajib', true)
            ->call('addBonus')
            ->set('bonus.0.bonus_produk_id', $bonusProduk->id)
            ->set('bonus.0.qty', 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('produk.index'));

        $p = Produk::where('nama', 'Foto 10R')->firstOrFail();
        $this->assertSame($kategori->id, $p->kategori_id);
        $this->assertSame('MINIMALIS', $p->gaya);
        $this->assertSame(35000, $p->harga);

        $this->assertNotNull($p->foto);
        Storage::disk('public')->assertExists($p->foto);

        $this->assertCount(1, $p->opsi);
        $this->assertSame('10RP', $p->opsi->first()->nilai_opsi);
        $this->assertSame('ukuran', $p->opsi->first()->tipe_opsi);
        $this->assertSame(40000, $p->opsi->first()->harga_override);
        $this->assertTrue((bool) $p->opsi->first()->is_wajib);

        $this->assertCount(1, $p->bonus);
        $this->assertSame($bonusProduk->id, $p->bonus->first()->bonus_produk_id);
        $this->assertSame(2, $p->bonus->first()->qty);
    }

    public function test_validasi_produk_menolak_data_kosong(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(ProdukForm::class)
            ->set('nama', '')
            ->call('save')
            ->assertHasErrors(['kategori_id', 'nama']);
    }

    public function test_edit_produk_sinkron_opsi(): void
    {
        $kategori = $this->kategori();
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'P', 'harga' => 1000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '8R', 'is_wajib' => false]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => false]);

        Livewire::actingAs($this->admin());

        Livewire::test(ProdukForm::class, ['produk' => $produk])
            ->assertSet('nama', 'P')
            ->call('removeOpsi', 1) // sisakan satu
            ->call('save')
            ->assertHasNoErrors();

        $this->assertCount(1, $produk->fresh()->opsi);
        $this->assertSame('8R', $produk->fresh()->opsi->first()->nilai_opsi);
    }

    public function test_produk_dipakai_paket_tidak_bisa_dihapus(): void
    {
        $kategori = $this->kategori();
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'P', 'harga' => 1000, 'status' => 'aktif']);
        $paket = Paket::create(['nama' => 'Paket A', 'harga' => 5000, 'status' => 'aktif']);
        $paket->produk()->attach($produk->id);

        Livewire::actingAs($this->admin());

        Livewire::test(ProdukIndex::class)
            ->call('delete', $produk->id)
            ->assertSet('error', fn ($v) => is_string($v) && str_contains($v, 'tidak bisa dihapus'));

        $this->assertDatabaseHas('produk', ['id' => $produk->id]);
    }
}
