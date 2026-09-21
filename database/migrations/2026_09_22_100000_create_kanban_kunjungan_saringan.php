<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua penunjang navigasi: catatan board yang baru dibuka tiap orang, dan
 * saringan kartu yang disimpan supaya bisa dipakai ulang sekali klik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_kunjungan', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dibuka_at');

            $table->primary(['user_id', 'board_id']);
            $table->index(['user_id', 'dibuka_at']);
        });

        Schema::create('kanban_saringan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama', 60);
            $table->json('isi');
            $table->timestamps();

            $table->index(['board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_saringan');
        Schema::dropIfExists('kanban_kunjungan');
    }
};
