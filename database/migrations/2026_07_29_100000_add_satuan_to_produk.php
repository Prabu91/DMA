<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            // Satuan hitung produk: 'qty' (per item/pcs) atau 'siswa' (per jumlah siswa).
            // Hanya mengubah label input jumlah — harga tetap unit × jumlah yang diisi.
            $table->string('satuan')->default('qty')->after('harga');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('satuan');
        });
    }
};
