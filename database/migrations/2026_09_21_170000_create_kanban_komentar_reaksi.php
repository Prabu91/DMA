<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reaksi emoji pada komentar kartu, seperti reaksi di Trello. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_komentar_reaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komentar_id')->constrained('kanban_komentar')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamp('created_at')->nullable();

            $table->unique(['komentar_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_komentar_reaksi');
    }
};
