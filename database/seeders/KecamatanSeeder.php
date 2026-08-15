<?php

namespace Database\Seeders;

use App\Models\Kecamatan;
use App\Models\Kota;
use Illuminate\Database\Seeder;

class KecamatanSeeder extends Seeder
{
    /**
     * Contoh kecamatan per kota (data awal untuk dev/uji auto-assign).
     *
     * Untuk DATA RIIL, jalankan impor dari public API wilayah (emsifa):
     *   php artisan kecamatan:import
     * Peta kota→regency ada di config/wilayah.php. Seeder ini hanya fallback
     * offline/testing agar auto-assign punya sampel tanpa akses jaringan.
     */
    private const PETA = [
        'Bandung' => ['Coblong', 'Cibeunying Kaler', 'Sukajadi', 'Andir'],
        'Cianjur' => ['Cianjur Kota', 'Cilaku', 'Karangtengah'],
        'Bogor' => ['Bogor Tengah', 'Bogor Utara', 'Tanah Sareal'],
        'Jakarta' => ['Kebayoran Baru', 'Cilandak', 'Pancoran', 'Setiabudi'],
        'Tangerang' => ['Ciledug', 'Karawaci', 'Cipondoh'],
        'Depok' => ['Beji', 'Pancoran Mas', 'Cimanggis'],
        'Cikarang' => ['Cikarang Utara', 'Cikarang Selatan', 'Cikarang Barat'],
    ];

    public function run(): void
    {
        foreach (self::PETA as $namaKota => $daftarKecamatan) {
            $kota = Kota::where('nama', $namaKota)->first();
            if (! $kota) {
                continue;
            }

            foreach ($daftarKecamatan as $namaKecamatan) {
                Kecamatan::firstOrCreate([
                    'kota_id' => $kota->id,
                    'nama' => $namaKecamatan,
                ]);
            }
        }
    }
}
