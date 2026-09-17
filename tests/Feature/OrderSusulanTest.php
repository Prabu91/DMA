<?php

namespace Tests\Feature;

use App\Livewire\Booking\Keranjang;
use App\Livewire\Booking\OrderDetail;
use App\Livewire\Booking\OrderIndex;
use App\Livewire\Booking\Review;
use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\ProdukBonus;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Cart;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Order susulan (siswa yang difoto belakangan) — kesepakatan Fase 1: order
 * baru yang tertaut ke order induk. Dibuat marketing, langsung ke hari event
 * tanpa H-7/H-2, harga produk yang sama mengikuti induk, tanpa item free.
 */
class OrderSusulanTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private Sekolah $sekolah;

    private User $marketing;

    private Produk $pasFoto;

    private Produk $poster;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => '30073995', 'nama' => 'TK PLUS LESTARI', 'cabang_id' => $this->bdg->id,
        ]);
        $this->marketing = $this->staf('marketing', 'Rudi');

        $kategori = Kategori::create(['nama' => 'Pas Foto', 'pakai_desain' => false]);
        $this->pasFoto = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Pas Foto', 'harga' => 12000, 'aktif' => true]);
        $this->poster = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Poster', 'harga' => 30000, 'aktif' => true]);
    }

    private function staf(string $role, string $nama = 'Staf', ?int $cabangId = -1): User
    {
        $u = User::factory()->create([
            'nama' => $nama,
            'cabang_id' => $cabangId === -1 ? $this->bdg->id : $cabangId,
        ]);
        $u->assignRole($role);

        return $u;
    }

    /** Order induk: event 14 Sep, pas foto 50 pcs dengan harga yang sudah dikoreksi jadi Rp10.000. */
    private function induk(array $atribut = []): Order
    {
        $order = Order::create(array_merge([
            'booking_code' => '140926BDGMKT001',
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $this->marketing->id,
            'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'jumlah_siswa' => 50,
            'tanggal_event' => now()->subDays(3)->toDateString(),
            'konfirmasi_h7_at' => now()->subDays(10),
            'konfirmasi_h2_at' => now()->subDays(5),
            'konfirmasi_hh_at' => now()->subDays(3),
            'event_status' => OrderStatus::EVENT_SELESAI,
            'total' => 500000,
            'tanggal_booking' => now()->subMonth(),
        ], $atribut));

        $order->items()->create([
            'tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id,
            'opsi_ukuran' => '1 UKURAN', 'qty' => 50, 'harga' => 10000, 'is_free' => false,
        ]);

        return $order;
    }

    /** Jalankan alur lengkap: tombol susulan → keranjang → review → simpan. */
    private function buatSusulan(Order $induk, User $oleh, array $baris, int $siswa = 2): Order
    {
        Livewire::actingAs($oleh)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $induk->id])
            ->call('buatSusulan')
            ->assertRedirect(route('app.etalase.index'));

        $cart = app(Cart::class);
        foreach ($baris as $b) {
            $cart->add($b);
        }
        $cart->setJumlahSiswa($siswa);

        Livewire::actingAs($oleh)
            ->test(Review::class, ['konteks' => 'staf'])
            ->set('tanggalEvent', now()->addDays(3)->toDateString())
            ->call('simpan')
            ->assertHasNoErrors();

        return Order::withoutGlobalScopes()->where('order_induk_id', $induk->id)->latest('id')->firstOrFail();
    }

    // ---------------- Membuat susulan ----------------

    public function test_marketing_membuat_susulan_yang_tertaut_ke_induk(): void
    {
        $induk = $this->induk();

        $susulan = $this->buatSusulan($induk, $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '1 UKURAN', 'qty' => 2],
        ]);

        $this->assertSame($induk->id, $susulan->order_induk_id);
        $this->assertTrue($susulan->isSusulan());
        $this->assertSame($this->sekolah->id, $susulan->sekolah_id);
        $this->assertSame($this->marketing->id, $susulan->marketing_id);
        $this->assertSame($this->bdg->id, $susulan->cabang_id);
        $this->assertSame(2, $susulan->jumlah_siswa);
        $this->assertNotNull($susulan->booking_code);
        $this->assertSame([$susulan->id], $induk->susulan()->pluck('id')->all());
        $this->assertTrue(app(Cart::class)->isEmpty());
    }

    public function test_mulai_susulan_mengosongkan_keranjang_dan_mengunci_sekolah(): void
    {
        $induk = $this->induk();
        $lain = Sekolah::create(['id_sekolah' => '11111111', 'nama' => 'SD Lain', 'cabang_id' => $this->bdg->id]);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $this->poster->id, 'qty' => 9]);
        $cart->setSekolahId($lain->id);

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $induk->id])
            ->call('buatSusulan');

        $this->assertTrue($cart->isEmpty());
        $this->assertSame($induk->id, $cart->indukId());
        $this->assertSame($this->sekolah->id, $cart->sekolahId());

        // Mengganti sekolah dari keranjang diabaikan selama mode susulan.
        Livewire::actingAs($this->marketing)
            ->test(Keranjang::class, ['konteks' => 'staf'])
            ->set('sekolahId', $lain->id)
            ->assertSet('sekolahId', $this->sekolah->id);
    }

    public function test_admin_pusat_tanpa_cabang_membuat_susulan_memakai_cabang_induk(): void
    {
        $induk = $this->induk();
        $admin = $this->staf('admin_sales', 'Admin', null);

        $susulan = $this->buatSusulan($induk, $admin, [
            ['tipe_item' => 'produk', 'produk_id' => $this->poster->id, 'qty' => 1],
        ]);

        $this->assertSame($this->bdg->id, $susulan->cabang_id);
        $this->assertSame($this->marketing->id, $susulan->marketing_id); // tetap milik marketing induk
    }

    public function test_susulan_dari_susulan_tertaut_ke_induk_asli(): void
    {
        $induk = $this->induk();
        $pertama = $this->buatSusulan($induk, $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->poster->id, 'qty' => 1],
        ]);

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $pertama->id])
            ->call('buatSusulan');

        $this->assertSame($induk->id, app(Cart::class)->indukId());
        $this->assertSame($this->sekolah->id, app(Cart::class)->sekolahId());
    }

    public function test_tim_event_tidak_bisa_membuat_susulan(): void
    {
        $induk = $this->induk();
        $tim = $this->staf('tim_event');

        Livewire::actingAs($tim)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $induk->id])
            ->call('buatSusulan')
            ->assertForbidden();

        $this->assertNull(app(Cart::class)->indukId());
    }

    public function test_order_batal_tidak_bisa_dibuatkan_susulan(): void
    {
        $induk = $this->induk(['status' => OrderStatus::BATAL]);

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $induk->id])
            ->call('buatSusulan')
            ->assertForbidden();
    }

    public function test_batal_susulan_mengosongkan_keranjang(): void
    {
        $induk = $this->induk();
        app(Cart::class)->mulaiSusulan($induk->id, $this->sekolah->id);

        Livewire::actingAs($this->marketing)
            ->test(Keranjang::class, ['konteks' => 'staf'])
            ->call('batalSusulan')
            ->assertRedirect(route('app.order.show', $induk->id));

        $this->assertNull(app(Cart::class)->indukId());
    }

    // ---------------- Aturan uang ----------------

    public function test_produk_yang_sama_memakai_harga_induk_bukan_harga_katalog(): void
    {
        // Katalog Rp12.000, tapi di induk sudah dikoreksi jadi Rp10.000.
        $susulan = $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '1 UKURAN', 'qty' => 2],
        ]);

        $this->assertSame(10000, (int) $susulan->items->first()->harga);
        $this->assertSame(20000, (int) $susulan->total);
    }

    public function test_produk_yang_tidak_ada_di_induk_memakai_harga_katalog(): void
    {
        // Item susulan boleh berbeda dari induk.
        $susulan = $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->poster->id, 'qty' => 1],
        ]);

        $this->assertSame(30000, (int) $susulan->items->first()->harga);
    }

    public function test_opsi_berbeda_tidak_memakai_harga_induk(): void
    {
        // Harga bergantung pada opsi — pas foto ukuran lain bukan baris yang sama.
        $susulan = $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '2 UKURAN', 'qty' => 2],
        ]);

        $this->assertSame(12000, (int) $susulan->items->first()->harga);
    }

    public function test_susulan_tidak_mendapat_item_free(): void
    {
        // Pas foto memberi bonus poster. Bonus itu sudah diberikan di induk.
        ProdukBonus::create(['produk_id' => $this->pasFoto->id, 'bonus_produk_id' => $this->poster->id, 'qty' => 1]);

        $susulan = $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '1 UKURAN', 'qty' => 2],
        ]);

        $this->assertSame(0, $susulan->items()->where('is_free', true)->count());
    }

    public function test_hitung_ulang_item_di_lokasi_tidak_memunculkan_item_free(): void
    {
        ProdukBonus::create(['produk_id' => $this->pasFoto->id, 'bonus_produk_id' => $this->poster->id, 'qty' => 1]);

        $susulan = $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '1 UKURAN', 'qty' => 2],
        ]);

        app(BookingService::class)->rebuildOrder($susulan);

        $this->assertSame(0, $susulan->items()->where('is_free', true)->count());
    }

    public function test_order_biasa_tetap_mendapat_item_free(): void
    {
        // Pengaman: aturan susulan tidak boleh ikut mematikan free di order biasa.
        ProdukBonus::create(['produk_id' => $this->pasFoto->id, 'bonus_produk_id' => $this->poster->id, 'qty' => 1]);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'qty' => 2]);
        $cart->setJumlahSiswa(2);
        $cart->setSekolahId($this->sekolah->id);

        Livewire::actingAs($this->marketing)
            ->test(Review::class, ['konteks' => 'staf'])
            ->set('tanggalEvent', now()->addDays(3)->toDateString())
            ->call('simpan');

        $order = Order::withoutGlobalScopes()->whereNull('order_induk_id')->latest('id')->firstOrFail();
        $this->assertSame(1, $order->items()->where('is_free', true)->count());
    }

    // ---------------- Langsung ke hari event ----------------

    private function susulanSiapEvent(): Order
    {
        return $this->buatSusulan($this->induk(), $this->marketing, [
            ['tipe_item' => 'produk', 'produk_id' => $this->pasFoto->id, 'opsi_ukuran' => '1 UKURAN', 'qty' => 2],
        ]);
    }

    public function test_susulan_hanya_punya_milestone_hari_h(): void
    {
        $susulan = $this->susulanSiapEvent();

        $this->assertSame(['hh'], array_column($susulan->milestones(), 'key'));
        $this->assertTrue($susulan->milestoneTerbuka('hh'));
    }

    public function test_hari_h_susulan_tidak_menunggu_h2(): void
    {
        $susulan = $this->susulanSiapEvent();
        $susulan->update(['konfirmasi_lokasi_at' => now()]);
        $tim = $this->staf('tim_event');
        $susulan->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $susulan->id])
            ->call('toggleQcEvent', $susulan->items()->value('id'))
            ->call('konfirmasiHariH')
            ->assertHasNoErrors();

        $susulan->refresh();
        $this->assertNull($susulan->konfirmasi_h2_at);
        $this->assertSame(OrderStatus::EVENT_SELESAI, $susulan->event_status);
    }

    public function test_susulan_tetap_wajib_konfirmasi_data_sekolah(): void
    {
        // Pemeriksaan di lokasi tetap berlaku, hanya urutan jadwalnya yang dilewati.
        $susulan = $this->susulanSiapEvent();
        $tim = $this->staf('tim_event');
        $susulan->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)
            ->test(EventDetail::class, ['orderId' => $susulan->id])
            ->call('konfirmasiHariH');

        $this->assertNull($susulan->refresh()->konfirmasi_hh_at);
    }

    public function test_h7_dan_h2_tidak_bisa_dikonfirmasi_pada_susulan(): void
    {
        $susulan = $this->susulanSiapEvent();

        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $susulan->id])
            ->call('konfirmasiMilestone', 'h7')
            ->assertStatus(422);
    }

    public function test_ste_susulan_terbit_tanpa_h2(): void
    {
        $susulan = $this->susulanSiapEvent();

        $this->actingAs($this->marketing)
            ->get(route('app.order.ste', $susulan->id))
            ->assertOk();
    }

    public function test_ste_order_biasa_tetap_menunggu_h2(): void
    {
        $induk = $this->induk(['konfirmasi_h2_at' => null, 'konfirmasi_hh_at' => null, 'event_status' => null]);

        $this->actingAs($this->marketing)
            ->get(route('app.order.ste', $induk->id))
            ->assertForbidden();
    }

    public function test_susulan_tidak_muncul_di_filter_butuh_h2(): void
    {
        $susulan = $this->susulanSiapEvent();

        Livewire::actingAs($this->staf('operasional', 'Ops', null))
            ->test(OrderIndex::class)
            ->set('tahap', 'butuh_h2')
            ->assertDontSee($susulan->booking_code);
    }

    // ---------------- Tampilan ----------------

    public function test_order_induk_menampilkan_daftar_susulan(): void
    {
        $susulan = $this->susulanSiapEvent();

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $susulan->order_induk_id])
            ->assertSee('Order susulan')
            ->assertSee($susulan->booking_code);
    }

    public function test_order_susulan_menunjuk_ke_induknya(): void
    {
        $susulan = $this->susulanSiapEvent();

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $susulan->id])
            ->assertSee('Order susulan dari')
            ->assertSee('140926BDGMKT001');
    }

    public function test_etalase_menampilkan_penanda_mode_susulan(): void
    {
        $induk = $this->induk();

        $this->actingAs($this->marketing)
            ->withSession(['booking_cart' => ['induk_id' => $induk->id, 'sekolah_id' => $this->sekolah->id]])
            ->get(route('app.etalase.index'))
            ->assertOk()
            ->assertSee('Membuat order susulan untuk')
            ->assertSee('140926BDGMKT001');
    }

    public function test_etalase_biasa_tanpa_penanda_susulan(): void
    {
        $this->actingAs($this->marketing)
            ->get(route('app.etalase.index'))
            ->assertOk()
            ->assertDontSee('Membuat order susulan untuk');
    }

    public function test_sekolah_juga_melihat_tautan_induk(): void
    {
        $susulan = $this->susulanSiapEvent();

        Livewire::actingAs($this->sekolah, 'sekolah')
            ->test(OrderDetail::class, ['konteks' => 'sekolah', 'orderId' => $susulan->id])
            ->assertSee('Order susulan dari');
    }

    public function test_susulan_tetap_susulan_walau_induk_dihapus_permanen(): void
    {
        $susulan = $this->susulanSiapEvent();

        // Jalur hapus permanen yang sesungguhnya: buang ke sampah, lalu purge.
        $superAdmin = $this->staf('super_admin', 'Super', null);
        Order::withoutGlobalScopes()->find($susulan->order_induk_id)->delete();
        Livewire::actingAs($superAdmin)
            ->test(OrderIndex::class)
            ->call('hapusPermanen', $susulan->order_induk_id);
        $this->assertNull(Order::withoutGlobalScopes()->withTrashed()->find($susulan->order_induk_id));

        $susulan = Order::withoutGlobalScopes()->find($susulan->id);
        $this->assertTrue($susulan->isSusulan());       // tidak berubah jadi order biasa
        $this->assertSame(['hh'], array_column($susulan->milestones(), 'key'));

        Livewire::actingAs($this->marketing)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $susulan->id])
            ->assertSee('order yang sudah dihapus permanen');
    }
}
