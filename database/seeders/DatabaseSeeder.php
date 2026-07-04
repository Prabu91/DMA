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
            UserSeeder::class,
            KategoriSeeder::class, // kategori sebelum produk (FK kategori_id)
            ProdukSeeder::class,
        ]);
    }
}
