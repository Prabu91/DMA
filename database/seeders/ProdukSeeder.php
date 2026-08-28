<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Database\Seeder;

class ProdukSeeder extends Seeder
{
    public function run(): void
    {
        // [nama kategori => daftar produk]
        $katalog = [
            'Foto Kelas' => [
                ['nama' => 'Foto Kelas Standar', 'frame' => 'Formal', 'harga' => 35000],
                ['nama' => 'Foto Kelas Premium', 'frame' => 'Formal', 'harga' => 55000],
            ],
            'Foto Individu' => [
                ['nama' => 'Pas Foto 3x4', 'frame' => 'Studio', 'harga' => 15000],
                ['nama' => 'Foto Individu Ukuran R', 'frame' => 'Studio', 'harga' => 25000],
            ],
            'Album' => [
                ['nama' => 'Album Kenangan 20 Halaman', 'frame' => 'Hardcover', 'harga' => 150000],
                ['nama' => 'Album Eksklusif 40 Halaman', 'frame' => 'Hardcover', 'harga' => 275000],
            ],
            'Cetak & Frame' => [
                ['nama' => 'Cetak 10R + Frame', 'frame' => 'Kayu', 'harga' => 60000],
                ['nama' => 'Cetak 16R + Frame', 'frame' => 'Kayu', 'harga' => 95000],
            ],
        ];

        foreach ($katalog as $namaKategori => $produkList) {
            $kategori = Kategori::where('nama', $namaKategori)->first();

            if ($kategori === null) {
                continue;
            }

            foreach ($produkList as $produk) {
                Produk::firstOrCreate(
                    ['nama' => $produk['nama'], 'kategori_id' => $kategori->id],
                    $produk + [
                        'kategori_id' => $kategori->id,
                        'deskripsi' => $produk['nama'].' - '.$namaKategori,
                        'status' => 'aktif',
                    ]
                );
            }
        }
    }
}
