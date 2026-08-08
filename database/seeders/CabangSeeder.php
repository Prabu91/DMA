<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    public function run(): void
    {
        // Cabang final DMA (nama bersih, tanpa prefix; Jakarta → Jaksel).
        $cabang = [
            ['nama' => 'Jaksel', 'kode_area' => 'JKS'],
            ['nama' => 'Bandung', 'kode_area' => 'BDG'],
            ['nama' => 'Bogor', 'kode_area' => 'BGR'],
            ['nama' => 'Cianjur', 'kode_area' => 'CJR'],
            ['nama' => 'Bekasi', 'kode_area' => 'BKS'],
            ['nama' => 'Surabaya', 'kode_area' => 'SBY'],
        ];

        foreach ($cabang as $data) {
            Cabang::firstOrCreate(['nama' => $data['nama']], $data);
        }
    }
}
