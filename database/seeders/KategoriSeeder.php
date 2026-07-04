<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = [
            ['nama' => 'Foto Kelas', 'pakai_desain' => true],
            ['nama' => 'Foto Individu', 'pakai_desain' => true],
            ['nama' => 'Album', 'pakai_desain' => true],
            ['nama' => 'Cetak & Frame', 'pakai_desain' => false],
        ];

        foreach ($kategori as $data) {
            Kategori::firstOrCreate(['nama' => $data['nama']], $data);
        }
    }
}
