<?php

namespace Tests\Feature;

use App\Livewire\Booking\KotakMasuk;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KotakMasukDetailTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'area', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['cabang_id' => $this->jkt->id]);
        $u->assignRole('operasional');

        return $u;
    }

    private function sekolah(string $nama): Sekolah
    {
        return Sekolah::create(['id_sekolah' => 'SKL-'.uniqid(), 'nama' => $nama, 'cabang_id' => $this->jkt->id]);
    }

    private function order(Sekolah $sekolah, array $attr = []): Order
    {
        return Order::create(array_merge([
            'sekolah_id' => $sekolah->id,
            'cabang_id' => $this->jkt->id,
            'sumber' => 'sekolah',
            'status' => 'baru',
            'total' => 100000,
            'jumlah_siswa' => 25,
            'tanggal_booking' => now(),
        ], $attr));
    }

    public function test_lihat_detail_membuka_modal(): void
    {
        $order = $this->order($this->sekolah('SD Merdeka'));

        Livewire::actingAs($this->admin())
            ->test(KotakMasuk::class)
            ->assertSet('detailId', null)
            ->call('lihatDetail', $order->id)
            ->assertSet('detailId', $order->id)
            ->assertSee('SD Merdeka')
            ->call('tutupDetail')
            ->assertSet('detailId', null);
    }

    public function test_filter_search_nama_sekolah(): void
    {
        $a = $this->order($this->sekolah('SD Melati'));
        $b = $this->order($this->sekolah('SD Mawar'));

        Livewire::actingAs($this->admin())
            ->test(KotakMasuk::class)
            ->set('q', 'Melati')
            ->assertSee('Booking #'.$a->id)
            ->assertDontSee('Booking #'.$b->id);
    }

    public function test_filter_tanggal_masuk(): void
    {
        $lama = $this->order($this->sekolah('SD Lama'), ['tanggal_booking' => now()->subDays(10)]);
        $baru = $this->order($this->sekolah('SD Baru'), ['tanggal_booking' => now()]);

        Livewire::actingAs($this->admin())
            ->test(KotakMasuk::class)
            ->set('dari', now()->subDays(2)->toDateString())
            ->assertSee('Booking #'.$baru->id)
            ->assertDontSee('Booking #'.$lama->id);
    }
}
