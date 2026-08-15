<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Kecamatan;
use App\Models\Kota;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Fase B: auto-assign order sekolah → marketing berdasarkan kecamatan.
 */
class MarketingRouterTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private Kota $kota;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cabang = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $this->kota = Kota::create(['nama' => 'Jakarta', 'cabang_id' => $this->cabang->id]);
    }

    private function marketing(?Kecamatan $kecamatan = null): User
    {
        $u = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $u->assignRole('marketing');
        if ($kecamatan) {
            $u->kecamatan()->attach($kecamatan->id);
        }

        return $u;
    }

    private function sekolah(?Kecamatan $kecamatan = null): Sekolah
    {
        return Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SDN Uji '.uniqid(),
            'cabang_id' => $this->cabang->id,
            'kecamatan_id' => $kecamatan?->id,
        ]);
    }

    /** Buat order jalur sekolah lewat mesin booking (memicu auto-assign). */
    private function buatOrderSekolah(Sekolah $sekolah): Order
    {
        $kategori = Kategori::create(['nama' => 'Souvenir', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Gantungan', 'harga' => 15000, 'status' => 'aktif']);

        $cart = new Cart;
        $cart->add(['tipe_item' => 'produk', 'produk_id' => $produk->id, 'qty' => 10]);
        $svc = app(BookingService::class);
        $lines = $svc->resolveLines($cart);

        $ctx = [
            'sekolah_id' => $sekolah->id,
            'marketing_id' => null,
            'cabang_id' => $this->cabang->id,
            'sumber' => 'sekolah',
        ];

        return $svc->simpan($ctx, $lines, [], 10, $svc->subtotal($lines));
    }

    public function test_order_auto_assign_ke_marketing_pemilik_kecamatan(): void
    {
        $kecamatan = Kecamatan::create(['nama' => 'Kebayoran Baru', 'kota_id' => $this->kota->id]);
        $marketing = $this->marketing($kecamatan);
        $sekolah = $this->sekolah($kecamatan);

        $order = $this->buatOrderSekolah($sekolah);

        $this->assertSame($marketing->id, $order->marketing_id);
        $this->assertNotNull($order->booking_code); // kode dibuat saat marketing terisi
    }

    public function test_tanpa_marketing_kecamatan_order_tetap_menunggu(): void
    {
        $kecamatan = Kecamatan::create(['nama' => 'Cilandak', 'kota_id' => $this->kota->id]);
        // Tidak ada marketing yang memegang kecamatan ini.
        $this->marketing(); // marketing tanpa kecamatan
        $sekolah = $this->sekolah($kecamatan);

        $order = $this->buatOrderSekolah($sekolah);

        $this->assertNull($order->marketing_id);
        $this->assertNull($order->booking_code);
    }

    public function test_sekolah_tanpa_kecamatan_tidak_auto_assign(): void
    {
        $kecamatan = Kecamatan::create(['nama' => 'Pancoran', 'kota_id' => $this->kota->id]);
        $this->marketing($kecamatan);
        $sekolah = $this->sekolah(null); // kecamatan_id null

        $order = $this->buatOrderSekolah($sekolah);

        $this->assertNull($order->marketing_id);
    }

    public function test_marketing_beda_cabang_tidak_dipilih(): void
    {
        $kecamatan = Kecamatan::create(['nama' => 'Setiabudi', 'kota_id' => $this->kota->id]);
        // Marketing cabang lain, walau (secara hipotetis) memegang kecamatan ini.
        $cabangLain = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $mktLain = User::factory()->create(['cabang_id' => $cabangLain->id]);
        $mktLain->assignRole('marketing');
        $mktLain->kecamatan()->attach($kecamatan->id);

        $sekolah = $this->sekolah($kecamatan);
        $order = $this->buatOrderSekolah($sekolah);

        $this->assertNull($order->marketing_id); // beda cabang → tak dipilih
    }
}
