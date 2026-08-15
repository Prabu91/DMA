<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase C: kategori pelanggan sekolah dari jumlah order yang event-nya selesai.
 * NOS (0) · NRS (1–2) · SR (≥3).
 */
class SekolahKategoriTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cabang = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
    }

    private function sekolah(): Sekolah
    {
        return Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SDN Uji '.uniqid(),
            'cabang_id' => $this->cabang->id,
        ]);
    }

    private function order(Sekolah $sekolah, string $eventStatus): void
    {
        Order::create([
            'booking_code' => 'BK-'.uniqid(),
            'sekolah_id' => $sekolah->id,
            'cabang_id' => $this->cabang->id,
            'sumber' => 'sekolah',
            'status' => 'lunas',
            'event_status' => $eventStatus,
            'total' => 1000,
            'tanggal_booking' => now(),
        ]);
    }

    public function test_belum_pernah_selesai_nos(): void
    {
        $sekolah = $this->sekolah();
        $this->order($sekolah, OrderStatus::EVENT_DIJADWALKAN); // belum selesai

        $this->assertSame(Sekolah::KATEGORI_NOS, $sekolah->kategoriPelanggan());
        $this->assertSame(0, $sekolah->dealCount());
    }

    public function test_satu_atau_dua_selesai_nrs(): void
    {
        $sekolah = $this->sekolah();
        $this->order($sekolah, OrderStatus::EVENT_SELESAI);
        $this->assertSame(Sekolah::KATEGORI_NRS, $sekolah->kategoriPelanggan());

        $this->order($sekolah, OrderStatus::EVENT_SELESAI);
        $this->assertSame(Sekolah::KATEGORI_NRS, $sekolah->kategoriPelanggan());
    }

    public function test_tiga_atau_lebih_selesai_sr(): void
    {
        $sekolah = $this->sekolah();
        foreach (range(1, 3) as $i) {
            $this->order($sekolah, OrderStatus::EVENT_SELESAI);
        }

        $this->assertSame(Sekolah::KATEGORI_SR, $sekolah->kategoriPelanggan());
        $this->assertSame(3, $sekolah->dealCount());
    }

    public function test_withcount_deal_count_dipakai(): void
    {
        $sekolah = $this->sekolah();
        $this->order($sekolah, OrderStatus::EVENT_SELESAI);
        $this->order($sekolah, OrderStatus::EVENT_DIJADWALKAN);

        $loaded = Sekolah::withCount(['orders as deal_count' => fn ($q) => $q->where('event_status', OrderStatus::EVENT_SELESAI)])
            ->find($sekolah->id);

        $this->assertSame(1, $loaded->dealCount());
        $this->assertSame(Sekolah::KATEGORI_NRS, $loaded->kategoriPelanggan());
    }
}
