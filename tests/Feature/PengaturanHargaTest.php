<?php

namespace Tests\Feature;

use App\Livewire\Booking\Keranjang;
use App\Livewire\Katalog\EtalaseDetail;
use App\Livewire\PengaturanIndex;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\Cart;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PengaturanHargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function produk(): Produk
    {
        $kategori = Kategori::create(['nama' => 'Foto Kelas', 'pakai_desain' => false]);

        return Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Foto Kelas A',
            'harga' => 25000,
            'status' => 'aktif',
        ]);
    }

    public function test_harga_terlihat_tamu_saat_saklar_mati(): void
    {
        $this->produk();

        $this->get(route('storefront.katalog.index'))
            ->assertOk()
            ->assertSee('25.000');
    }

    public function test_harga_disembunyikan_dari_tamu_saat_saklar_menyala(): void
    {
        $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        $this->get(route('storefront.katalog.index'))
            ->assertOk()
            ->assertDontSee('25.000')
            ->assertDontSee('Masuk untuk lihat harga'); // disembunyikan, tanpa teks pengganti
    }

    public function test_satuan_ikut_tersembunyi_di_detail_produk(): void
    {
        // "/ item" tanpa angka di depannya terlihat seperti tampilan rusak.
        $produk = $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        $this->get(route('storefront.katalog.detail', ['tipe' => 'produk', 'id' => $produk->id]))
            ->assertOk()
            ->assertDontSee('25.000')
            ->assertDontSee('/ item');
    }

    public function test_staf_yang_sudah_masuk_tetap_melihat_harga(): void
    {
        $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        $staf = User::factory()->create();
        $staf->assignRole('marketing');

        $this->actingAs($staf)->get(route('app.etalase.index'))
            ->assertOk()
            ->assertSee('25.000');
    }

    public function test_keranjang_tamu_ikut_menyembunyikan_harga(): void
    {
        // Kalau keranjang tetap menampilkan harga, menyembunyikannya di katalog
        // jadi percuma — tamu tinggal memasukkan barang lalu membaca angkanya.
        $produk = $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        app(Cart::class)->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 2]);

        Livewire::test(Keranjang::class, ['konteks' => 'publik'])
            ->assertDontSee('25.000')   // harga satuan
            ->assertDontSee('50.000')   // subtotal
            ->assertDontSee('/item');   // satuan ikut hilang, tidak menggantung
    }

    public function test_sekolah_yang_sudah_masuk_tetap_melihat_harga_di_keranjang(): void
    {
        $produk = $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        $cabang = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);

        app(Cart::class)->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 2]);

        Livewire::actingAs($sekolah, 'sekolah')
            ->test(Keranjang::class, ['konteks' => 'sekolah'])
            ->assertSee('50.000');
    }

    public function test_tamu_diarahkan_masuk_alih_alih_mengisi_keranjang(): void
    {
        // Keranjang berharga kosong tidak ada gunanya; pengunjung diminta masuk dulu.
        $produk = $this->produk();
        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, true);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->call('tambah')
            ->assertRedirect(route('sekolah.masuk'));

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_tamu_tetap_bisa_menambah_keranjang_saat_saklar_mati(): void
    {
        // Perilaku lama tidak berubah bila harga memang boleh dilihat publik.
        $produk = $this->produk();

        Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id])
            ->call('tambah')
            ->assertNoRedirect()
            ->assertSet('justAdded', true);

        $this->assertSame(1, app(Cart::class)->count());
    }

    public function test_admin_bisa_menyalakan_saklar_dari_halaman_pengaturan(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->assertFalse(Pengaturan::bool(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN));

        Livewire::actingAs($admin)
            ->test(PengaturanIndex::class)
            ->set('hargaPublikDisembunyikan', true);

        $this->assertTrue(Pengaturan::bool(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN));
    }

    public function test_marketing_ditolak_membuka_pengaturan(): void
    {
        $marketing = User::factory()->create();
        $marketing->assignRole('marketing');

        $this->actingAs($marketing)->get(route('app.pengaturan'))->assertForbidden();
    }
}
