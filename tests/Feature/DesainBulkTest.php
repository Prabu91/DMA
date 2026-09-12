<?php

namespace Tests\Feature;

use App\Livewire\Katalog\DesainIndex;
use App\Livewire\Katalog\ProdukForm;
use App\Models\Desain;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Permintaan pemilik: unggah banyak JPEG desain sekaligus dengan kode diambil
 * dari nama berkas, plus hapus banyak desain sekaligus.
 */
class DesainBulkTest extends TestCase
{
    use RefreshDatabase;

    private Kategori $kategori;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        foreach (['super_admin', 'operasional', 'admin_sales', 'marketing', 'editor'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kategori = Kategori::create(['nama' => 'Wisuda', 'pakai_desain' => true]);
    }

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super_admin');

        return $u;
    }

    /** @return array<int, UploadedFile> */
    private function berkas(array $nama, int $lebar = 600, int $tinggi = 900): array
    {
        return array_map(fn ($n) => UploadedFile::fake()->image($n, $lebar, $tinggi), $nama);
    }

    // ---------------- Unggah massal ----------------

    public function test_kode_desain_diambil_dari_nama_berkas(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', $this->berkas(['WSD-001.jpg', 'WSD-002.jpg', 'WSD-003.jpg']))
            ->call('simpanBulk')
            ->assertHasNoErrors();

        $this->assertSame(
            ['WSD-001', 'WSD-002', 'WSD-003'],
            Desain::orderBy('kode')->pluck('kode')->all()
        );
    }

    public function test_setiap_desain_menyimpan_fotonya(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', $this->berkas(['WSD-001.jpg']))
            ->call('simpanBulk');

        $desain = Desain::first();
        $this->assertNotNull($desain->foto_preview);
        Storage::disk('public')->assertExists($desain->foto_preview);
    }

    public function test_orientasi_dibaca_dari_dimensi_gambar(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', array_merge(
                $this->berkas(['tegak.jpg'], 600, 900),
                $this->berkas(['lebar.jpg'], 900, 600),
            ))
            ->call('simpanBulk');

        $this->assertSame('portrait', Desain::where('kode', 'tegak')->value('orientasi'));
        $this->assertSame('landscape', Desain::where('kode', 'lebar')->value('orientasi'));
    }

    public function test_kode_yang_sudah_ada_dilewati_bukan_ditimpa(): void
    {
        $lama = Desain::create([
            'kategori_id' => $this->kategori->id, 'kode' => 'WSD-001',
            'tahun_ajaran' => '2025/2026', 'status' => 'aktif', 'foto_preview' => 'desain/lama.jpg',
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', $this->berkas(['WSD-001.jpg', 'WSD-002.jpg']))
            ->call('simpanBulk');

        $this->assertSame(2, Desain::count());                    // hanya WSD-002 yang baru
        $this->assertSame('desain/lama.jpg', $lama->refresh()->foto_preview); // yang lama utuh
        $this->assertSame('2025/2026', $lama->tahun_ajaran);
    }

    public function test_kode_dibandingkan_tanpa_membedakan_huruf_besar_kecil(): void
    {
        Desain::create([
            'kategori_id' => $this->kategori->id, 'kode' => 'wsd-001',
            'tahun_ajaran' => '2025/2026', 'status' => 'aktif',
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', $this->berkas(['WSD-001.jpg']))
            ->call('simpanBulk');

        $this->assertSame(1, Desain::count());
    }

    public function test_unggah_tanpa_berkas_ditolak(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->call('simpanBulk')
            ->assertHasErrors('bulkFiles');

        $this->assertSame(0, Desain::count());
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', [UploadedFile::fake()->create('catatan.pdf', 10, 'application/pdf')])
            ->call('simpanBulk')
            ->assertHasErrors('bulkFiles.0');

        $this->assertSame(0, Desain::count());
    }

    // ---------------- Hapus massal ----------------

    private function tigaDesain(): array
    {
        return collect(['A-1', 'A-2', 'A-3'])->map(fn ($k) => Desain::create([
            'kategori_id' => $this->kategori->id, 'kode' => $k,
            'tahun_ajaran' => '2026/2027', 'status' => 'aktif',
        ]))->all();
    }

    public function test_hapus_massal_menghapus_semua_yang_dipilih(): void
    {
        [$a, $b, $c] = $this->tigaDesain();

        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->set('terpilih', [$a->id, $b->id])
            ->call('mintaHapusMassal')
            ->call('hapusMassal');

        $this->assertSame(['A-3'], Desain::pluck('kode')->all());
        $this->assertSame($c->id, Desain::first()->id);
    }

    public function test_hapus_massal_melewati_desain_yang_masih_menempel_di_produk(): void
    {
        // Satu terpakai tidak boleh menggagalkan penghapusan yang lain.
        [$a, $b] = $this->tigaDesain();

        $produk = Produk::create([
            'kategori_id' => $this->kategori->id, 'nama' => 'Wisuda Gradasi', 'harga' => 50000, 'aktif' => true,
        ]);
        $produk->desains()->attach($a->id);

        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->set('terpilih', [$a->id, $b->id])
            ->call('mintaHapusMassal')
            ->call('hapusMassal');

        $this->assertNotNull($a->fresh());  // tertahan
        $this->assertNull($b->fresh());     // terhapus
    }

    public function test_hapus_massal_juga_membuang_berkas_fotonya(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('bukaBulk')
            ->set('bulkKategoriId', $this->kategori->id)
            ->set('bulkTahun', '2026/2027')
            ->set('bulkFiles', $this->berkas(['WSD-009.jpg']))
            ->call('simpanBulk');

        $desain = Desain::first();
        $path = $desain->foto_preview;
        Storage::disk('public')->assertExists($path);

        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->set('terpilih', [$desain->id])
            ->call('mintaHapusMassal')
            ->call('hapusMassal');

        Storage::disk('public')->assertMissing($path);
    }

    public function test_hapus_massal_tanpa_pilihan_memberi_pesan(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(DesainIndex::class)
            ->call('mintaHapusMassal')
            ->assertSet('konfirmasiHapusMassal', false)
            ->assertSee('Belum ada desain yang dipilih.');
    }

    // ---------------- Unggah massal dari halaman produk ----------------

    public function test_unggah_massal_di_halaman_produk_langsung_masuk_daftar(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('kategori_id', $this->kategori->id)
            ->set('desainBulk', $this->berkas(['PRD-001.jpg', 'PRD-002.jpg']))
            ->call('tambahDesainBulk')
            ->assertHasNoErrors()
            ->assertCount('desains', 2);

        $this->assertSame(['PRD-001', 'PRD-002'], Desain::orderBy('kode')->pluck('kode')->all());
        $this->assertSame($this->kategori->id, Desain::first()->kategori_id);
    }

    public function test_unggah_massal_di_halaman_produk_butuh_kategori_dulu(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('desainBulk', $this->berkas(['PRD-001.jpg']))
            ->call('tambahDesainBulk')
            ->assertHasErrors('desainBulk');

        $this->assertSame(0, Desain::count());
    }

    public function test_unggah_massal_di_halaman_produk_tersimpan_ke_pivot_saat_produk_disimpan(): void
    {
        $komponen = Livewire::actingAs($this->superAdmin())
            ->test(ProdukForm::class)
            ->set('kategori_id', $this->kategori->id)
            ->set('nama', 'Wisuda Gradasi')
            ->set('harga', 50000)
            ->set('desainBulk', $this->berkas(['PRD-010.jpg']))
            ->call('tambahDesainBulk');

        $komponen->call('save')->assertHasNoErrors();

        $produk = Produk::where('nama', 'Wisuda Gradasi')->firstOrFail();
        $this->assertSame(['PRD-010'], $produk->desains()->pluck('kode')->all());
    }
}
