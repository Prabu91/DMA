<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('desain', function (Blueprint $table) {
            // Desain bisa spesifik produk tertentu; null = berlaku semua produk di kategori.
            $table->foreignId('produk_id')->nullable()->after('kategori_id')->constrained('produk')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desain', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produk_id');
        });
    }
};
