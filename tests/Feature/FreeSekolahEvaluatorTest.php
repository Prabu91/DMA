<?php

namespace Tests\Feature;

use App\Models\AturanFreeSekolah;
use App\Models\Kategori;
use App\Models\Paket;
use App\Models\Produk;
use App\Models\ProdukBonus;
use App\Services\FreeSekolahEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeSekolahEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function produk(string $nama): Produk
    {
        $kategori = Kategori::firstOrCreate(['nama' => 'K'], ['pakai_desain' => false]);

        return Produk::create(['kategori_id' => $kategori->id, 'nama' => $nama, 'harga' => 1000, 'status' => 'aktif']);
    }

    // ---------- Mekanisme A: qty ----------

    public function test_qty_memenuhi_ambang_menghasilkan_free(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $hadiah = $this->produk('Bingkai');
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '>=', 'nilai' => 50,
            'hasil_produk_id' => $hadiah->id, 'hasil_ukuran' => '10RP',
        ]);

        $free = app(FreeSekolahEvaluator::class)->evaluate([
            'paket_id' => $paket->id,
            'jumlah_siswa' => 60,
            'total_omset' => 0,
        ]);

        $this->assertCount(1, $free);
        $this->assertSame($hadiah->id, $free[0]['produk_id']);
        $this->assertSame('10RP', $free[0]['ukuran']);
        $this->assertSame('aturan', $free[0]['source']);
    }

    public function test_qty_di_bawah_ambang_tidak_menghasilkan_free(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $hadiah = $this->produk('Bingkai');
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '>=', 'nilai' => 50,
            'hasil_produk_id' => $hadiah->id,
        ]);

        $free = app(FreeSekolahEvaluator::class)->evaluate([
            'paket_id' => $paket->id,
            'jumlah_siswa' => 30,
        ]);

        $this->assertCount(0, $free);
    }

    public function test_operator_kurang_dari(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $hadiah = $this->produk('Diskon');
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '<', 'nilai' => 20,
            'hasil_produk_id' => $hadiah->id,
        ]);

        $this->assertCount(1, app(FreeSekolahEvaluator::class)->evaluate(['paket_id' => $paket->id, 'jumlah_siswa' => 10]));
        $this->assertCount(0, app(FreeSekolahEvaluator::class)->evaluate(['paket_id' => $paket->id, 'jumlah_siswa' => 25]));
    }

    // ---------- Mekanisme A: omset ----------

    public function test_omset_memenuhi_ambang(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $hadiah = $this->produk('Album');
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'omset', 'operator' => '>=', 'nilai' => 5_000_000,
            'hasil_produk_id' => $hadiah->id,
        ]);

        $tepat = app(FreeSekolahEvaluator::class)->evaluate([
            'paket_id' => $paket->id, 'jumlah_siswa' => 5, 'total_omset' => 6_000_000,
        ]);
        $kurang = app(FreeSekolahEvaluator::class)->evaluate([
            'paket_id' => $paket->id, 'jumlah_siswa' => 999, 'total_omset' => 1_000_000,
        ]);

        $this->assertCount(1, $tepat);          // omset lolos meski siswa sedikit
        $this->assertSame($hadiah->id, $tepat[0]['produk_id']);
        $this->assertCount(0, $kurang);          // siswa banyak tak berpengaruh utk basis omset
    }

    // ---------- Mekanisme B: produk_bonus ----------

    public function test_produk_bonus_dihitung_per_unit(): void
    {
        $utama = $this->produk('Cetak 10R');
        $bonus = $this->produk('Gantungan');
        ProdukBonus::create(['produk_id' => $utama->id, 'bonus_produk_id' => $bonus->id, 'qty' => 2]);

        $free = app(FreeSekolahEvaluator::class)->evaluate([
            'produk' => [['produk_id' => $utama->id, 'qty' => 3]],
        ]);

        $this->assertCount(1, $free);
        $this->assertSame($bonus->id, $free[0]['produk_id']);
        $this->assertSame(6, $free[0]['qty']);   // 2 (bonus) × 3 (dipesan)
        $this->assertSame('bonus', $free[0]['source']);
    }

    public function test_gabungan_aturan_dan_bonus(): void
    {
        $paket = Paket::create(['nama' => 'Paket', 'harga' => 100000, 'status' => 'aktif']);
        $hadiahAturan = $this->produk('Bingkai');
        AturanFreeSekolah::create([
            'paket_id' => $paket->id, 'basis' => 'qty', 'operator' => '>=', 'nilai' => 50,
            'hasil_produk_id' => $hadiahAturan->id,
        ]);
        $utama = $this->produk('Cetak');
        $bonus = $this->produk('Gantungan');
        ProdukBonus::create(['produk_id' => $utama->id, 'bonus_produk_id' => $bonus->id, 'qty' => 1]);

        $free = app(FreeSekolahEvaluator::class)->evaluate([
            'paket_id' => $paket->id,
            'jumlah_siswa' => 100,
            'produk' => [['produk_id' => $utama->id, 'qty' => 4]],
        ]);

        $sources = collect($free)->pluck('source')->all();
        $this->assertContains('aturan', $sources);
        $this->assertContains('bonus', $sources);
        $this->assertCount(2, $free);
    }
}
