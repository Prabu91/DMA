<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode komposisi ukuran — dipakai produk Pas Foto.
 *
 * Pada mode ini ukuran bukan "pilih salah satu", melainkan pembagian jatah pcs:
 * satu harga (mis. Rp20.000 per siswa) boleh dibagi 2x3 4 pcs + 3x4 2 pcs, atau
 * kombinasi lain, sebatas maks_pcs. Harga tidak berubah oleh komposisinya.
 *
 * Saklar per produk, karena di kategori Pas Foto ada produk yang komposisinya
 * bebas dan ada yang ukurannya sudah ditentukan (tetap memakai varian biasa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->boolean('komposisi_ukuran')->default(false)->after('frame');
            $table->unsignedSmallInteger('maks_pcs')->nullable()->after('komposisi_ukuran');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn(['komposisi_ukuran', 'maks_pcs']);
        });
    }
};
