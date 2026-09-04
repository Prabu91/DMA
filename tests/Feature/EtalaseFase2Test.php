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
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'editor'] as $r) {
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
        $this->actingAs($this->user('marketing'))->get(route('app.etalase.index'))->assertOk();
    }

    public function test_editor_tidak_bisa_akses_etalase(): void
    {
        $this->actingAs($this->user('editor'))->get(route('app.etalase.index'))->assertForbidden();
    }

    public function test_sekolah_bisa_akses_katalog(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);
        $sekolah->markEmailAsVerified();

        $this->actingAs($sekolah, 'sekolah')->get(route('sekolah.katalog.index'))->assertOk();
    }

    public function test_staf_tidak_bisa_akses_katalog_sekolah(): void
    {
        $this->actingAs($this->user('marketing'))
            ->get(route('sekolah.katalog.index'))
            ->assertRedirect(route('sekolah.masuk'));
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

    /** Buat desain + tempel ke produk (pivot) dgn ukuran. $attach = [produkId => [sizes]|null]. */
    private function desain(Kategori $kat, string $kode, array $attach, string $tahun = '2026/2027', string $status = 'aktif'): Desain
    {
        $d = Desain::create(['kategori_id' => $kat->id, 'kode' => $kode, 'tahun_ajaran' => $tahun, 'status' => $status]);
        foreach ($attach as $produkId => $ukuran) {
            $d->products()->attach($produkId, ['ukuran' => $ukuran ? json_encode($ukuran) : null]);
        }

        return $d;
    }

    public function test_detail_produk_menampilkan_pool_desain_dan_opsi(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Foto Wisuda', 'harga' => 50000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => true]);

        $this->desain($kategori, 'ERP-2026', [$produk->id => null], '2026/2027');
        $this->desain($kategori, 'ERP-2025', [$produk->id => null], '2025/2026');
        $this->desain($kategori, 'ERP-OFF', [$produk->id => null], '2026/2027', 'nonaktif');

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

    public function test_desain_difilter_oleh_ukuran_terpilih(): void
    {
        $kategori = Kategori::create(['nama' => 'Free Sekolah', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Free', 'harga' => 0, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '8R', 'is_wajib' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10R', 'is_wajib' => true]);

        $this->desain($kategori, 'SINGLE-8R', [$produk->id => ['8R']]);
        $this->desain($kategori, 'KOLASE-10R', [$produk->id => ['10R']]);
        $this->desain($kategori, 'UMUM', [$produk->id => null]); // semua ukuran

        $comp = Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id]);

        // Pilih 8R → hanya desain 8R + desain tanpa label ukuran.
        $comp->set('pilihan.ukuran', '8R')
            ->assertSee('SINGLE-8R')
            ->assertSee('UMUM')
            ->assertDontSee('KOLASE-10R');

        // Ganti ke 10R → hanya desain 10R + tanpa label.
        $comp->set('pilihan.ukuran', '10R')
            ->assertSee('KOLASE-10R')
            ->assertSee('UMUM')
            ->assertDontSee('SINGLE-8R');
    }

    public function test_desain_difilter_oleh_produk(): void
    {
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => true]);
        $hanging = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Hanging', 'harga' => 20000, 'status' => 'aktif']);
        $kalender = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Kalender', 'harga' => 30000, 'status' => 'aktif']);

        $this->desain($kategori, 'DSN-HANGING', [$hanging->id => null]);
        $this->desain($kategori, 'DSN-KALENDER', [$kalender->id => null]);
        $this->desain($kategori, 'DSN-UMUM', [$hanging->id => null, $kalender->id => null]); // dipakai kedua produk

        // Etalase produk Hanging → hanya desain Hanging + umum.
        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $hanging->id])
            ->assertSee('DSN-HANGING')
            ->assertSee('DSN-UMUM')
            ->assertDontSee('DSN-KALENDER');

        // Etalase produk Kalender → hanya desain Kalender + umum.
        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $kalender->id])
            ->assertSee('DSN-KALENDER')
            ->assertSee('DSN-UMUM')
            ->assertDontSee('DSN-HANGING');
    }

    public function test_ganti_ukuran_melepas_desain_tak_cocok(): void
    {
        $kategori = Kategori::create(['nama' => 'Free Sekolah', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Free', 'harga' => 0, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '8R', 'is_wajib' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10R', 'is_wajib' => true]);
        $d8 = $this->desain($kategori, 'SINGLE-8R', [$produk->id => ['8R']]);
        $this->desain($kategori, 'KOLASE-10R', [$produk->id => ['10R']]);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id])
            ->set('pilihan.ukuran', '8R')
            ->set('selectedDesain', $d8->id)
            ->set('pilihan.ukuran', '10R')      // desain 8R tak lagi cocok
            ->assertSet('selectedDesain', null);
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
