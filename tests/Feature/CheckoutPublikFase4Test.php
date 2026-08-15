<?php

namespace Tests\Feature;

use App\Livewire\Booking\Review;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutPublikFase4Test extends TestCase
{
    use RefreshDatabase;

    private function cabang(): Cabang
    {
        return Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
    }

    private function sekolah(?int $cabangId, bool $verified = true): Sekolah
    {
        $sekolah = Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SD Uji',
            'email' => 'uji'.uniqid().'@contoh.sch.id',
            'cabang_id' => $cabangId,
        ]);

        if ($verified) {
            $sekolah->forceFill(['email_verified_at' => now()])->save();
        }

        return $sekolah;
    }

    private function isiKeranjang(int $qty = 1): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama' => 'Foto Kelas'], ['pakai_desain' => false]);
        $produk = Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Foto Kelas A',
            'harga' => 25000,
            'status' => 'aktif',
        ]);
        app(Cart::class)->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => $qty]);

        return $produk;
    }

    public function test_tamu_diarahkan_ke_login_dan_intended_disimpan(): void
    {
        $this->get(route('storefront.checkout'))
            ->assertRedirect(route('sekolah.masuk'));

        $this->assertSame(route('storefront.checkout'), session('url.intended'));
    }

    public function test_sekolah_belum_verifikasi_tetap_bisa_checkout(): void
    {
        // Verifikasi email tidak lagi diwajibkan (sementara; nanti via WA).
        $cabang = $this->cabang();
        $this->isiKeranjang();

        $this->actingAs($this->sekolah($cabang->id, verified: false), 'sekolah')
            ->get(route('storefront.checkout'))
            ->assertOk();
    }

    public function test_keranjang_kosong_diarahkan_ke_keranjang(): void
    {
        $cabang = $this->cabang();

        $this->actingAs($this->sekolah($cabang->id), 'sekolah')
            ->get(route('storefront.checkout'))
            ->assertRedirect(route('storefront.keranjang'));
    }

    public function test_cabang_null_checkout_diblokir(): void
    {
        $this->isiKeranjang();

        $this->actingAs($this->sekolah(null), 'sekolah')
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSee('Cabang belum ditetapkan')
            ->assertDontSeeLivewire(Review::class);
    }

    public function test_checkout_valid_menampilkan_review_publik(): void
    {
        $cabang = $this->cabang();
        $this->isiKeranjang();

        $this->actingAs($this->sekolah($cabang->id), 'sekolah')
            ->get(route('storefront.checkout'))
            ->assertOk()
            ->assertSeeLivewire(Review::class)
            ->assertSee('Checkout');
    }

    public function test_simpan_order_publik_membuat_order_sekolah(): void
    {
        $cabang = $this->cabang();
        $sekolah = $this->sekolah($cabang->id);
        $this->isiKeranjang(2);

        $this->actingAs($sekolah, 'sekolah');

        $test = Livewire::test(Review::class, ['konteks' => 'publik'])
            ->set('jumlahSiswaInput', 30)
            ->set('tanggalEvent', now()->addWeek()->toDateString())
            ->call('simpan');

        $order = Order::first();
        $this->assertNotNull($order);
        $test->assertRedirect(route('sekolah.riwayat.show', $order->id));
        $this->assertSame('sekolah', $order->sumber);
        $this->assertNull($order->marketing_id);
        $this->assertSame($cabang->id, $order->cabang_id);
        $this->assertSame($sekolah->id, $order->sekolah_id);
        $this->assertSame(30, $order->jumlah_siswa);
        $this->assertSame(now()->addWeek()->toDateString(), $order->tanggal_event->toDateString());
        $this->assertNull($order->booking_code); // menunggu penugasan marketing
        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_tanggal_event_wajib_saat_checkout(): void
    {
        $cabang = $this->cabang();
        $this->isiKeranjang();
        $this->actingAs($this->sekolah($cabang->id), 'sekolah');

        Livewire::test(Review::class, ['konteks' => 'publik'])
            ->set('jumlahSiswaInput', 20)
            ->call('simpan')   // tanpa tanggalEvent
            ->assertHasErrors('tanggalEvent')
            ->assertNoRedirect();

        $this->assertSame(0, Order::count());
    }

    public function test_tanggal_event_tidak_boleh_masa_lalu(): void
    {
        $cabang = $this->cabang();
        $this->isiKeranjang();
        $this->actingAs($this->sekolah($cabang->id), 'sekolah');

        Livewire::test(Review::class, ['konteks' => 'publik'])
            ->set('jumlahSiswaInput', 20)
            ->set('tanggalEvent', now()->subDay()->toDateString())
            ->call('simpan')
            ->assertHasErrors('tanggalEvent');

        $this->assertSame(0, Order::count());
    }

    public function test_simpan_ditolak_tanpa_jumlah_siswa(): void
    {
        $cabang = $this->cabang();
        $this->isiKeranjang();

        $this->actingAs($this->sekolah($cabang->id), 'sekolah');

        Livewire::test(Review::class, ['konteks' => 'publik'])
            ->call('simpan')
            ->assertSet('error', 'Isi jumlah siswa terlebih dahulu.')
            ->assertNoRedirect();

        $this->assertSame(0, Order::count());
    }
}
