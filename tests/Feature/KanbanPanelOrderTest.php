<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Kanban\DetailKartu;
use App\Models\Cabang;
use App\Models\Kanban\Kartu;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Notifications\KanbanKabar;
use App\Support\Kanban\Akses;
use App\Support\Kanban\PanelOrder;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Panel Order di kartu: format template tim, isian yang bisa dilengkapi, tim event jadi anggota. */
class KanbanPanelOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $marketing;

    private Cabang $cabang;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_merge(Akses::PERAN_STAF, ['marketing']) as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->admin = User::factory()->create(['nama' => 'Super', 'cabang_id' => $this->cabang->id]);
        $this->admin->assignRole('super_admin');
        $this->marketing = User::factory()->create(['nama' => 'Rudi Setiawan', 'cabang_id' => $this->cabang->id]);
        $this->marketing->assignRole('marketing');

        $this->sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-000032', 'nama' => 'RA MADANI',
            'alamat' => 'Jl. Diklat Pemda, Curug', 'kota' => 'Bandung',
            'pic_sekolah' => 'IBU DINI', 'no_telp_pic' => '628889681567',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    private function order(array $isian = []): Order
    {
        return Order::create(array_merge([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $this->marketing->id,
            'cabang_id' => $this->cabang->id,
            'status' => OrderStatus::BARU,
            'total' => 90000,
            'tanggal_event' => '2026-09-23',
            'jam_event' => '08:30',
        ], $isian));
    }

    private function kartu(Order $order): Kartu
    {
        return Kartu::where('order_id', $order->id)->firstOrFail();
    }

    private function detail(Order $order, ?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->admin)
            ->test(DetailKartu::class, ['kartuId' => $this->kartu($order)->id]);
    }

    public function test_panel_memakai_susunan_template_tim(): void
    {
        $order = $this->order();

        $label = collect(PanelOrder::baris($order))->pluck('label')->all();

        $this->assertSame([
            'NAMA SEKOLAH', 'ALAMAT SEKOLAH', 'PIC SEKOLAH', 'NO TELP PIC', 'HARGA',
            'TEAM EVENT', 'TANGGAL EVENT', 'JAM EVENT', 'EMAIL GURU', 'KET', 'MAPS', 'TEMA YEARBOOK',
        ], $label);
    }

    public function test_panel_diisi_dari_data_order_dan_sekolah(): void
    {
        $order = $this->order(['keterangan' => 'FREE BERSAMA FREE PAS FOTO']);

        $this->detail($order)
            ->assertSee('RA MADANI')
            ->assertSee('Jl. Diklat Pemda, Curug')
            ->assertSee('IBU DINI')
            ->assertSee('628889681567')
            ->assertSee('90.000')
            ->assertSee('23 September 2026')
            ->assertSee('08:30')
            ->assertSee('FREE BERSAMA FREE PAS FOTO');
    }

    public function test_isian_kosong_dilengkapi_dari_kartu(): void
    {
        $order = $this->order();

        // KET & TEMA YEARBOOK memang sering kosong dari web order.
        $this->detail($order)
            ->set('orderIsi.ket', 'Bawa payung, lokasi terbuka')
            ->call('simpanIsiOrder', 'ket')
            ->set('orderIsi.tema', 'Retro 90an')
            ->call('simpanIsiOrder', 'tema');

        $order->refresh();
        $this->assertSame('Bawa payung, lokasi terbuka', $order->keterangan);
        $this->assertSame('Retro 90an', $order->tema_yearbook);
    }

    public function test_isian_sekolah_tersimpan_ke_data_sekolah(): void
    {
        $order = $this->order();

        $this->detail($order)
            ->set('orderIsi.email', 'guru@ramadani.sch.id')
            ->call('simpanIsiOrder', 'email');

        $this->assertSame('guru@ramadani.sch.id', $this->sekolah->fresh()->email_guru);
    }

    public function test_isian_dikosongkan_lagi(): void
    {
        $order = $this->order(['keterangan' => 'Catatan lama']);

        $this->detail($order)->set('orderIsi.ket', '')->call('simpanIsiOrder', 'ket');

        $this->assertNull($order->fresh()->keterangan);
    }

    public function test_kunci_isian_ngawur_ditolak(): void
    {
        $order = $this->order();

        $this->detail($order)->set('orderIsi.harga', '1')->call('simpanIsiOrder', 'harga')->assertStatus(422);
    }

    public function test_tim_event_jadi_anggota_kartu_dan_dikabari(): void
    {
        Notification::fake();
        $tim = User::factory()->create(['nama' => 'Indri', 'cabang_id' => $this->cabang->id]);
        $tim->assignRole('tim_event');

        $order = $this->order();
        $order->timEvent()->sync([$tim->id]);

        Livewire::actingAs($this->admin)
            ->test(OrderDetail::class, ['orderId' => $order->id, 'konteks' => 'staf'])
            ->set('timEventTerpilih', [$tim->id])
            ->call('simpanTimEvent');

        $this->assertTrue($this->kartu($order)->anggota()->whereKey($tim->id)->exists());
        Notification::assertSentTo($tim, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::DITUGASKAN);
    }

    public function test_teks_salin_memuat_seluruh_baris(): void
    {
        $order = $this->order(['keterangan' => 'Catatan']);

        $teks = PanelOrder::teks($order);

        $this->assertStringContainsString('NAMA SEKOLAH: RA MADANI', $teks);
        $this->assertStringContainsString('JAM EVENT: 08:30', $teks);
        $this->assertStringContainsString('TEMA YEARBOOK: ', $teks);
    }
}
