<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    public function run(): void
    {
        $cabang = [
            ['nama' => 'DMA Jakarta', 'kode_area' => 'JKT'],
            ['nama' => 'DMA Bandung', 'kode_area' => 'BDG'],
            ['nama' => 'DMA Surabaya', 'kode_area' => 'SBY'],
        ];

        foreach ($cabang as $data) {
            Cabang::firstOrCreate(['nama' => $data['nama']], $data);
        }
    }
}
