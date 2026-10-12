<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci board / list / kartu. Yang terkunci tetap bisa dibuka dan dibaca; yang
 * dibatasi hanya mengubahnya. Daftar aksesnya boleh berisi orang tertentu,
 * peran, atau campuran keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_kunci', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10);              // board | kolom | kartu
            $table->unsignedBigInteger('target_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['jenis', 'target_id']);
        });

        Schema::create('kanban_kunci_akses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunci_id')->constrained('kanban_kunci')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('peran', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kunci_id', 'user_id']);
            $table->unique(['kunci_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_kunci_akses');
        Schema::dropIfExists('kanban_kunci');
    }
};
