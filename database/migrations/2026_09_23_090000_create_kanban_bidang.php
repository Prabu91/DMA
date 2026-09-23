<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bidang khusus (custom fields) per board — padanan Power-Up Custom Fields
 * di Trello: tiap board menentukan sendiri kolom tambahannya, lalu tiap
 * kartu mengisi nilainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_bidang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->string('nama', 80);
            $table->string('jenis', 20);              // teks|angka|tanggal|centang|pilihan
            $table->json('opsi')->nullable();         // daftar pilihan untuk jenis "pilihan"
            $table->boolean('di_depan')->default(false);
            $table->float('posisi')->default(0);
            $table->timestamps();

            $table->index(['board_id', 'posisi']);
        });

        Schema::create('kanban_bidang_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidang_id')->constrained('kanban_bidang')->cascadeOnDelete();
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->text('nilai')->nullable();
            $table->timestamps();

            $table->unique(['bidang_id', 'kartu_id']);
            $table->index('kartu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_bidang_nilai');
        Schema::dropIfExists('kanban_bidang');
    }
};
