<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gambar lampiran punya versi kecil, supaya sampul kartu di papan tidak mengunduh berkas asli. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_lampiran', function (Blueprint $table) {
            $table->string('thumb_path', 500)->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_lampiran', fn (Blueprint $table) => $table->dropColumn('thumb_path'));
    }
};
