<?php

namespace Database\Seeders;

use App\Models\Frame;
use Illuminate\Database\Seeder;

class FrameSeeder extends Seeder
{
    /** Master frame (dulu "gaya" produk). */
    private const FRAMES = [
        'MINIMALIS', 'BLOK', '3D', 'GLITER', 'LEMBARAN',
        'Formal', 'Studio', 'Hardcover', 'Kayu',
    ];

    public function run(): void
    {
        foreach (self::FRAMES as $nama) {
            Frame::firstOrCreate(['nama' => $nama], ['status' => 'aktif']);
        }
    }
}
