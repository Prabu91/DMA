<?php

namespace Tests\Feature;

use App\Livewire\PengaturanIndex;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiReportOrderTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'token-uji-report-order';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'admin_sales', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function aktifkanToken(): void
    {
        Pengaturan::set(Pengaturan::API_TOKEN_HASH, Hash::make($this->token));
    }

    /** Satu order dengan satu item berbayar; nominal = (harga - diskon) * qty. */
    private function order(int $harga = 100000, int $qty = 3, int $diskon = 0): Order
    {
        $cabang = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-BDG-0001', 'nama' => 'SD Merdeka', 'cabang_id' => $cabang->id]);
        $marketing = User::factory()->create(['cabang_id' => $cabang->id, 'nama' => 'Rudi Setiawan']);
        $marketing->assignRole('marketing');

        $kategori = Kategori::create(['nama' => 'Yearbook', 'pakai_desain' => false]);
        $produk = Produk::create(['kategori_id' => $kategori->id, 'nama' => 'Yearbook', 'harga' => $harga, 'status' => 'aktif']);

        $order = Order::create([
            'booking_code' => '070926BDG00001',
            'sekolah_id' => $sekolah->id,
            'marketing_id' => $marketing->id,
            'cabang_id' => $cabang->id,
            'status' => 'baru',
            'tanggal_booking' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'tipe_item' => 'produk',
            'produk_id' => $produk->id,
            'qty' => $qty,
            'harga' => $harga,
            'diskon' => $diskon,
            'is_free' => false,
        ]);

        return $order;
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->aktifkanToken();
        $this->order();

        $this->getJson('/api/v1/report-order/ringkasan')->assertStatus(401);
        $this->getJson('/api/v1/report-order')->assertStatus(401);
    }

    public function test_token_salah_ditolak(): void
    {
        $this->aktifkanToken();

        $this->withToken('token-ngawur')
            ->getJson('/api/v1/report-order/ringkasan')
            ->assertStatus(401);
    }

    public function test_api_tertutup_bila_token_belum_pernah_dibuat(): void
    {
        // Default harus TERTUTUP, bukan terbuka.
        $this->withToken($this->token)
            ->getJson('/api/v1/report-order/ringkasan')
            ->assertStatus(503);
    }

    public function test_ringkasan_mengembalikan_total_omset(): void
    {
        $this->aktifkanToken();
        $this->order(harga: 100000, qty: 3);

        $this->withToken($this->token)
            ->getJson('/api/v1/report-order/ringkasan')
            ->assertOk()
            ->assertJsonPath('ringkasan.baris', 1)
            ->assertJsonPath('ringkasan.qty', 3)
            ->assertJsonPath('ringkasan.nominal', 300000);
    }

    public function test_detail_memuat_baris_order(): void
    {
        $this->aktifkanToken();
        $this->order(harga: 100000, qty: 3, diskon: 10000);

        $this->withToken($this->token)
            ->getJson('/api/v1/report-order')
            ->assertOk()
            ->assertJsonPath('data.0.booking_code', '070926BDG00001')
            ->assertJsonPath('data.0.marketing', 'Rudi Setiawan')
            ->assertJsonPath('data.0.sekolah.id_sekolah', 'SKL-BDG-0001')
            ->assertJsonPath('data.0.item.nama', 'Yearbook')
            ->assertJsonPath('data.0.qty', 3)
            ->assertJsonPath('data.0.nominal', 270000)  // (100rb - 10rb) x 3
            ->assertJsonPath('data.0.order_dihapus', false)
            ->assertJsonPath('meta.total_baris', 1);
    }

    public function test_order_dihapus_ditandai_tapi_tak_masuk_nominal(): void
    {
        // Sama seperti halaman Report order: barisnya tetap terlihat & ditandai,
        // tapi uangnya tidak ikut dihitung.
        $this->aktifkanToken();
        $order = $this->order(harga: 100000, qty: 3);
        $order->delete();

        $this->withToken($this->token)
            ->getJson('/api/v1/report-order')
            ->assertOk()
            ->assertJsonPath('data.0.order_dihapus', true)
            ->assertJsonPath('ringkasan.baris', 1)
            ->assertJsonPath('ringkasan.nominal', 0);
    }

    public function test_filter_tanggal_dipakai(): void
    {
        $this->aktifkanToken();
        $this->order();

        $this->withToken($this->token)
            ->getJson('/api/v1/report-order/ringkasan?dari=2000-01-01&sampai=2000-12-31')
            ->assertOk()
            ->assertJsonPath('ringkasan.baris', 0)
            ->assertJsonPath('ringkasan.nominal', 0);
    }

    public function test_filter_ngawur_ditolak(): void
    {
        $this->aktifkanToken();

        $this->withToken($this->token)
            ->getJson('/api/v1/report-order?jenis=bukan-pilihan')
            ->assertStatus(422);
    }

    public function test_admin_bisa_membuat_dan_mencabut_token(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $comp = Livewire::actingAs($admin)->test(PengaturanIndex::class);

        $comp->call('buatTokenApi');
        $token = $comp->get('tokenBaru');

        $this->assertNotEmpty($token);
        // Yang tersimpan hash, bukan token mentahnya.
        $this->assertNotSame($token, Pengaturan::teks(Pengaturan::API_TOKEN_HASH));
        $this->assertTrue(Hash::check($token, Pengaturan::teks(Pengaturan::API_TOKEN_HASH)));

        // Token hasil halaman Pengaturan benar-benar dipakai API.
        $this->withToken($token)->getJson('/api/v1/report-order/ringkasan')->assertOk();

        $comp->call('cabutTokenApi');
        $this->assertNull(Pengaturan::teks(Pengaturan::API_TOKEN_HASH));
        $this->withToken($token)->getJson('/api/v1/report-order/ringkasan')->assertStatus(503);
    }

    public function test_marketing_tak_bisa_membuat_token(): void
    {
        $marketing = User::factory()->create();
        $marketing->assignRole('marketing');

        $this->actingAs($marketing)->get(route('app.pengaturan'))->assertForbidden();
    }
}
