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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event'] as $r) {
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
        $u->assignRole('admin_sales');

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

    // ---------- O3: pembayaran (nominal → status otomatis) ----------

    public function test_catat_dp_pending_lalu_approve_status_dp(): void
    {
        Storage::fake('public');
        $order = $this->order($this->jkt, 'baru'); // total 100.000

        // Marketing catat DP (bukti wajib) → PENDING, belum dihitung.
        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJenis', 'dp')
            ->set('bayarJumlah', 40000)
            ->set('bayarTanggal', now()->toDateString())
            ->set('bayarBukti', UploadedFile::fake()->image('bukti.jpg'))
            ->call('catatPembayaran')
            ->assertHasNoErrors();

        $order->refresh()->load('pembayaran');
        $this->assertSame('baru', $order->status);          // pending → belum dihitung
        $this->assertSame(0, $order->totalDibayar());
        $bayar = $order->pembayaran()->first();
        $this->assertSame('pending', $bayar->status);

        // Bukti WAJIB: tanpa bukti gagal.
        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJumlah', 10000)->set('bayarTanggal', now()->toDateString())
            ->call('catatPembayaran')
            ->assertHasErrors('bayarBukti');

        // Admin sales approve → baru dihitung.
        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('approvePembayaran', $bayar->id);

        $order->refresh()->load('pembayaran');
        $this->assertSame('dp', $order->status);
        $this->assertSame(60000, $order->outstanding());
    }

    public function test_nominal_tak_boleh_melebihi_tagihan(): void
    {
        Storage::fake('public');
        $order = $this->order($this->jkt, 'baru'); // total 100.000

        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJenis', 'dp')->set('bayarJumlah', 150000)->set('bayarTanggal', now()->toDateString())
            ->set('bayarBukti', UploadedFile::fake()->image('b.jpg'))
            ->call('catatPembayaran')
            ->assertHasErrors('bayarJumlah'); // melebihi sisa tagihan
    }

    public function test_pelunasan_approve_lalu_batal(): void
    {
        Storage::fake('public');
        $order = $this->order($this->jkt, 'baru');

        Livewire::actingAs($this->area($this->jkt))->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJumlah', 100000)->set('bayarJenis', 'pelunasan')->set('bayarTanggal', now()->toDateString())
            ->set('bayarBukti', UploadedFile::fake()->image('b.jpg'))
            ->call('catatPembayaran');

        $bayar = $order->pembayaran()->first();
        Livewire::actingAs($this->area($this->jkt))->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('approvePembayaran', $bayar->id);
        $this->assertSame('lunas', $order->refresh()->status);

        Livewire::actingAs($this->area($this->jkt))->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('ubahStatus', 'batal');
        $this->assertSame('batal', $order->refresh()->status);
    }

    public function test_nominal_non_integer_ditolak(): void
    {
        $order = $this->order($this->jkt, 'baru');

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('bayarJumlah', 0) // < 1
            ->set('bayarTanggal', now()->toDateString())
            ->call('catatPembayaran')
            ->assertHasErrors('bayarJumlah');

        $this->assertSame(0, $order->refresh()->totalDibayar());
    }

    public function test_ubah_status_hanya_batal_aktifkan(): void
    {
        $order = $this->order($this->jkt, 'baru');

        // dp/lunas tak bisa lewat ubahStatus (hanya batal/aktifkan).
        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('ubahStatus', 'dp')
            ->assertStatus(422);
    }

    // ---------- Diskon per item: ajukan → setujui/tolak ----------

    private function orderDenganItem(): array
    {
        $order = $this->order($this->jkt, 'baru'); // total 100.000
        $item = $order->items()->create(['tipe_item' => 'produk', 'produk_id' => null, 'qty' => 1, 'harga' => 100000, 'is_free' => false]);

        return [$order, $item];
    }

    public function test_ajukan_lalu_setujui_diskon_per_item_ubah_nominal(): void
    {
        [$order, $item] = $this->orderDenganItem();

        // Marketing ajukan diskon 20.000/satuan pada item.
        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('diskonItem.'.$item->id, 20000)
            ->call('ajukanDiskon');
        $order->refresh();
        $this->assertSame('diajukan', $order->diskon_status);
        $this->assertSame(20000, $item->refresh()->diskon_diajukan);
        $this->assertSame(0, $item->diskon); // belum efektif

        // Admin sales setujui, ubah jadi 15.000.
        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('diskonItem.'.$item->id, 15000)
            ->call('setujuiDiskon');
        $order->refresh()->load('items');
        $this->assertSame('disetujui', $order->diskon_status);
        $this->assertSame(15000, $item->refresh()->diskon);
        $this->assertSame(85000, $order->outstanding()); // 100.000 - 15.000
    }

    public function test_marketing_tak_bisa_setujui_diskon(): void
    {
        [$order, $item] = $this->orderDenganItem();
        $order->update(['diskon_status' => 'diajukan']);

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->set('diskonItem.'.$item->id, 20000)
            ->call('setujuiDiskon')
            ->assertStatus(403);

        $this->assertSame(0, $item->refresh()->diskon);
    }

    public function test_tolak_diskon(): void
    {
        [$order, $item] = $this->orderDenganItem();
        $item->update(['diskon_diajukan' => 20000]);
        $order->update(['diskon_status' => 'diajukan']);

        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('tolakDiskon');

        $order->refresh();
        $this->assertSame('ditolak', $order->diskon_status);
        $this->assertSame(0, $item->refresh()->diskon);
    }

    // ---------- O4: milestone event ----------

    public function test_konfirmasi_milestone_h7_oleh_admin_sales(): void
    {
        $order = $this->order($this->jkt);
        // DP sudah masuk → H-7 boleh dikonfirmasi (berurutan).
        $order->update(['tanggal_event' => now()->addDays(10)->toDateString(), 'status' => 'dp']);

        // H-7 kini wewenang admin sales (area), bukan marketing.
        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'h7');

        $this->assertNotNull($order->refresh()->konfirmasi_h7_at);
        $states = collect($order->milestones())->keyBy('key');
        $this->assertSame('confirmed', $states['h7']['state']);
    }

    public function test_marketing_tak_bisa_konfirmasi_milestone(): void
    {
        $order = $this->order($this->jkt);
        $order->update(['tanggal_event' => now()->addDays(10)->toDateString()]);

        Livewire::actingAs($this->marketing($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'h7')
            ->assertStatus(403);

        $this->assertNull($order->refresh()->konfirmasi_h7_at);
    }

    public function test_hari_h_tak_bisa_dikonfirmasi_di_panel_staf(): void
    {
        $order = $this->order($this->jkt);
        $order->update(['tanggal_event' => now()->addDays(10)->toDateString()]);

        // Hari-H (hh) hanya untuk tim event → panel staf menolak.
        Livewire::actingAs($this->area($this->jkt))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('konfirmasiMilestone', 'hh')
            ->assertStatus(422);

        $this->assertNull($order->refresh()->konfirmasi_hh_at);
    }

    public function test_state_milestone_overdue_upcoming_dan_countdown(): void
    {
        $order = $this->order($this->jkt);
        // DP sudah masuk → H-7 terbuka; H-2 & Hari-H masih terkunci (berurutan).
        $order->update(['tanggal_event' => now()->addDays(5)->toDateString(), 'status' => 'dp']);

        $ms = collect($order->milestones())->keyBy('key');
        $this->assertSame('overdue', $ms['h7']['state']);  // due = event-7 = -2 hari, DP beres → terbuka
        $this->assertSame('locked', $ms['h2']['state']);   // H-7 belum → terkunci
        $this->assertSame('locked', $ms['hh']['state']);   // H-2 belum → terkunci

        $this->assertSame('H-5', $order->eventCountdown()['label']);
    }

    public function test_konfirmasi_milestone_butuh_tanggal_event(): void
    {
        $order = $this->order($this->jkt); // tanpa tanggal_event

        Livewire::actingAs($this->area($this->jkt))
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
        // STE terbit setelah konfirmasi H-2.
        $order = $this->order($this->jkt);
        $order->update(['tanggal_event' => now()->addDays(2)->toDateString(), 'konfirmasi_h2_at' => now()]);
        $order->timEvent()->attach($this->timEvent($this->jkt)->id);

        $res = $this->actingAs($this->marketing($this->jkt))
            ->get(route('app.order.ste', $order->id));

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_ste_diblokir_sebelum_h2(): void
    {
        $order = $this->order($this->jkt); // belum H-2
        $order->timEvent()->attach($this->timEvent($this->jkt)->id);

        $this->actingAs($this->marketing($this->jkt))
            ->get(route('app.order.ste', $order->id))
            ->assertStatus(403);
    }
}
