<?php

namespace Tests\Feature;

use App\Livewire\Booking\KotakMasuk;
use App\Livewire\Booking\Review;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\CodeGenerator;
use App\Support\Cart;
use App\Support\Qr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BookingFase67Test extends TestCase
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

    private function marketing(Cabang $c, string $kodeRole = 'MKT1'): User
    {
        $u = User::factory()->create(['cabang_id' => $c->id, 'kode_role' => $kodeRole]);
        $u->assignRole('marketing');

        return $u;
    }

    // ---------- CodeGenerator ----------

    public function test_format_booking_code(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD B', 'cabang_id' => $bdg->id]);
        $mkt = $this->marketing($bdg, 'MKT1');

        $order = Order::create([
            'sekolah_id' => $sekolah->id, 'cabang_id' => $bdg->id, 'marketing_id' => $mkt->id,
            'sumber' => 'sekolah', 'total' => 100000, 'jumlah_siswa' => 10,
            'tanggal_booking' => Carbon::create(2026, 6, 27, 9, 0),
        ]);

        $code = app(CodeGenerator::class)->generate($order);
        $this->assertSame('270626BDGMKT1001', $code);

        // Idempotent.
        $this->assertSame('270626BDGMKT1001', app(CodeGenerator::class)->generate($order->fresh()));
    }

    public function test_urutan_bertambah_untuk_prefix_sama(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD B', 'cabang_id' => $bdg->id]);
        $mkt = $this->marketing($bdg, 'MKT1');
        $tgl = Carbon::create(2026, 6, 27, 9, 0);

        $o1 = Order::create(['sekolah_id' => $sekolah->id, 'cabang_id' => $bdg->id, 'marketing_id' => $mkt->id, 'sumber' => 'sekolah', 'total' => 1, 'jumlah_siswa' => 1, 'tanggal_booking' => $tgl]);
        $o2 = Order::create(['sekolah_id' => $sekolah->id, 'cabang_id' => $bdg->id, 'marketing_id' => $mkt->id, 'sumber' => 'sekolah', 'total' => 1, 'jumlah_siswa' => 1, 'tanggal_booking' => $tgl]);

        $this->assertSame('270626BDGMKT1001', app(CodeGenerator::class)->generate($o1));
        $this->assertSame('270626BDGMKT1002', app(CodeGenerator::class)->generate($o2));
    }

    public function test_tanpa_marketing_tidak_ada_code(): void
    {
        $bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD B', 'cabang_id' => $bdg->id]);
        $order = Order::create(['sekolah_id' => $sekolah->id, 'cabang_id' => $bdg->id, 'sumber' => 'sekolah', 'total' => 1, 'jumlah_siswa' => 1, 'tanggal_booking' => now()]);

        $this->assertNull(app(CodeGenerator::class)->generate($order));
        $this->assertNull($order->fresh()->booking_code);
    }

    // ---------- Trigger jalur marketing (langsung saat simpan) ----------

    public function test_jalur_marketing_code_langsung(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $mkt = $this->marketing($jkt, 'MKT2');
        $kategori = Kategori::create(['nama' => 'K', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Cetak', 'harga' => 10000, 'status' => 'aktif']);

        $cart = app(Cart::class);
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 1]);
        $cart->setJumlahSiswa(10);
        $cart->setSekolahId($sekolah->id);

        Livewire::actingAs($mkt);
        Livewire::test(Review::class, ['konteks' => 'staf'])->set('tanggalEvent', now()->addWeek()->toDateString())->call('simpan');

        $order = Order::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($order->booking_code);
        $this->assertStringContainsString('JKTMKT2', $order->booking_code);
    }

    // ---------- Trigger jalur sekolah (saat diambil) ----------

    public function test_jalur_sekolah_code_saat_diambil(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $mkt = $this->marketing($jkt, 'MKT1');
        $order = Order::create(['sekolah_id' => $sekolah->id, 'cabang_id' => $jkt->id, 'sumber' => 'sekolah', 'total' => 1, 'jumlah_siswa' => 1, 'tanggal_booking' => now()]);

        Livewire::actingAs($mkt);
        Livewire::test(KotakMasuk::class)->call('ambil', $order->id);

        $order->refresh();
        $this->assertSame($mkt->id, $order->marketing_id);
        $this->assertNotNull($order->booking_code);
    }

    // ---------- QR + PDF ----------

    public function test_qr_svg_dibuat(): void
    {
        $svg = Qr::svg('270626BDGMKT1001', 120);
        $this->assertStringContainsString('<svg', $svg);
    }

    public function test_pdf_staf_dan_sekolah(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $mkt = $this->marketing($jkt, 'MKT1');
        $order = Order::create([
            'sekolah_id' => $sekolah->id, 'cabang_id' => $jkt->id, 'marketing_id' => $mkt->id,
            'sumber' => 'sekolah', 'booking_code' => '060726JKTMKT1001', 'total' => 50000, 'jumlah_siswa' => 10,
            'tanggal_booking' => now(),
        ]);
        $order->items()->create(['tipe_item' => 'produk', 'produk_id' => null, 'qty' => 1, 'harga' => 50000, 'is_free' => false]);
        $sekolah->markEmailAsVerified();

        // Staf (cabang sama).
        $this->actingAs($mkt)->get(route('app.order.pdf', $order->id))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Sekolah pemilik.
        $this->actingAs($sekolah, 'sekolah')->get(route('sekolah.riwayat.pdf', $order->id))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_sekolah_lain_ditolak(): void
    {
        $jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $a = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $jkt->id]);
        $b = Sekolah::create(['id_sekolah' => 'SKL-JKT-0002', 'nama' => 'SD B', 'cabang_id' => $jkt->id]);
        $b->markEmailAsVerified();
        $order = Order::create(['sekolah_id' => $a->id, 'cabang_id' => $jkt->id, 'sumber' => 'sekolah', 'total' => 1, 'jumlah_siswa' => 1, 'tanggal_booking' => now()]);

        $this->actingAs($b, 'sekolah')->get(route('sekolah.riwayat.pdf', $order->id))->assertNotFound();
    }
}
