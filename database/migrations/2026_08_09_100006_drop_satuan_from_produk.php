<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hapus kolom 'satuan': tiap produk kini SELALU dipesan per jumlah produk
     * (qty). Jumlah siswa jadi input tingkat order (di keranjang/checkout),
     * bukan atribut per-produk. Menghilangkan kebingungan qty vs siswa.
     */
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('satuan');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->string('satuan')->default('qty');
        });
    }
};
