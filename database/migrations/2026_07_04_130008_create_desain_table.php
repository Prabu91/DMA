<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori');
            $table->string('kode');
            $table->string('seri')->nullable();
            $table->string('orientasi')->nullable();
            $table->string('foto_preview')->nullable();
            $table->string('tahun_ajaran')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index('kode');
            $table->index('tahun_ajaran');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desain');
    }
};
