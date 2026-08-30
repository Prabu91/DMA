<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4: halaman verifikasi publik booking dari QR — /cek/{booking_code}.
 * Read-only, tanpa login, tanpa data sensitif.
 */
class CekBookingTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attr = []): Order
    {
        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-000001', 'nama' => 'SDN Merdeka', 'cabang_id' => $cabang->id]);

        return Order::create(array_merge([
            'booking_code' => 'BK-CEK-1', 'sekolah_id' => $sekolah->id, 'cabang_id' => $cabang->id,
            'sumber' => 'sekolah', 'status' => 'dp', 'total' => 100000, 'tanggal_booking' => now(),
        ], $attr));
    }

    public function test_booking_valid_tampil_terverifikasi(): void
    {
        $this->order(['tanggal_event' => now()->addDays(5)->toDateString()]);

        $this->get(route('storefront.cek', 'BK-CEK-1'))
            ->assertOk()
            ->assertSee('Booking Terverifikasi')
            ->assertSee('SDN Merdeka')
            ->assertSee('Bandung')
            ->assertSee('DP dibayar'); // label status
    }

    public function test_booking_tak_dikenal_tampil_tidak_ditemukan(): void
    {
        $this->get(route('storefront.cek', 'BK-PALSU'))
            ->assertOk()
            ->assertSee('Tidak Ditemukan')
            ->assertSee('BK-PALSU');
    }

    public function test_halaman_verifikasi_terbuka_tanpa_login(): void
    {
        $this->order();

        // Tak ada redirect ke login — publik.
        $this->get(route('storefront.cek', 'BK-CEK-1'))->assertOk();
    }
}
