<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,     // role harus ada sebelum user di-assign role
            CabangSeeder::class,   // cabang sebelum user (FK cabang_id)
            KotaSeeder::class,       // kota + cabang peta
            KecamatanSeeder::class,  // kecamatan per kota (sebelum UserSeeder assign ke marketing)
            UserSeeder::class,
            KategoriSeeder::class, // kategori sebelum produk (FK kategori_id)
            ProdukSeeder::class,
            PricelistSeeder::class, // katalog representatif dari PRICE 2026-2027 (+satuan)
            DesainSeeder::class,    // pool desain kategori bertema (butuh kategori dari PricelistSeeder)
        ]);
    }
}
