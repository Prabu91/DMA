<?php

namespace Tests\Feature;

use App\Livewire\Booking\OrderDetail;
use App\Livewire\PengaturanIndex;
use App\Models\Cabang;
use App\Models\Kategori;
use App\Models\Order;
use App\Models\Produk;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\FolderKerja;
use App\Support\OrderStatus;
use App\Support\Pengaturan;
use App\Support\Redaksi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Redaksi (nama lengkap + alamat sekolah yang ikut dicetak) dan path folder
 * kerja editor — pengganti REDAKSI.txt dan path yang di Trello diketik manual.
 */
class RedaksiFolderKerjaTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private Sekolah $sekolah;

    private User $marketing;

    private Kategori $reguler;

    private Kategori $yearbook;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->sekolah = Sekolah::create([
            'id_sekolah' => '28981678',
            'nama' => 'TK MIFTAHUL KHOIR',
            'alamat' => 'KP. BOJONG SALAK RT. 01 RW. 22 DS. CILAMPENI KEC. KATAPANG KAB. BANDUNG',
            'cabang_id' => $this->bdg->id,
        ]);
        $this->marketing = $this->staf('marketing', 'Shanty Dewiansyah');
        $this->reguler = Kategori::create(['nama' => 'Profesi', 'grup' => 'reguler']);
        $this->yearbook = Kategori::create(['nama' => 'Yearbook', 'grup' => 'yb']);
    }

    private function staf(string $role, string $nama = 'Staf', ?int $cabangId = -1): User
    {
        $u = User::factory()->create(['nama' => $nama, 'cabang_id' => $cabangId === -1 ? $this->bdg->id : $cabangId]);
        $u->assignRole($role);

        return $u;
    }

    private function order(array $atribut = []): Order
    {
        $order = Order::create(array_merge([
            'booking_code' => '100926BDGMKT007',
            'sekolah_id' => $this->sekolah->id,
            'marketing_id' => $this->marketing->id,
            'cabang_id' => $this->bdg->id,
            'sumber' => 'marketing',
            'status' => 'baru',
            'tanggal_event' => '2026-09-10',
            'total' => 0,
            'tanggal_booking' => now(),
        ], $atribut));

        $order->items()->create([
            'tipe_item' => 'produk',
            'produk_id' => Produk::create(['kategori_id' => $this->reguler->id, 'nama' => 'Foto 10RP OB Profesi', 'harga' => 10000])->id,
            'opsi_ukuran' => 'GRADASI', 'qty' => 49, 'harga' => 10000, 'is_free' => false,
        ]);
        $order->items()->create([
            'tipe_item' => 'produk',
            'produk_id' => Produk::create(['kategori_id' => $this->reguler->id, 'nama' => 'Souvenir Free Poster', 'harga' => 0])->id,
            'qty' => 63, 'harga' => 0, 'is_free' => true,
        ]);

        return $order->fresh();
    }

    private function detail(Order $order, User $user)
    {
        return Livewire::actingAs($user)->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id]);
    }

    // ---------------- Redaksi ----------------

    public function test_redaksi_dibuat_dari_nama_dan_alamat_sekolah(): void
    {
        $this->assertSame(
            "TK MIFTAHUL KHOIR\nKP. BOJONG SALAK RT. 01 RW. 22 DS. CILAMPENI KEC. KATAPANG KAB. BANDUNG",
            Redaksi::untuk($this->order()),
        );
    }

    public function test_perbaikan_data_sekolah_ikut_terbawa_ke_redaksi(): void
    {
        $order = $this->order();
        $this->sekolah->update(['nama' => 'TK MIFTAHUL KHOIR KATAPANG']);

        $this->assertStringStartsWith('TK MIFTAHUL KHOIR KATAPANG', Redaksi::untuk($order->fresh()));
    }

    public function test_koreksi_redaksi_berlaku_untuk_order_saja(): void
    {
        $order = $this->order();

        $this->detail($order, $this->marketing)
            ->call('mulaiEditRedaksi')
            ->set('redaksiTeks', "TK ISLAM MIFTAHUL KHOIR\nKATAPANG - BANDUNG")
            ->call('simpanRedaksi')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame("TK ISLAM MIFTAHUL KHOIR\nKATAPANG - BANDUNG", Redaksi::untuk($order));
        $this->assertTrue(Redaksi::dikoreksi($order));
        $this->assertSame('TK MIFTAHUL KHOIR', $this->sekolah->fresh()->nama); // data sekolah utuh
        $this->assertSame(1, $order->activities()->where('action', 'redaksi_diubah')->count());
    }

    public function test_koreksi_yang_sama_dengan_data_sekolah_tidak_disimpan_sebagai_koreksi(): void
    {
        $order = $this->order();

        $this->detail($order, $this->marketing)
            ->call('mulaiEditRedaksi')
            ->call('simpanRedaksi');

        $this->assertNull($order->fresh()->redaksi);
    }

    public function test_redaksi_bisa_dikembalikan_ke_data_sekolah(): void
    {
        $order = $this->order(['redaksi' => 'NAMA LAIN']);

        $this->detail($order, $this->marketing)->call('redaksiIkutSekolah');

        $this->assertNull($order->fresh()->redaksi);
    }

    public function test_redaksi_kosong_ditolak(): void
    {
        $order = $this->order();

        $this->detail($order, $this->marketing)
            ->call('mulaiEditRedaksi')
            ->set('redaksiTeks', '   ')
            ->call('simpanRedaksi')
            ->assertHasErrors('redaksiTeks');
    }

    public function test_redaksi_tetap_bisa_dikoreksi_walau_order_terkunci(): void
    {
        // Redaksi dipakai di tahap editing, sesudah Hari-H.
        $order = $this->order(['konfirmasi_hh_at' => now(), 'event_status' => OrderStatus::EVENT_SELESAI]);
        $this->assertTrue($order->isLocked());

        $this->detail($order, $this->marketing)
            ->call('mulaiEditRedaksi')
            ->set('redaksiTeks', 'TK MIFTAHUL KHOIR')
            ->call('simpanRedaksi')
            ->assertHasNoErrors();

        $this->assertSame('TK MIFTAHUL KHOIR', $order->fresh()->redaksi);
    }

    public function test_marketing_cabang_lain_tidak_bisa_membuka_order_untuk_dikoreksi(): void
    {
        $order = $this->order();
        $lain = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);

        // CabangScope: order cabang lain tidak terlihat sama sekali.
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->staf('marketing', 'Orang Lain', $lain->id))
            ->test(OrderDetail::class, ['konteks' => 'staf', 'orderId' => $order->id])
            ->call('mulaiEditRedaksi');
    }

    public function test_order_batal_tidak_bisa_dikoreksi_redaksinya(): void
    {
        $order = $this->order(['status' => OrderStatus::BATAL]);

        $this->detail($order, $this->marketing)
            ->call('mulaiEditRedaksi')
            ->assertForbidden();
    }

    public function test_nama_bergaris_bawah_diberi_peringatan(): void
    {
        $this->sekolah->update(['nama' => 'AISYIYAH 76_1124']);
        $order = $this->order();

        $this->assertTrue(Redaksi::perluDicek($order));
        $this->detail($order, $this->marketing)->assertSee('Redaksi mengandung garis bawah');
    }

    public function test_item_bisa_ditandai_tanpa_redaksi(): void
    {
        $order = $this->order();
        $item = $order->items->first();
        $item->setQc('event', true, null);

        $this->detail($order, $this->marketing)->call('toggleTanpaRedaksi', $item->id);

        $item->refresh();
        $this->assertTrue($item->tanpa_redaksi);
        $this->assertNotNull($item->qc_event_at); // bukan perubahan item, centang QC tetap
        $this->assertSame(1, $order->activities()->where('action', 'redaksi_item')->count());
    }

    public function test_unduh_redaksi_txt(): void
    {
        $order = $this->order();

        $res = $this->actingAs($this->marketing)->get(route('app.order.redaksi', $order->id));

        $res->assertOk();
        $this->assertStringContainsString('attachment; filename="REDAKSI_100926BDGMKT007.txt"', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString("TK MIFTAHUL KHOIR\r\nKP. BOJONG SALAK", $res->getContent());
    }

    public function test_unduh_redaksi_order_cabang_lain_ditolak(): void
    {
        $order = $this->order();
        $lain = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);

        $this->actingAs($this->staf('marketing', 'Orang Lain', $lain->id))
            ->get(route('app.order.redaksi', $order->id))
            ->assertNotFound();
    }

    public function test_sekolah_tidak_melihat_kartu_redaksi(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->sekolah, 'sekolah')
            ->test(OrderDetail::class, ['konteks' => 'sekolah', 'orderId' => $order->id])
            ->assertDontSee('Redaksi & folder kerja');
    }

    // ---------------- Folder kerja ----------------

    public function test_path_folder_mengikuti_pola_bawaan(): void
    {
        $paths = array_values(FolderKerja::perItem($this->order()));

        $this->assertSame(
            '\\\\delapanmataair\\Editor 5\\2. REGULER#PROJECT SEKOLAH\\2026-2027\\10 SEPTEMBER 2026\\BANDUNG (SHANTY)\\TK MIFTAHUL KHOIR\\PILIHAN\\1. FOTO 10RP OB PROFESI GRADASI',
            $paths[0],
        );
        // Item free ikut bernomor, seperti checklist di Trello.
        $this->assertStringEndsWith('\\PILIHAN\\2. SOUVENIR FREE POSTER', $paths[1]);
    }

    public function test_tahun_ajaran_berganti_setiap_juli(): void
    {
        $this->assertSame('2025-2026', FolderKerja::tahunAjaran(2026, 6));
        $this->assertSame('2026-2027', FolderKerja::tahunAjaran(2026, 7));
    }

    public function test_item_yearbook_masuk_folder_jalur_yearbook(): void
    {
        $order = $this->order();
        $yb = $order->items()->create([
            'tipe_item' => 'produk',
            'produk_id' => Produk::create(['kategori_id' => $this->yearbook->id, 'nama' => 'Yearbook 30 Hal', 'harga' => 100000])->id,
            'qty' => 1, 'harga' => 100000, 'is_free' => false,
        ]);

        $order = $order->fresh();
        $this->assertStringContainsString('\\YEARBOOK\\', FolderKerja::perItem($order)[$yb->id]);
        $this->assertSame(['reguler', 'yb'], array_keys(FolderKerja::perJalur($order)));
    }

    public function test_karakter_terlarang_di_nama_folder_dibersihkan(): void
    {
        $this->sekolah->update(['nama' => 'SD "BINTANG": KELAS A/B?']);

        $path = array_values(FolderKerja::perItem($this->order()))[0];

        $this->assertStringContainsString('\\SD -BINTANG- KELAS A-B\\PILIHAN\\', $path);
    }

    public function test_pola_folder_bisa_diubah_admin(): void
    {
        $admin = $this->staf('admin_sales', 'Admin', null);

        Livewire::actingAs($admin)
            ->test(PengaturanIndex::class)
            ->set('folderRoot', '\\\\NAS\\Editor 1')
            ->set('folderTemplatSekolah', '{root}\\{jalur}\\{bulan_event}\\{kode_booking}_{sekolah}')
            ->set('folderJalur.reguler', 'REGULER')
            ->call('simpanFolder')
            ->assertHasNoErrors();

        $path = array_values(FolderKerja::perItem($this->order()))[0];
        $this->assertSame('\\\\NAS\\Editor 1\\REGULER\\SEPTEMBER 2026\\100926BDGMKT007_TK MIFTAHUL KHOIR\\PILIHAN\\1. FOTO 10RP OB PROFESI GRADASI', $path);
    }

    public function test_nilai_sama_dengan_bawaan_tidak_disimpan(): void
    {
        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(PengaturanIndex::class)
            ->call('simpanFolder')
            ->assertHasNoErrors();

        $this->assertNull(Pengaturan::teks(FolderKerja::KUNCI_TEMPLAT_SEKOLAH));
        $this->assertNull(Pengaturan::teks(FolderKerja::PREFIKS_JALUR.'reguler'));
    }

    public function test_penanda_tak_dikenal_ditolak(): void
    {
        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(PengaturanIndex::class)
            ->set('folderTemplatSekolah', '{root}\\{kelas}')
            ->call('simpanFolder')
            ->assertHasErrors('folderTemplatSekolah');

        $this->assertNull(Pengaturan::teks(FolderKerja::KUNCI_TEMPLAT_SEKOLAH));
    }

    public function test_templat_item_wajib_memuat_folder_sekolah(): void
    {
        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(PengaturanIndex::class)
            ->set('folderTemplatItem', 'PILIHAN\\{item}')
            ->call('simpanFolder')
            ->assertHasErrors('folderTemplatItem');
    }

    public function test_pola_bisa_dikembalikan_ke_bawaan(): void
    {
        Pengaturan::set(FolderKerja::KUNCI_ROOT, '\\\\NAS');

        Livewire::actingAs($this->staf('admin_sales', 'Admin', null))
            ->test(PengaturanIndex::class)
            ->call('folderKeBawaan');

        $this->assertSame(FolderKerja::BAWAAN_ROOT, FolderKerja::root());
    }

    public function test_marketing_tidak_bisa_membuka_pengaturan(): void
    {
        Livewire::actingAs($this->marketing)
            ->test(PengaturanIndex::class)
            ->assertForbidden();
    }

    public function test_kartu_menampilkan_redaksi_dan_path(): void
    {
        $order = $this->order();

        $this->detail($order, $this->marketing)
            ->assertSee('Redaksi & folder kerja')
            ->assertSee('KP. BOJONG SALAK')
            ->assertSee('PILIHAN\\1. FOTO 10RP OB PROFESI GRADASI', false);
    }
}
