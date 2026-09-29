<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * List yang disematkan tiap orang: selalu berada di kiri dan ikut menempel
 * saat papan digeser — seperti freeze kolom di Excel. Pilihan pribadi, jadi
 * tidak mengganggu susunan list untuk orang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_kolom_pin', function (Blueprint $table) {
            $table->foreignId('kolom_id')->constrained('kanban_kolom')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'kolom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_kolom_pin');
    }
};
