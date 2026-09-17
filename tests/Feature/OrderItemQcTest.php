<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Event\EventDetail;
use App\Models\Cabang;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\ProdukBonus;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\BookingService;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * QC item per peran — pengganti tiga checklist kembar di kartu Trello
 * (MARKETING, TEAM EVENT, ADMIN). Tim event mencentang di lokasi sebelum
 * Hari-H; admin mencentang ulang sesudah event.
 */
class OrderItemQcTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private Sekolah $sekolah;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => '28981678', 'nama' => 'TK MIFTAHUL KHOIR', 'cabang_id' => $this->bdg->id,
        ]);
        $this->kategori = Kategori::create(['nama' => 'Foto', 'pakai_desain' => true]);
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

    private function produk(string $nama, int $harga = 10000): Produk
    {
        return Produk::create(['kategori_id' => $this->kategori->id, 'nama' => $nama, 'harga' => $harga, 'aktif' => true]);
    }

    /** Order siap Hari-H: data sekolah & H-2 beres, dua item berbayar + satu item free. */
    private function order(array $atribut = []): Order
    {
        $order = Order::create(array_merge([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'jumlah_siswa' => 49,
            'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tanggal_event' => now()->toDateString(),
            'konfirmasi_lokasi_at' => now(),
            'konfirmasi_h7_at' => now()->subDays(7),
            'konfirmasi_h2_at' => now()->subDays(2),
            'total' => 0,
            'tanggal_booking' => now()->subMonth(),
        ], $atribut));

        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produk('Foto 10RP Profesi')->id, 'qty' => 49, 'harga' => 10000, 'is_free' => false]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produk('Pas Foto')->id, 'qty' => 42, 'harga' => 5000, 'is_free' => false]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produk('Poster')->id, 'qty' => 63, 'harga' => 0, 'is_free' => true]);

        return $order;
    }

    private function timEvent(Order $order): User
    {
        $u = $this->staf('tim_event', 'Reynaldi');
        $order->timEvent()->attach($u->id);

        return $u;
    }

    private function event(Order $order, User $user)
    {
        return Livewire::actingAs($user)->test(EventDetail::class, ['orderId' => $order->id]);
    }

    private function centangSemua($komponen, Order $order): void
    {
        foreach ($order->items()->pluck('id') as $id) {
            $komponen->call('toggleQcEvent', $id);
        }
    }

    // ---------------- Tim event ----------------

    public function test_tim_event_mencentang_item_dan_tercatat_siapa_serta_kapan(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);
        $item = $order->items()->first();

        $this->event($order, $tim)->call('toggleQcEvent', $item->id);

        $item->refresh();
        $this->assertNotNull($item->qc_event_at);
        $this->assertSame($tim->id, $item->qc_event_oleh);
    }

    public function test_centang_kedua_kali_melepas_centang(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);
        $item = $order->items()->first();

        $this->event($order, $tim)
            ->call('toggleQcEvent', $item->id)
            ->call('toggleQcEvent', $item->id);

        $item->refresh();
        $this->assertNull($item->qc_event_at);
        $this->assertNull($item->qc_event_oleh);
    }

    public function test_hari_h_ditolak_sebelum_semua_item_dicek(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);
        $berbayar = $order->items()->where('is_free', false)->pluck('id');

        // Semua item berbayar dicek, item free (poster) terlewat.
        $komponen = $this->event($order, $tim);
        foreach ($berbayar as $id) {
            $komponen->call('toggleQcEvent', $id);
        }
        $komponen->call('konfirmasiHariH')
            ->assertSee('Centang semua item dulu (2/3) sebelum Hari-H.');

        $this->assertNull($order->refresh()->konfirmasi_hh_at);
    }

    public function test_hari_h_bisa_setelah_semua_item_termasuk_free_dicek(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);

        $komponen = $this->event($order, $tim);
        $this->centangSemua($komponen, $order);
        $komponen->call('konfirmasiHariH');

        $order->refresh();
        $this->assertNotNull($order->konfirmasi_hh_at);
        $this->assertSame(OrderStatus::EVENT_SELESAI, $order->event_status);
    }

    public function test_semua_item_dicek_tercatat_di_log(): void
    {
        $order = $this->order();
        $komponen = $this->event($order, $this->timEvent($order));
        $this->centangSemua($komponen, $order);

        $this->assertSame(1, $order->activities()->where('action', 'qc_event_lengkap')->count());
    }

    public function test_tim_event_tidak_bisa_mencentang_setelah_terkunci(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now()]);
        $tim = $this->timEvent($order);

        $this->event($order, $tim)
            ->call('toggleQcEvent', $order->items()->value('id'))
            ->assertStatus(422);
    }

    public function test_tim_event_lain_tidak_bisa_mencentang(): void
    {
        $order = $this->order();
        $bukanTimnya = $this->staf('tim_event', 'Tim lain');

        Livewire::actingAs($bukanTimnya)
            ->test(EventDetail::class, ['orderId' => $order->id])
            ->assertForbidden();
    }

    public function test_daftar_periksa_tampil_dengan_progres(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);

        $this->event($order, $tim)
            ->assertSee('Periksa item bersama guru')
            ->assertSee('0/3 dicek')
            ->assertSee('Poster')
            ->call('toggleQcEvent', $order->items()->value('id'))
            ->assertSee('1/3 dicek')
            ->assertSee('Dicek Reynaldi');
    }

    // ---------------- Centang lepas saat item berubah ----------------

    public function test_ubah_jumlah_melepas_centang(): void
    {
        $order = $this->order();
        $tim = $this->timEvent($order);
        $item = $order->items()->where('is_free', false)->first();

        $this->event($order, $tim)
            ->call('toggleQcEvent', $item->id)
            ->call('ubahQtyItem', $item->id, 48);

        $this->assertNull($item->refresh()->qc_event_at);
    }

    public function test_ganti_desain_melepas_centang_semua_peran(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $item->setQc('event', true, null);
        $item->setQc('admin', true, null);

        $desain = Desain::create([
            'kategori_id' => $this->kategori->id, 'kode' => 'FRS-012',
            'tahun_ajaran' => '2026/2027', 'status' => 'aktif',
        ]);
        $item->update(['desain_id' => $desain->id]);

        $item->refresh();
        $this->assertNull($item->qc_event_at);
        $this->assertNull($item->qc_admin_at);
    }

    public function test_koreksi_harga_tidak_melepas_centang(): void
    {
        // QC memeriksa item & jumlahnya, bukan harganya.
        $order = $this->order(['konfirmasi_hh_at' => now()]);
        $item = $order->items()->where('is_free', false)->first();
        $item->setQc('event', true, null);

        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditHarga', $item->id)
            ->set('hargaBaru', 9000)
            ->call('simpanHarga');

        $item->refresh();
        $this->assertSame(9000, (int) $item->harga);
        $this->assertNotNull($item->qc_event_at);
    }

    public function test_item_free_yang_tidak_berubah_tetap_tercentang_setelah_hitung_ulang(): void
    {
        // Pas foto memberi 1 bonus gantungan per pcs. Mengubah jumlah item LAIN
        // tidak mengubah bonusnya, jadi centang bonus tidak boleh hilang.
        $order = Order::create([
            'booking_code' => 'BK-FREE', 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing', 'status' => 'baru', 'jumlah_siswa' => 10,
            'tanggal_event' => now()->toDateString(), 'total' => 0, 'tanggal_booking' => now(),
        ]);
        $pas = $this->produk('Pas Foto');
        $gantungan = $this->produk('Gantungan');
        ProdukBonus::create(['produk_id' => $pas->id, 'bonus_produk_id' => $gantungan->id, 'qty' => 1]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $pas->id, 'qty' => 2, 'harga' => 5000, 'is_free' => false]);
        $lain = $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $this->produk('Foto Kelas')->id, 'qty' => 1, 'harga' => 20000, 'is_free' => false]);
        app(BookingService::class)->rebuildOrder($order);

        $bonus = $order->items()->where('is_free', true)->firstOrFail();
        $bonus->setQc('event', true, null);

        $lain->update(['qty' => 3]);
        app(BookingService::class)->rebuildOrder($order);

        $bonusBaru = $order->items()->where('is_free', true)->firstOrFail();
        $this->assertNotSame($bonus->id, $bonusBaru->id);  // memang dibuat ulang
        $this->assertNotNull($bonusBaru->qc_event_at);      // tapi centangnya terbawa
    }

    public function test_item_free_yang_jumlahnya_berubah_kehilangan_centang(): void
    {
        $order = Order::create([
            'booking_code' => 'BK-FREE2', 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing', 'status' => 'baru', 'jumlah_siswa' => 10,
            'tanggal_event' => now()->toDateString(), 'total' => 0, 'tanggal_booking' => now(),
        ]);
        $pas = $this->produk('Pas Foto');
        ProdukBonus::create(['produk_id' => $pas->id, 'bonus_produk_id' => $this->produk('Gantungan')->id, 'qty' => 1]);
        $item = $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $pas->id, 'qty' => 2, 'harga' => 5000, 'is_free' => false]);
        app(BookingService::class)->rebuildOrder($order);
        $order->items()->where('is_free', true)->first()->setQc('event', true, null);

        $item->update(['qty' => 3]); // bonus ikut jadi 3
        app(BookingService::class)->rebuildOrder($order);

        $bonus = $order->items()->where('is_free', true)->firstOrFail();
        $this->assertSame(3, $bonus->qty);
        $this->assertNull($bonus->qc_event_at);
    }

    // ---------------- Admin ----------------

    private function detail(Order $order, User $user)
    {
        return Livewire::actingAs($user)
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id]);
    }

    public function test_admin_mencentang_setelah_hari_h_walau_order_terkunci(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now(), 'event_status' => OrderStatus::EVENT_SELESAI]);
        $admin = $this->staf('admin_sales', 'Admin Collection', null);
        $item = $order->items()->first();

        $this->assertTrue($order->isLocked());
        $this->detail($order, $admin)->call('toggleQcAdmin', $item->id);

        $item->refresh();
        $this->assertNotNull($item->qc_admin_at);
        $this->assertSame($admin->id, $item->qc_admin_oleh);
    }

    public function test_admin_belum_bisa_mencentang_sebelum_hari_h(): void
    {
        $order = $this->order();

        $this->detail($order, $this->staf('admin_sales', 'Admin', null))
            ->call('toggleQcAdmin', $order->items()->value('id'))
            ->assertForbidden();
    }

    public function test_marketing_tidak_bisa_mencentang_qc_admin(): void
    {
        $marketing = $this->staf('marketing', 'Shanty');
        $order = $this->order(['konfirmasi_hh_at' => now(), 'marketing_id' => $marketing->id]);

        $this->detail($order, $marketing)
            ->call('toggleQcAdmin', $order->items()->value('id'))
            ->assertForbidden();
    }

    public function test_order_batal_tidak_bisa_dicentang_admin(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now(), 'status' => OrderStatus::BATAL]);

        $this->detail($order, $this->staf('admin_sales', 'Admin', null))
            ->call('toggleQcAdmin', $order->items()->value('id'))
            ->assertForbidden();
    }

    public function test_semua_item_dicek_admin_tercatat_di_log(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now()]);
        $komponen = $this->detail($order, $this->staf('admin_sales', 'Admin', null));
        foreach ($order->items()->pluck('id') as $id) {
            $komponen->call('toggleQcAdmin', $id);
        }

        $this->assertSame(1, $order->activities()->where('action', 'qc_admin_lengkap')->count());
    }

    public function test_halaman_order_menampilkan_progres_qc_per_peran(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now()]);
        $order->items->each(fn (OrderItem $i) => $i->setQc('event', true, null));
        $order->items()->first()->setQc('admin', true, null);

        $this->detail($order, $this->staf('admin_sales', 'Admin', null))
            ->assertSee('Tim event 3/3')
            ->assertSee('Admin 1/3');
    }

    public function test_status_qc_tidak_tampil_sebelum_tim_event_mulai(): void
    {
        $order = $this->order();

        $this->detail($order, $this->staf('admin_sales', 'Admin', null))
            ->assertDontSee('Tim event 0/3');
    }

    public function test_sekolah_tidak_melihat_status_qc(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => now()]);
        $order->items->each(fn (OrderItem $i) => $i->setQc('event', true, null));

        Livewire::actingAs($this->sekolah, 'sekolah')
            ->test(OrderDetail::class, ['konteks' => 'sekolah', 'orderId' => $order->id])
            ->assertDontSee('Tim event 3/3');
    }
}
