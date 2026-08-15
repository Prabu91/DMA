<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kecamatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->foreignId('kota_id')->constrained('kota')->cascadeOnDelete();
            $table->timestamps();

            $table->index('kota_id');
            $table->unique(['kota_id', 'nama']); // nama kecamatan unik dalam satu kota
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kecamatan');
    }
};
