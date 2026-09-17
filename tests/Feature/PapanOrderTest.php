<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\Event\EventDetail;
use App\Livewire\Papan\PapanOrder;
use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\PapanDitolak;
use App\Services\PapanOrder as AturanPapan;
use App\Support\OrderStatus;
use App\Support\RoleMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Papan order (kanban) — pengganti board Trello tahap C sampai P.
 */
class PapanOrderTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private Sekolah $sekolah;

    private User $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->sekolah = Sekolah::create(['id_sekolah' => '28981678', 'nama' => 'TK Miftahul Khoir', 'cabang_id' => $this->bdg->id]);
        $this->marketing = $this->staf('marketing', 'Shanty');
    }

    private function staf(string $role, string $nama = 'Staf', ?int $cabangId = -1): User
    {
        $u = User::factory()->create(['nama' => $nama, 'cabang_id' => $cabangId === -1 ? $this->bdg->id : $cabangId]);
        $u->assignRole($role);

        return $u;
    }

    private function admin(): User
    {
        return $this->staf('admin_sales', 'Admin', null);
    }

    private function order(array $atribut = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'booking_code' => 'BK-PAPAN-'.$n,
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $this->marketing->id,
            'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing',
            'status' => 'dp',
            'tanggal_event' => now()->subDays(2)->toDateString(),
            'konfirmasi_hh_at' => now()->subDays(2),
            'event_status' => OrderStatus::EVENT_SELESAI,
            'tahap' => 'C',
            'tahap_masuk_at' => now()->subDay(),
            'total' => 100000,
            'tanggal_booking' => now()->subMonth(),
        ], $atribut));
    }

    private function aturan(): AturanPapan
    {
        return app(AturanPapan::class);
    }

    private function papan(User $user, array $param = [])
    {
        return Livewire::actingAs($user)->withQueryParams($param)->test(PapanOrder::class);
    }

    // ---------------- Masuk papan ----------------

    public function test_konfirmasi_hari_h_memasukkan_kartu_ke_collect_admin(): void
    {
        $order = $this->order([
            'konfirmasi_hh_at' => null, 'event_status' => OrderStatus::EVENT_DIJADWALKAN,
            'tahap' => null, 'tahap_masuk_at' => null,
            'tanggal_event' => now()->toDateString(),
            'konfirmasi_lokasi_at' => now(), 'konfirmasi_h2_at' => now(),
        ]);
        $tim = $this->staf('tim_event');
        $order->timEvent()->attach($tim->id);

        Livewire::actingAs($tim)->test(EventDetail::class, ['orderId' => $order->id])->call('konfirmasiHariH');

        $order->refresh();
        $this->assertSame('C', $order->tahap);
        $this->assertNotNull($order->tahap_masuk_at);
    }

    public function test_order_yang_belum_hari_h_tidak_bisa_dipindah(): void
    {
        $order = $this->order(['konfirmasi_hh_at' => null, 'tahap' => null]);

        $this->expectException(PapanDitolak::class);
        $this->aturan()->pindahTahap($order, 'E', $this->admin());
    }

    // ---------------- Aturan pindah ----------------

    public function test_admin_memindah_kartu_dan_tercatat(): void
    {
        $pj = $this->staf('admin_sales', 'Riri', null);
        $order = $this->order(['tahap_pj_id' => $pj->id, 'tertahan_alasan' => 'berkas kurang', 'tertahan_at' => now()]);

        $this->aturan()->pindahTahap($order, 'E', $this->admin());

        $order->refresh();
        $this->assertSame('E', $order->tahap);
        $this->assertNull($order->tahap_pj_id);        // penanggung jawab tahap lama dilepas
        $this->assertNull($order->tertahan_alasan);    // tanda tertahan tidak terbawa
        $this->assertSame(1, $order->activities()->where('action', 'papan_pindah')->count());
    }

    public function test_masuk_qc_marketing_otomatis_dipegang_marketing_order(): void
    {
        $order = $this->order(['tahap' => 'E']);

        $this->aturan()->pindahTahap($order, 'F', $this->admin());

        $this->assertSame($this->marketing->id, $order->fresh()->tahap_pj_id);
    }

    public function test_selesai_ditolak_bila_belum_lunas(): void
    {
        $order = $this->order(['tahap' => 'N', 'status' => OrderStatus::DP]);

        try {
            $this->aturan()->pindahTahap($order, 'P', $this->admin());
            $this->fail('Seharusnya ditolak.');
        } catch (PapanDitolak $e) {
            $this->assertStringContainsString('belum lunas', $e->getMessage());
        }
        $this->assertSame('N', $order->fresh()->tahap);
    }

    public function test_selesai_boleh_bila_lunas(): void
    {
        $order = $this->order(['tahap' => 'N', 'status' => OrderStatus::LUNAS]);

        $this->aturan()->pindahTahap($order, 'P', $this->admin());

        $this->assertSame('P', $order->fresh()->tahap);
    }

    public function test_editor_hanya_memindah_kartu_editing_ke_qc(): void
    {
        $editor = $this->staf('editor', 'Abeng', null);
        $diEdit = $this->order(['tahap' => 'E']);

        $this->aturan()->pindahTahap($diEdit, 'F', $editor);
        $this->assertSame('F', $diEdit->fresh()->tahap);

        $lain = $this->order(['tahap' => 'E']);
        $this->expectException(PapanDitolak::class);
        $this->aturan()->pindahTahap($lain, 'G', $editor);
    }

    public function test_editor_tidak_bisa_menyentuh_kartu_tahap_lain(): void
    {
        $editor = $this->staf('editor', 'Abeng', null);
        $order = $this->order(['tahap' => 'C']);

        $this->assertFalse($this->aturan()->bolehKelola($editor, $order));
        $this->expectException(PapanDitolak::class);
        $this->aturan()->pindahTahap($order, 'E', $editor);
    }

    public function test_editor_mengambil_kartu_untuk_diri_sendiri_saja(): void
    {
        $editor = $this->staf('editor', 'Abeng', null);
        $lain = $this->staf('editor', 'Akew', null);
        $order = $this->order(['tahap' => 'E']);

        $this->aturan()->tugaskan($order, $editor->id, $editor);
        $this->assertSame($editor->id, $order->fresh()->tahap_pj_id);

        $this->expectException(PapanDitolak::class);
        $this->aturan()->tugaskan($order->fresh(), $lain->id, $editor);
    }

    public function test_marketing_hanya_mengelola_kartu_qc_miliknya(): void
    {
        $milik = $this->order(['tahap' => 'F']);
        $this->aturan()->pindahTahap($milik, 'G', $this->marketing);
        $this->assertSame('G', $milik->fresh()->tahap);

        $orangLain = $this->staf('marketing', 'Rudi');
        $bukanMilik = $this->order(['tahap' => 'F', 'marketing_id' => $orangLain->id]);
        $this->assertFalse($this->aturan()->bolehKelola($this->marketing, $bukanMilik));
    }

    public function test_order_batal_tidak_bisa_dikelola(): void
    {
        $order = $this->order(['status' => OrderStatus::BATAL]);

        $this->assertFalse($this->aturan()->bolehKelola($this->admin(), $order));
    }

    public function test_tertahan_wajib_alasan_dan_bisa_dilanjutkan(): void
    {
        $order = $this->order();
        $admin = $this->admin();

        try {
            $this->aturan()->tahan($order, '  ', $admin);
            $this->fail('Seharusnya ditolak.');
        } catch (PapanDitolak) {
        }

        $this->aturan()->tahan($order, 'Logo sekolah belum dikirim', $admin);
        $this->assertSame('Logo sekolah belum dikirim', $order->fresh()->tertahan_alasan);

        $this->aturan()->lanjutkan($order->fresh(), $admin);
        $this->assertNull($order->fresh()->tertahan_alasan);
        $this->assertSame(1, $order->activities()->where('action', 'papan_tertahan')->count());
        $this->assertSame(1, $order->activities()->where('action', 'papan_lanjut')->count());
    }

    // ---------------- Tenggat ----------------

    public function test_tenggat_dihitung_dari_tanggal_event(): void
    {
        $order = $this->order(['tanggal_event' => '2026-09-10', 'tahap' => 'C']);
        $this->assertSame('2026-09-11', $order->tenggatPapan()->toDateString());   // H+1

        $order->tahap = 'G';
        $this->assertSame('2026-09-14', $order->tenggatPapan()->toDateString());   // H+4

        $order->tahap = 'E';
        $this->assertNull($order->tenggatPapan());                                // tanpa tenggat otomatis
    }

    public function test_tenggat_khusus_hanya_bisa_diatur_admin(): void
    {
        $order = $this->order(['tahap' => 'E']);
        $this->aturan()->aturTenggat($order, '2026-09-20', $this->admin());
        $this->assertSame('2026-09-20', $order->fresh()->tenggatPapan()->toDateString());

        $this->expectException(PapanDitolak::class);
        $this->aturan()->aturTenggat($order->fresh(), null, $this->staf('editor', 'Abeng', null));
    }

    public function test_tenggat_khusus_hilang_saat_pindah_tahap(): void
    {
        $order = $this->order(['tahap' => 'E', 'tenggat_manual' => '2026-09-20']);

        $this->aturan()->pindahTahap($order, 'F', $this->admin());

        $this->assertNull($order->fresh()->tenggat_manual);
    }

    // ---------------- Halaman papan ----------------

    public function test_papan_per_tahap_menampilkan_kartu_di_kolomnya(): void
    {
        $this->order(['tahap' => 'E', 'booking_code' => 'KODE-EDIT']);
        $this->order(['tahap' => 'G', 'booking_code' => 'KODE-PRODUKSI']);

        $kolom = collect($this->papan($this->admin())->get('kolom'))->keyBy('kunci');

        $this->assertSame(['KODE-EDIT'], collect($kolom['tahap:E']['kartu'])->pluck('kode')->all());
        $this->assertSame(['KODE-PRODUKSI'], collect($kolom['tahap:G']['kartu'])->pluck('kode')->all());
    }

    public function test_kolom_menuju_event_berisi_order_belum_hari_h_yang_dekat(): void
    {
        $this->order(['booking_code' => 'KODE-BESOK', 'tahap' => null, 'konfirmasi_hh_at' => null,
            'event_status' => OrderStatus::EVENT_DIJADWALKAN, 'tanggal_event' => now()->addDay()->toDateString()]);
        $this->order(['booking_code' => 'KODE-JAUH', 'tahap' => null, 'konfirmasi_hh_at' => null,
            'event_status' => OrderStatus::EVENT_DIJADWALKAN, 'tanggal_event' => now()->addMonth()->toDateString()]);

        $kolom = collect($this->papan($this->admin())->get('kolom'))->keyBy('kunci');

        $this->assertSame(['KODE-BESOK'], collect($kolom['ab']['kartu'])->pluck('kode')->all());
        $this->assertFalse($kolom['ab']['bisaDrop']);
    }

    public function test_selesai_lama_tidak_tampil(): void
    {
        $this->order(['tahap' => 'P', 'status' => 'lunas', 'booking_code' => 'KODE-BARU', 'tahap_masuk_at' => now()->subDays(3)]);
        $this->order(['tahap' => 'P', 'status' => 'lunas', 'booking_code' => 'KODE-LAMA', 'tahap_masuk_at' => now()->subDays(40)]);

        $kolom = collect($this->papan($this->admin())->get('kolom'))->keyBy('kunci');

        $this->assertSame(['KODE-BARU'], collect($kolom['tahap:P']['kartu'])->pluck('kode')->all());
    }

    public function test_seret_kartu_ke_kolom_tahap(): void
    {
        $order = $this->order(['tahap' => 'C']);

        $this->papan($this->admin())
            ->call('pindah', $order->id, 'tahap:E')
            ->assertSet('galat', null)
            ->assertSee('Kartu dipindah ke E · Editing.');

        $this->assertSame('E', $order->fresh()->tahap);
    }

    public function test_seret_yang_ditolak_menampilkan_alasan(): void
    {
        $order = $this->order(['tahap' => 'N', 'status' => OrderStatus::DP]);

        $this->papan($this->admin())
            ->call('pindah', $order->id, 'tahap:P')
            ->assertSee('Order belum lunas');

        $this->assertSame('N', $order->fresh()->tahap);
    }

    public function test_per_orang_menampilkan_editor_dan_menugaskan_lewat_seret(): void
    {
        $abeng = $this->staf('editor', 'Abeng', null);
        $order = $this->order(['tahap' => 'E']);

        $komponen = $this->papan($this->admin(), ['mode' => 'orang', 'di' => 'E']);
        $kunci = collect($komponen->get('kolom'))->pluck('kunci')->all();
        $this->assertSame(['pj:0', 'pj:'.$abeng->id, 'tertahan'], $kunci);

        $komponen->call('pindah', $order->id, 'pj:'.$abeng->id);

        $this->assertSame($abeng->id, $order->fresh()->tahap_pj_id);
    }

    public function test_seret_ke_tertahan_membuka_isian_alasan(): void
    {
        $order = $this->order(['tahap' => 'E']);

        $this->papan($this->admin(), ['mode' => 'orang', 'di' => 'E'])
            ->call('pindah', $order->id, 'tertahan')
            ->assertSet('kartuId', $order->id)
            ->set('alasanTahan', 'Menunggu kode desain')
            ->call('simpanTahan')
            ->assertSet('kartuId', null);

        $this->assertSame('Menunggu kode desain', $order->fresh()->tertahan_alasan);
    }

    public function test_keluar_dari_tertahan_lewat_seret_ke_orang(): void
    {
        $abeng = $this->staf('editor', 'Abeng', null);
        $order = $this->order(['tahap' => 'E', 'tertahan_alasan' => 'kode desain', 'tertahan_at' => now()]);

        $this->papan($abeng)->call('pindah', $order->id, 'pj:'.$abeng->id);

        $order->refresh();
        $this->assertNull($order->tertahan_alasan);
        $this->assertSame($abeng->id, $order->tahap_pj_id);
    }

    public function test_lembar_kartu_memindah_tahap(): void
    {
        $order = $this->order(['tahap' => 'C']);

        $this->papan($this->admin())
            ->call('bukaKartu', $order->id)
            ->set('tujuanTahap', 'G')
            ->call('simpanTahap')
            ->assertSet('kartuId', null);

        $this->assertSame('G', $order->fresh()->tahap);
    }

    public function test_editor_langsung_melihat_tahap_editing_per_orang(): void
    {
        $this->papan($this->staf('editor', 'Abeng', null))
            ->assertSet('mode', 'orang')
            ->assertSet('tahapDipilih', 'E');
    }

    public function test_filter_tugas_saya(): void
    {
        $abeng = $this->staf('editor', 'Abeng', null);
        $this->order(['tahap' => 'E', 'tahap_pj_id' => $abeng->id, 'booking_code' => 'KODE-SAYA']);
        $this->order(['tahap' => 'E', 'booking_code' => 'KODE-LAIN']);

        $kolom = $this->papan($abeng)->set('tugasSaya', true)->get('kolom');
        $kode = collect($kolom)->flatMap(fn ($k) => collect($k['kartu'])->pluck('kode'))->all();

        $this->assertSame(['KODE-SAYA'], $kode);
    }

    public function test_marketing_hanya_melihat_order_miliknya(): void
    {
        $rudi = $this->staf('marketing', 'Rudi');
        $this->order(['tahap' => 'F', 'booking_code' => 'KODE-SHANTY']);
        $this->order(['tahap' => 'F', 'booking_code' => 'KODE-RUDI', 'marketing_id' => $rudi->id]);

        $kolom = $this->papan($rudi, ['mode' => 'tahap'])->get('kolom');
        $kode = collect($kolom)->flatMap(fn ($k) => collect($k['kartu'])->pluck('kode'))->all();

        $this->assertSame(['KODE-RUDI'], $kode);
    }

    public function test_tim_event_tidak_bisa_membuka_papan(): void
    {
        $this->actingAs($this->staf('tim_event'))->get(route('app.papan'))->assertForbidden();
    }

    public function test_halaman_papan_bisa_dibuka(): void
    {
        $this->order(['tahap' => 'E']);

        $this->actingAs($this->admin())->get(route('app.papan'))
            ->assertOk()
            ->assertSee('Papan order')
            ->assertSee('TK Miftahul Khoir');
    }

    public function test_menu_papan_muncul_untuk_editor(): void
    {
        $label = collect(RoleMenu::for($this->staf('editor', 'Abeng', null)))->pluck('label')->all();

        $this->assertContains('Papan order', $label);
    }

    public function test_halaman_order_menampilkan_tahap_papan(): void
    {
        $abeng = $this->staf('editor', 'Abeng', null);
        $order = $this->order(['tahap' => 'E', 'tahap_pj_id' => $abeng->id]);

        Livewire::actingAs($this->admin())
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->assertSee('E · Editing')
            ->assertSee('Abeng');
    }
}
