<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sampul kartu ukuran penuh, dan latar board berupa foto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_kartu', function (Blueprint $table) {
            $table->boolean('cover_penuh')->default(false)->after('cover_lampiran_id');
        });

        Schema::table('kanban_board', function (Blueprint $table) {
            $table->string('latar_path', 500)->nullable()->after('warna');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_kartu', fn (Blueprint $table) => $table->dropColumn('cover_penuh'));
        Schema::table('kanban_board', fn (Blueprint $table) => $table->dropColumn('latar_path'));
    }
};
