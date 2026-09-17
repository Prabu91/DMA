<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Kanban\SinkronOrder;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Board Order: tiap order otomatis punya kartu di list marketing-nya, dan
 * kartu tetap bebas diseret ke list mana pun.
 */
class KanbanOrderTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private Sekolah $sekolah;

    private User $shanty;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->sekolah = Sekolah::create(['id_sekolah' => '28981678', 'nama' => 'TK Miftahul Khoir', 'cabang_id' => $this->bdg->id]);
        $this->shanty = $this->staf('marketing', 'Shanty');
    }

    private function staf(string $role, string $nama): User
    {
        $u = User::factory()->create(['nama' => $nama, 'cabang_id' => $this->bdg->id]);
        $u->assignRole($role);

        return $u;
    }

    private function order(array $atribut = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'booking_code' => 'BK-KANBAN-'.$n,
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $this->shanty->id,
            'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing',
            'status' => OrderStatus::BARU,
            'total' => 100000,
            'tanggal_booking' => now(),
        ], $atribut));
    }

    private function kartu(Order $order): ?Kartu
    {
        return Kartu::where('order_id', $order->id)->first();
    }

    public function test_order_baru_membuat_kartu_di_list_marketing(): void
    {
        $order = $this->order();

        $kartu = $this->kartu($order);
        $this->assertNotNull($kartu);
        $this->assertSame(Board::order()->id, $kartu->board_id);
        $this->assertSame('28981678_TK MIFTAHUL KHOIR_SHANTY', $kartu->judul);

        $kolom = $kartu->kolom;
        $this->assertSame($this->shanty->id, $kolom->marketing_id);
        $this->assertSame('Shanty (Bandung)', $kolom->nama);
    }

    public function test_order_kedua_marketing_sama_masuk_list_yang_sama_di_bawah(): void
    {
        $a = $this->order();
        $b = $this->order();

        $this->assertSame($this->kartu($a)->kolom_id, $this->kartu($b)->kolom_id);
        $this->assertGreaterThan($this->kartu($a)->posisi, $this->kartu($b)->posisi);
        $this->assertSame(1, Kolom::where('marketing_id', $this->shanty->id)->count());
    }

    public function test_order_tanpa_marketing_masuk_list_tanpa_marketing(): void
    {
        $order = $this->order(['marketing_id' => null, 'sumber' => 'sekolah']);

        $this->assertSame(SinkronOrder::LIST_TANPA_MARKETING, $this->kartu($order)->kolom->nama);
        $this->assertSame('28981678_TK MIFTAHUL KHOIR', $this->kartu($order)->judul);
    }

    public function test_order_susulan_diberi_label_susulan(): void
    {
        $induk = $this->order();
        $susulan = $this->order(['order_induk_id' => $induk->id]);

        $this->assertSame([SinkronOrder::LABEL_SUSULAN], $this->kartu($susulan)->label->pluck('nama')->all());
        $this->assertCount(0, $this->kartu($induk)->label);
    }

    public function test_kartu_order_bisa_diseret_ke_list_mana_pun_dan_tidak_ditarik_balik(): void
    {
        $order = $this->order();
        $kartu = $this->kartu($order);
        $admin = $this->staf('admin_sales', 'Admin');
        $board = Board::order();
        $lain = app(Tata::class)->tambahKolom($board, 'Siap event', $admin);

        // Staf mana pun boleh menyeret kartu di board order.
        Livewire::actingAs($this->staf('tim_event', 'Tim'))->test(PapanBoard::class, ['board' => $board])
            ->call('urutKartu', $kartu->id, 0, $lain->id);
        $this->assertSame($lain->id, $kartu->fresh()->kolom_id);

        // Perubahan order lain (status) tidak memindahkan kartu kembali.
        $order->update(['status' => OrderStatus::DP]);
        $this->assertSame($lain->id, $kartu->fresh()->kolom_id);
    }

    public function test_ganti_marketing_memindah_kartu_bila_masih_di_list_lama(): void
    {
        $rina = $this->staf('marketing', 'Rina');
        $a = $this->order();
        $b = $this->order();
        $siap = app(Tata::class)->tambahKolom(Board::order(), 'Siap event', $rina);
        $this->kartu($b)->update(['kolom_id' => $siap->id]);

        $a->update(['marketing_id' => $rina->id]);
        $b->update(['marketing_id' => $rina->id]);

        $this->assertSame($rina->id, $this->kartu($a)->kolom->marketing_id);
        $this->assertSame('28981678_TK MIFTAHUL KHOIR_RINA', $this->kartu($a)->judul);
        $this->assertSame($siap->id, $this->kartu($b)->kolom_id, 'kartu yang sudah dipindah orang tidak diganggu');
        $this->assertSame('28981678_TK MIFTAHUL KHOIR_RINA', $this->kartu($b)->judul);
    }

    public function test_order_batal_atau_dihapus_mengarsipkan_kartu_dan_pulih_bila_aktif_lagi(): void
    {
        $order = $this->order();

        $order->update(['status' => OrderStatus::BATAL]);
        $this->assertNotNull($this->kartu($order)->diarsipkan_at);

        $order->update(['status' => OrderStatus::BARU]);
        $this->assertNull($this->kartu($order)->diarsipkan_at);

        $order->delete();
        $this->assertNotNull($this->kartu($order)->diarsipkan_at);

        $order->restore();
        $this->assertNull($this->kartu($order)->diarsipkan_at);
    }

    public function test_kartu_yang_diarsipkan_orang_tidak_dipulihkan_sinkron(): void
    {
        $order = $this->order();
        $user = $this->staf('admin_sales', 'Admin');

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $this->kartu($order)->id])->call('arsipkan');
        $order->update(['status' => OrderStatus::DP]);

        $this->assertNotNull($this->kartu($order)->diarsipkan_at);
    }

    public function test_order_batal_tidak_membuat_kartu(): void
    {
        $order = $this->order(['status' => OrderStatus::BATAL]);

        $this->assertNull($this->kartu($order));
    }

    public function test_list_marketing_yang_diarsipkan_dipulihkan_untuk_order_baru(): void
    {
        $a = $this->order();
        $kolom = $this->kartu($a)->kolom;
        $kolom->update(['diarsipkan_at' => now()]);

        $b = $this->order();

        $this->assertSame($kolom->id, $this->kartu($b)->kolom_id);
        $this->assertNull($kolom->fresh()->diarsipkan_at);
    }

    public function test_kartu_order_tidak_bisa_dihapus_permanen(): void
    {
        $order = $this->order();
        $kartu = $this->kartu($order);
        $kartu->update(['diarsipkan_at' => now()]);

        Livewire::actingAs($this->staf('super_admin', 'Super'))->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->assertSee('Order')
            ->assertDontSee('Hapus permanen')
            ->call('hapus')->assertStatus(422);
    }

    public function test_detail_kartu_menampilkan_panel_order(): void
    {
        $order = $this->order(['tanggal_event' => '2026-10-05']);

        Livewire::actingAs($this->shanty)->test(DetailKartu::class, ['kartuId' => $this->kartu($order)->id])
            ->assertSee($order->booking_code)
            ->assertSee('TK Miftahul Khoir')
            ->assertSee('Menunggu DP')
            ->assertSee($order->tanggal_event->translatedFormat('j M Y'))
            ->assertSee('Buka order di panel staf');
    }

    public function test_perintah_backfill_membuat_kartu_order_lama(): void
    {
        $lama = $this->order();
        $selesai = $this->order(['event_status' => OrderStatus::EVENT_SELESAI]);
        Kartu::query()->delete();

        $this->artisan('kanban:sinkron-order')->expectsOutput('Kartu dibuat: 1.')->assertSuccessful();
        $this->assertNotNull($this->kartu($lama));
        $this->assertNull($this->kartu($selesai));

        $this->artisan('kanban:sinkron-order --termasuk-selesai')->expectsOutput('Kartu dibuat: 1.')->assertSuccessful();
        $this->artisan('kanban:sinkron-order --termasuk-selesai')->expectsOutput('Kartu dibuat: 0.')->assertSuccessful();
        $this->assertNotNull($this->kartu($selesai));
    }

    public function test_gagal_sinkron_tidak_menggagalkan_order(): void
    {
        $this->app->bind(SinkronOrder::class, fn () => new class extends SinkronOrder
        {
            public function sinkron(Order $order): ?Kartu
            {
                throw new \RuntimeException('rusak');
            }
        });

        $order = $this->order();

        $this->assertModelExists($order);
        $this->assertNull($this->kartu($order));
    }
}
