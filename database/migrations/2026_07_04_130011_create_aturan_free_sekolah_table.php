<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aturan_free_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_id')->constrained('paket');
            $table->string('basis');
            $table->string('operator');
            $table->integer('nilai');
            $table->foreignId('hasil_produk_id')->nullable()->constrained('produk');
            $table->string('hasil_ukuran')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aturan_free_sekolah');
    }
};
