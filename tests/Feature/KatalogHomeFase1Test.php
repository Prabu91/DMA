<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KatalogHomeFase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_home_menampilkan_kategori_dan_paket_unggulan(): void
    {
        $kategori = Kategori::create(['nama' => 'Album Kelas', 'pakai_desain' => true]);
        Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Album Standar',
            'harga' => 10000,
            'status' => 'aktif',
        ]);

        Paket::create([
            'nama' => 'Paket Wisuda',
            'harga' => 50000,
            'status' => 'aktif',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pilih kategori paket')
            ->assertSee('Album Kelas')
            ->assertSee('Paket populer')
            ->assertSee('Paket Wisuda')
            ->assertSee('Cara pesan');
    }

    public function test_header_punya_menu_katalog_dan_ikon_keranjang(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('storefront.katalog.index'))
            ->assertSee('Katalog')
            ->assertSee('aria-label="Keranjang"', false);
    }

    public function test_kategori_tanpa_produk_aktif_tidak_tampil(): void
    {
        $kategori = Kategori::create(['nama' => 'Kategori Kosong', 'pakai_desain' => false]);
        Produk::create([
            'kategori_id' => $kategori->id,
            'nama' => 'Produk Nonaktif',
            'harga' => 1000,
            'status' => 'nonaktif',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Kategori Kosong');
    }

    public function test_staf_tetap_diarahkan_ke_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('app.dashboard'));
    }
}
