<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Booking\OrderIndex;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\RoleMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrderManajemenO2Test extends TestCase
{
    use RefreshDatabase;

    private Cabang $jkt;

    private Cabang $bdg;

    private Sekolah $sekolahJkt;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'area', 'marketing', 'tim_event'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->jkt = Cabang::create(['nama' => 'DMA Jakarta', 'kode_area' => 'JKT']);
        $this->bdg = Cabang::create(['nama' => 'DMA Bandung', 'kode_area' => 'BDG']);
        $this->sekolahJkt = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD JKT', 'cabang_id' => $this->jkt->id]);
    }

    private function marketing(Cabang $cabang): User
    {
        $u = User::factory()->create(['cabang_id' => $cabang->id]);
        $u->assignRole('marketing');

        return $u;
    }

    private function area(Cabang $cabang): User
    {
        $u = User::factory()->create(['cabang_id' => $cabang->id]);
        $u->assignRole('area');

        return $u;
    }

    private function timEvent(Cabang $cabang): User
    {
        $u = User::factory()->create(['cabang_id' => $cabang->id]);
        $u->assignRole('tim_event');

        return $u;
    }

    private function order(Cabang $cabang, string $status = 'baru'): Order
    {
        return Order::create([
            'sekolah_id' => $this->sekolahJkt->id,
            'cabang_id' => $cabang->id,
            'sumber' => 'sekolah',
            'status' => $status,
            'total' => 100000,
            'jumlah_siswa' => 20,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_menu_order_kini_punya_route(): void
    {
        $items = RoleMenu::for($this->marketing($this->jkt));
        $order = collect($items)->firstWhere('label', 'Order');

        $this->assertSame('app.order.index', $order['route']);
    }

    public function test_daftar_order_ter_scope_cabang(): void
    {
        $orderJkt = $this->order($this->jkt);
        $this->order($this->bdg); // cabang lain — tak boleh tampil

        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderIndex::class)
            ->assertSee('Order #'.$orderJkt->id)
            ->assertDontSee('Order #'.$this->order($this->bdg)->id);
    }

    public function test_filter_status(): void
    {
        $baru = $this->order($this->jkt, 'baru');
        $lunas = $this->order($this->jkt, 'lunas');

        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderIndex::class)
            ->set('status', 'lunas')
            ->assertSee('Order #'.$lunas->id)
            ->assertDontSee('Order #'.$baru->id);
    }

    public function test_marketing_ubah_tanggal_event(): void
    {
        $order = $this->order($this->jkt);
        $tgl = now()->addDays(20)->toDateString();

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('tanggalEvent', $tgl)
            ->set('jamEvent', '09:30')
            ->call('simpanJadwal')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame($tgl, $order->tanggal_event->toDateString());
        $this->assertSame('09:30', $order->jam_event);
    }

    public function test_ubah_jadwal_wajib_tanggal(): void
    {
        $order = $this->order($this->jkt);

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('tanggalEvent', '')
            ->call('simpanJadwal')
            ->assertHasErrors('tanggalEvent');
    }

    // ---------- O3: status pembayaran ----------

    public function test_konfirmasi_dp_dan_catatan(): void
    {
        $order = $this->order($this->jkt, 'baru');

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('catatan', 'DP 50% via BCA')
            ->call('ubahStatus', 'dp');

        $order->refresh();
        $this->assertSame('dp', $order->status);
        $this->assertSame('DP 50% via BCA', $order->keterangan);
    }

    public function test_dp_ke_lunas_lalu_batal(): void
    {
        $order = $this->order($this->jkt, 'dp');
        $mkt = $this->marketing($this->jkt);

        Livewire::actingAs($mkt)->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('ubahStatus', 'lunas');
        $this->assertSame('lunas', $order->refresh()->status);

        Livewire::actingAs($mkt)->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('ubahStatus', 'batal');
        $this->assertSame('batal', $order->refresh()->status);
    }

    public function test_transisi_tak_valid_ditolak(): void
    {
        $order = $this->order($this->jkt, 'lunas'); // lunas → dp tidak diizinkan

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('ubahStatus', 'dp')
            ->assertStatus(422);

        $this->assertSame('lunas', $order->refresh()->status);
    }

    // ---------- O4: milestone event ----------

    public function test_konfirmasi_milestone_h7(): void
    {
        $order = $this->order($this->jkt);
        $order->update(['tanggal_event' => now()->addDays(10)->toDateString()]);

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'h7');

        $this->assertNotNull($order->refresh()->konfirmasi_h7_at);
        $states = collect($order->milestones())->keyBy('key');
        $this->assertSame('confirmed', $states['h7']['state']);
    }

    public function test_state_milestone_overdue_upcoming_dan_countdown(): void
    {
        $order = $this->order($this->jkt);
        $order->update(['tanggal_event' => now()->addDays(5)->toDateString()]);

        $ms = collect($order->milestones())->keyBy('key');
        $this->assertSame('overdue', $ms['h7']['state']);   // due = event-7 = -2 hari
        $this->assertSame('upcoming', $ms['h2']['state']);  // due = +3 hari
        $this->assertSame('upcoming', $ms['hh']['state']);  // due = +5 hari

        $this->assertSame('H-5', $order->eventCountdown()['label']);
    }

    public function test_konfirmasi_milestone_butuh_tanggal_event(): void
    {
        $order = $this->order($this->jkt); // tanpa tanggal_event

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'h7')
            ->assertStatus(422);
    }

    // ---------- O5: tim event & STE ----------

    public function test_assign_tim_event(): void
    {
        $order = $this->order($this->jkt);
        $t1 = $this->timEvent($this->jkt);
        $t2 = $this->timEvent($this->jkt);

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('timEventTerpilih', [(string) $t1->id, (string) $t2->id])
            ->call('simpanTimEvent');

        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $order->timEvent()->pluck('users.id')->all());
    }

    public function test_tim_event_lintas_cabang_ditolak(): void
    {
        $order = $this->order($this->jkt);
        $timBdg = $this->timEvent($this->bdg); // cabang lain

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('timEventTerpilih', [(string) $timBdg->id])
            ->call('simpanTimEvent');

        $this->assertCount(0, $order->timEvent()->get()); // difilter, tak ter-assign
    }

    public function test_ste_pdf_dapat_diakses_staf(): void
    {
        $order = $this->order($this->jkt);
        $order->timEvent()->attach($this->timEvent($this->jkt)->id);

        $res = $this->actingAs($this->marketing($this->jkt))
            ->get(route('app.order.ste', $order->id));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }
}
