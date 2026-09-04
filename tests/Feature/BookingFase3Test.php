<?php

namespace Tests\Feature;

use App\Livewire\Booking\Keranjang;
use App\Livewire\Katalog\EtalaseDetail;
use App\Models\Cabang;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingFase3Test extends TestCase
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

    // ---------- Cart engine ----------

    public function test_cart_menggabung_item_identik_dan_memisah_yang_beda(): void
    {
        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => 1, 'desain_id' => 2, 'opsi_ukuran' => '10RP', 'qty' => 1]);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => 1, 'desain_id' => 2, 'opsi_ukuran' => '10RP', 'qty' => 2]); // merge → 3
        $cart->add(['tipe_item' => 'produk', 'produk_id' => 1, 'desain_id' => 2, 'opsi_ukuran' => '12RP', 'qty' => 1]); // distinct

        $this->assertCount(2, $cart->items());
        $this->assertSame(4, $cart->count());
    }

    // ---------- Add to cart (validasi) ----------

    public function test_produk_berdesain_wajib_pilih_desain_dan_ukuran(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Foto Wisuda', 'harga' => 50000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => true]);
        $desain = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-001', 'tahun_ajaran' => '2026/2027', 'status' => 'aktif']);
        $desain->products()->attach($produk->id, ['ukuran' => null]); // ditempel ke produk (semua ukuran)

        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id])
            ->call('tambah')
            ->assertHasErrors(['selectedDesain', 'pilihan.ukuran']);

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_tambah_produk_berhasil_setelah_lengkap(): void
    {
        $kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Foto Wisuda', 'harga' => 50000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => true]);
        $desain = Desain::create(['kategori_id' => $kategori->id, 'kode' => 'ERP-001', 'tahun_ajaran' => '2026/2027', 'status' => 'aktif']);

        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id])
            ->set('selectedDesain', $desain->id)
            ->set('pilihan.ukuran', '10RP')
            ->set('qty', 3)
            ->call('tambah')
            ->assertHasNoErrors()
            ->assertSet('justAdded', true);

        $this->assertSame(3, app(Cart::class)->count());
    }

    public function test_dua_tipe_varian_dipilih_terpisah_dan_tersimpan_di_cart(): void
    {
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Foto Manasik', 'harga' => 20000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'box', 'nilai_opsi' => 'TANPA BOX', 'is_wajib' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'box', 'nilai_opsi' => 'DEPAN BELAKANG', 'is_wajib' => true, 'harga_override' => 35000]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'is_wajib' => true]);

        $comp = Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id]);

        // Tiap tipe varian tampil sebagai kartu sendiri, judulnya ikut nama varian.
        $comp->assertSee('Opsi Box')->assertSee('Opsi Ukuran');

        // Keduanya wajib -> harus dipilih sendiri-sendiri.
        $comp->call('tambah')->assertHasErrors(['pilihan.box', 'pilihan.ukuran']);
        $this->assertSame(0, app(Cart::class)->count());

        // Baru satu yang dipilih -> yang lain tetap diprotes.
        $comp->set('pilihan.box', 'DEPAN BELAKANG')
            ->call('tambah')
            ->assertHasErrors(['pilihan.ukuran'])
            ->assertHasNoErrors(['pilihan.box']);

        $comp->set('pilihan.ukuran', '10RP')->call('tambah')->assertHasNoErrors();

        $item = collect(app(Cart::class)->items())->first();
        $this->assertSame(['box' => 'DEPAN BELAKANG', 'ukuran' => '10RP'], $item['opsi']);
        $this->assertSame('DEPAN BELAKANG · 10RP', $item['opsi_ukuran']); // snapshot utk order
    }

    // ---------- Keranjang: harga efektif & subtotal ----------

    public function test_keranjang_hitung_harga_override_dan_subtotal(): void
    {
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Cetak', 'harga' => 10000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '10RP', 'harga_override' => 15000, 'is_wajib' => false]);

        app(Cart::class)->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'opsi_ukuran' => '10RP', 'qty' => 2]);

        Livewire::test(Keranjang::class, ['konteks' => 'staf'])
            ->assertSee('30.000');  // 15.000 override × 2
    }

    public function test_jumlah_siswa_tersimpan_di_cart(): void
    {
        Livewire::test(Keranjang::class, ['konteks' => 'staf'])
            ->set('jumlahSiswa', 50);

        $this->assertSame(50, app(Cart::class)->jumlahSiswa());
    }

    // ---------- Jalur ----------

    public function test_marketing_hanya_lihat_sekolah_cabangnya(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'Sekolah Jakarta', 'cabang_id' => $jkt->id]);
        Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'Sekolah Bandung', 'cabang_id' => $bdg->id]);

        $marketing = User::factory()->create(['cabang_id' => $jkt->id]);
        $marketing->assignRole('marketing');
        Livewire::actingAs($marketing);

        Livewire::test(Keranjang::class, ['konteks' => 'staf'])
            ->assertSet('sekolahId', null)
            ->assertSee('Sekolah Jakarta')
            ->assertDontSee('Sekolah Bandung');
    }

    public function test_jalur_sekolah_konteks_mandiri(): void
    {
        $cabang = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SDN Merdeka', 'cabang_id' => $cabang->id]);

        Livewire::actingAs($sekolah, 'sekolah');

        Livewire::test(Keranjang::class, ['konteks' => 'sekolah'])
            ->assertSee('SDN Merdeka')
            ->assertSee('Booking mandiri');
    }
}
