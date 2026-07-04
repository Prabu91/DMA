<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot murni: tanpa kolom id, memakai FK gabungan sebagai primary key.
        Schema::create('paket_produk', function (Blueprint $table) {
            $table->foreignId('paket_id')->constrained('paket');
            $table->foreignId('produk_id')->constrained('produk');

            $table->primary(['paket_id', 'produk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_produk');
    }
};
