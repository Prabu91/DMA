<?php

namespace Tests\Feature;

use App\Livewire\Booking\Review;
use App\Livewire\Booking\Riwayat;
use App\Models\AturanFreeSekolah;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\ProdukBonus;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingFase4Test extends TestCase
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

    private function kategori(): Kategori
    {
        return Kategori::firstOrCreate(['nama' => 'K'], ['pakai_desain' => false]);
    }

    private function produk(string $nama, int $harga): Produk
    {
        return Produk::create(['kategori_id' => $this->kategori()->id, 'nama' => $nama, 'harga' => $harga, 'status' => 'aktif']);
    }

    // ---------- Jalur marketing ----------

    public function test_jalur_marketing_simpan_order(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $marketing = User::factory()->create(['cabang_id' => $jkt->id]);
        $marketing->assignRole('marketing');
        $produk = $this->produk('Cetak', 10000);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 2]);
        $cart->setJumlahSiswa(30);
        $cart->setSekolahId($sekolah->id);

        Livewire::actingAs($marketing);
        Livewire::test(Review::class, ['konteks' => 'staf'])->set('tanggalEvent', now()->addWeek()->toDateString())->call('simpan');

        $order = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('marketing', $order->sumber);
        $this->assertSame($marketing->id, $order->marketing_id);
        $this->assertSame($jkt->id, $order->cabang_id);
        $this->assertSame($sekolah->id, $order->sekolah_id);
        $this->assertSame(20000, $order->total);
        $this->assertSame(30, $order->jumlah_siswa);
        $this->assertNotNull($order->booking_code); // jalur marketing: code langsung (Fase 6)
        $this->assertCount(1, $order->items);
        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    // ---------- Jalur sekolah ----------

    public function test_jalur_sekolah_simpan_order(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $produk = $this->produk('Cetak', 15000);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 1]);
        $cart->setJumlahSiswa(10);

        Livewire::actingAs($sekolah, 'sekolah');
        Livewire::test(Review::class, ['konteks' => 'sekolah'])->set('tanggalEvent', now()->addWeek()->toDateString())->call('simpan');

        $order = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('sekolah', $order->sumber);
        $this->assertNull($order->marketing_id);
        $this->assertSame($jkt->id, $order->cabang_id);
        $this->assertSame($sekolah->id, $order->sekolah_id);
        $this->assertSame(15000, $order->total);
    }

    // ---------- Free: aturan paket qty ----------

    public function test_free_qty_ambang_terpenuhi(): void
    {
        [$order] = $this->bookingPaketDenganAturan(jumlahSiswa: 25, ambang: 20);

        $free = $order->items->where('is_free', true);
        $this->assertCount(1, $free);
        $this->assertSame(0, (int) $free->first()->harga);
        $this->assertSame(2, $order->items->count()); // 1 paket + 1 free
    }

    public function test_free_qty_di_bawah_ambang(): void
    {
        [$order] = $this->bookingPaketDenganAturan(jumlahSiswa: 10, ambang: 20);

        $this->assertCount(0, $order->items->where('is_free', true));
        $this->assertSame(1, $order->items->count()); // hanya paket
    }

    private function bookingPaketDenganAturan(int $jumlahSiswa, int $ambang): array
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $marketing = User::factory()->create(['cabang_id' => $jkt->id]);
        $marketing->assignRole('marketing');

        $hadiah = $this->produk('Bingkai', 0);
        $isi = $this->produk('Cetak Paket', 100000);
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $paket->items()->create(['produk_id' => $isi->id, 'qty' => 1, 'harga' => 100000, 'is_free' => false]);
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '>=', 'nilai' => $ambang,
            'hasil_produk_id' => $hadiah->id, 'hasil_ukuran' => '10RP',
        ]);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'paket', 'paket_id' => $paket->id, 'qty' => 1]);
        $cart->setJumlahSiswa($jumlahSiswa);
        $cart->setSekolahId($sekolah->id);

        Livewire::actingAs($marketing);
        Livewire::test(Review::class, ['konteks' => 'staf'])->set('tanggalEvent', now()->addWeek()->toDateString())->call('simpan');

        return [Order::withoutGlobalScopes()->with('items')->firstOrFail()];
    }

    // ---------- Free: produk_bonus (mekanisme B) ----------

    public function test_free_produk_bonus(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $marketing = User::factory()->create(['cabang_id' => $jkt->id]);
        $marketing->assignRole('marketing');

        $utama = $this->produk('Cetak', 10000);
        $bonus = $this->produk('Gantungan', 5000);
        ProdukBonus::create(['produk_id' => $utama->id, 'bonus_produk_id' => $bonus->id, 'qty' => 2]);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $utama->id, 'qty' => 3]);
        $cart->setJumlahSiswa(5);
        $cart->setSekolahId($sekolah->id);

        Livewire::actingAs($marketing);
        Livewire::test(Review::class, ['konteks' => 'staf'])->set('tanggalEvent', now()->addWeek()->toDateString())->call('simpan');

        $order = Order::withoutGlobalScopes()->with('items')->firstOrFail();
        $free = $order->items->where('is_free', true)->first();
        $this->assertNotNull($free);
        $this->assertSame($bonus->id, $free->produk_id);
        $this->assertSame(6, $free->qty);          // 2 × 3
        $this->assertSame(30000, $order->total);    // 10000 × 3 (free tak dihitung)
    }

    // ---------- Riwayat isolasi per sekolah ----------

    public function test_riwayat_hanya_order_sekolah_sendiri(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $a = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $b = Sekolah::create(['id_sekolah' => 'SKL-JKT-0002', 'nama' => 'SD B', 'cabang_id' => $jkt->id]);

        Order::create(['sekolah_id' => $a->id, 'cabang_id' => $jkt->id, 'sumber' => 'sekolah', 'total' => 111000, 'jumlah_siswa' => 5, 'tanggal_booking' => now()]);
        Order::create(['sekolah_id' => $b->id, 'cabang_id' => $jkt->id, 'sumber' => 'sekolah', 'total' => 222000, 'jumlah_siswa' => 5, 'tanggal_booking' => now()]);

        Livewire::actingAs($a, 'sekolah');
        Livewire::test(Riwayat::class)
            ->assertSee('111.000')
            ->assertDontSee('222.000');
    }
}
