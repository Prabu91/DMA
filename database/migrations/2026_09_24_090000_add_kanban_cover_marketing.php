<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thumbnail bawaan tiap marketing: gambar yang otomatis jadi cover kartu order
 * milik marketing itu — padanan template bergambar yang dipakai di Trello.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kanban_cover_path', 500)->nullable()->after('avatar');
        });

        Schema::table('kanban_kartu', function (Blueprint $table) {
            $table->foreignId('cover_marketing_id')->nullable()->after('cover_lampiran_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kanban_kartu', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_marketing_id');
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('kanban_cover_path'));
    }
};
