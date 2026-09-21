<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kabar kanban: tabel notifikasi bawaan Laravel (dipakai lonceng & email)
 * dan daftar pengikut kartu (siapa yang dikabari perubahan sebuah kartu).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('kanban_kartu_pengikut', function (Blueprint $table) {
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['kartu_id', 'user_id']);
        });

        Schema::table('kanban_kartu', function (Blueprint $table) {
            // Penanda supaya pengingat tenggat tidak terkirim berulang.
            $table->timestamp('diingatkan_at')->nullable()->after('tenggat_selesai_at');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_kartu', fn (Blueprint $table) => $table->dropColumn('diingatkan_at'));
        Schema::dropIfExists('kanban_kartu_pengikut');
        Schema::dropIfExists('notifications');
    }
};
