<?php

namespace Tests\Feature;

use App\Livewire\Finance\AllDataSales;
use App\Livewire\Finance\PenagihanHarian;
use App\Livewire\Finance\TransaksiEventHarian;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderPembayaran;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Sekolah $sekolah;

    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin_sales', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $this->sekolah = Sekolah::create(['id_sekolah' => 'SKL-000001', 'nama' => 'SDN Merdeka', 'cabang_id' => $this->jkt->id]);
        $this->marketing = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $this->marketing->assignRole('marketing');
    }

    private function finance(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('super_admin');

        return $u;
    }

    private function order(array $attr = []): Order
    {
        return Order::create(array_merge([
            'booking_code' => 'BK-'.uniqid(), 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => $this->marketing->id, 'sumber' => 'sekolah', 'status' => 'baru', 'total' => 100000,
            'tanggal_booking' => now(),
        ], $attr));
    }

    // ---------- Akses ----------

    public function test_marketing_ditolak_semua_halaman_finance(): void
    {
        foreach ([AllDataSales::class, PenagihanHarian::class, TransaksiEventHarian::class] as $c) {
            Livewire::actingAs($this->marketing)->test($c)->assertStatus(403);
        }
    }

    public function test_admin_sales_boleh_akses(): void
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('admin_sales');

        Livewire::actingAs($u)->test(AllDataSales::class)->assertOk();
    }

    // ---------- All Data Sales ----------

    public function test_all_data_sales_hanya_assigned_non_batal(): void
    {
        $tampil = $this->order(['booking_code' => 'BK-TAMPIL']);
        $this->order(['booking_code' => 'BK-BATAL', 'status' => 'batal']);
        Order::create([ // belum ada marketing → tak tampil
            'booking_code' => 'BK-BELUM', 'sekolah_id' => $this->sekolah->id, 'cabang_id' => $this->jkt->id,
            'marketing_id' => null, 'sumber' => 'sekolah', 'status' => 'baru', 'total' => 50000, 'tanggal_booking' => now(),
        ]);

        Livewire::actingAs($this->finance())
            ->test(AllDataSales::class)
            ->assertSee('BK-TAMPIL')
            ->assertDontSee('BK-BATAL')
            ->assertDontSee('BK-BELUM');
    }

    public function test_all_data_sales_outstanding_ikut_pembayaran(): void
    {
        $order = $this->order();
        OrderPembayaran::create(['order_id' => $order->id, 'jenis' => 'dp', 'jumlah' => 30000, 'tanggal_bayar' => now()->toDateString()]);

        Livewire::actingAs($this->finance())
            ->test(AllDataSales::class)
            ->assertSee('Rp70.000'); // outstanding = 100.000 - 30.000
    }

    public function test_modal_detail_tampilkan_produk(): void
    {
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false, 'grup' => 'souvenir']);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan Kunci', 'harga' => 15000, 'status' => 'aktif']);
        $order = $this->order();
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 4, 'harga' => 15000, 'is_free' => false]);

        Livewire::actingAs($this->finance())
            ->test(AllDataSales::class)
            ->call('lihatDetail', $order->id)
            ->assertSee('Gantungan Kunci');
    }

    // ---------- Penagihan Harian vs Event Harian ----------

    public function test_penagihan_harian_hanya_pembayaran_bukan_hari_h(): void
    {
        $tgl = now()->toDateString();
        $order = $this->order(['tanggal_event' => $tgl]);
        // Bayar di hari event (hari-H) → TIDAK muncul di penagihan.
        OrderPembayaran::create(['order_id' => $order->id, 'jenis' => 'dp', 'jumlah' => 40000, 'tanggal_bayar' => $tgl]);
        // Pelunasan H+1 → MUNCUL di penagihan (H+1).
        $besok = now()->addDay()->toDateString();
        OrderPembayaran::create(['order_id' => $order->id, 'jenis' => 'pelunasan', 'jumlah' => 60000, 'tanggal_bayar' => $besok]);

        // Tanggal event: tak ada penagihan (DP hari-H dikecualikan).
        Livewire::actingAs($this->finance())
            ->test(PenagihanHarian::class)->set('tanggal', $tgl)
            ->assertViewHas('totalTerkumpul', 0);

        // Tanggal H+1: pelunasan muncul.
        Livewire::actingAs($this->finance())
            ->test(PenagihanHarian::class)->set('tanggal', $besok)
            ->assertViewHas('totalTerkumpul', 60000);
    }

    public function test_event_harian_catat_dp_inline(): void
    {
        $tgl = now()->toDateString();
        $order = $this->order(['tanggal_event' => $tgl]);

        Livewire::actingAs($this->finance())
            ->test(TransaksiEventHarian::class)
            ->set('tanggal', $tgl)
            ->set('inputDp.'.$order->id, 40000)
            ->call('catatDp', $order->id)
            ->assertViewHas('terkumpul', 40000);

        $order->refresh()->load('pembayaran');
        $this->assertSame('dp', $order->status);
        $this->assertSame(40000, $order->totalDibayar());
    }
}
