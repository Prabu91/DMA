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

    public function test_varian_tambahan_menambah_harga_bukan_mengganti(): void
    {
        // Kasus Yearbook: varian "halaman" menentukan harga (mengganti),
        // varian "box" menambah di atasnya.
        $kategori = Kategori::create(['nama' => 'Yearbook', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Yearbook', 'harga' => 160000, 'status' => 'aktif']);
        $produk->opsi()->create(['tipe_opsi' => 'halaman', 'nilai_opsi' => '30 HALAMAN', 'harga_override' => 160000, 'is_wajib' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'halaman', 'nilai_opsi' => '60 HALAMAN', 'harga_override' => 320000, 'is_wajib' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'box', 'nilai_opsi' => 'BOX', 'harga_override' => 40000, 'is_tambahan' => true]);
        $produk->opsi()->create(['tipe_opsi' => 'box', 'nilai_opsi' => 'POP-UP', 'harga_override' => 30000, 'is_tambahan' => true]);
        $produk->refresh();

        $this->assertSame(160000, $produk->hargaSatuan([]));                          // harga dasar
        $this->assertSame(320000, $produk->hargaSatuan(['60 HALAMAN']));              // mengganti
        $this->assertSame(360000, $produk->hargaSatuan(['60 HALAMAN', 'BOX']));       // 320rb + 40rb
        $this->assertSame(350000, $produk->hargaSatuan(['60 HALAMAN', 'POP-UP']));    // 320rb + 30rb
        $this->assertSame(200000, $produk->hargaSatuan(['BOX']));                     // 160rb + 40rb

        // Katalog menampilkan angka yang sama dengan yang akan ditagih.
        Livewire::test(EtalaseDetail::class, ['konteks' => 'staf', 'tipe' => 'produk', 'id' => $produk->id])
            ->assertSee('Rp160.000')
            ->set('pilihan.halaman', '60 HALAMAN')
            ->set('pilihan.box', 'BOX')
            ->assertSee('Rp360.000')
            ->call('tambah')
            ->assertHasNoErrors();

        // ... dan keranjang menghitung angka yang sama.
        Livewire::test(Keranjang::class, ['konteks' => 'staf'])->assertSee('360.000');
    }

    public function test_pas_foto_membagi_jatah_pcs_ke_beberapa_ukuran(): void
    {
        // Satu harga per siswa; yang dikustom hanya pembagian pcs antar ukuran.
        $kategori = Kategori::create(['nama' => 'Pas Foto', 'pakai_desain' => false]);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Pas Foto SD/SMP/SMA',
            'harga' => 20000,
            'status' => 'aktif',
            'komposisi_ukuran' => true,
            'maks_pcs' => 20,
        ]);
        foreach (['2X3', '3X4', '4X6'] as $u) {
            $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => $u, 'is_wajib' => false]);
        }

        $comp = Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id]);

        // Ukuran tidak lagi tampil sebagai "pilih salah satu".
        $comp->assertSee('Bagi jatah maksimal 20 pcs')->assertDontSee('Opsi Ukuran');

        // Tanpa pcs sama sekali → ditolak.
        $comp->call('tambah')->assertHasErrors('komposisi');
        $this->assertSame(0, app(Cart::class)->count());

        $comp->call('ubahPcs', '2X3', 4)
            ->call('ubahPcs', '3X4', 2)
            ->assertSet('totalPcs', 6)
            ->call('tambah')
            ->assertHasNoErrors();

        $item = collect(app(Cart::class)->items())->first();
        $this->assertSame(['2X3' => 4, '3X4' => 2], $item['komposisi']);
        $this->assertSame('2X3 ×4 · 3X4 ×2', $item['opsi_ukuran']);

        // Harga tetap harga produk — komposisi tidak mengubahnya.
        $this->assertSame(20000, $produk->fresh()->hargaSatuan([]));
        Livewire::test(Keranjang::class, ['konteks' => 'publik'])->assertSee('20.000');
    }

    public function test_pas_foto_menolak_pcs_melebihi_jatah(): void
    {
        $kategori = Kategori::create(['nama' => 'Pas Foto', 'pakai_desain' => false]);
        $produk = Produk::create([
            'kategori_id' => $kategori->id, 'nama' => 'Pas Foto', 'harga' => 20000,
            'status' => 'aktif', 'komposisi_ukuran' => true, 'maks_pcs' => 5,
        ]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '2X3', 'is_wajib' => false]);
        $produk->opsi()->create(['tipe_opsi' => 'ukuran', 'nilai_opsi' => '3X4', 'is_wajib' => false]);

        $comp = Livewire::test(EtalaseDetail::class, ['konteks' => 'publik', 'tipe' => 'produk', 'id' => $produk->id]);

        // Tombol tambah berhenti tepat di batas, tidak melebihi.
        $comp->call('ubahPcs', '2X3', 10)->assertSet('totalPcs', 5);
        $comp->call('ubahPcs', '3X4', 3)->assertSet('totalPcs', 5);

        // Isian manual yang kelewat batas juga dipangkas.
        $comp->set('komposisi.2X3', 99)->assertSet('totalPcs', 5);
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
