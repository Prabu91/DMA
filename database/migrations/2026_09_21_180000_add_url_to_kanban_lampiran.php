<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lampiran bisa berupa tautan (mis. Google Drive), bukan hanya berkas unggahan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_lampiran', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('path');
            $table->string('path', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kanban_lampiran', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
