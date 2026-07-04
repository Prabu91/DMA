<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kolom domain DMA sesuai ERD. Kolom 'role' dipertahankan apa adanya
            // sebagai label; otorisasi tetap memakai spatie (single source of truth).
            $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabang');
            $table->string('nama')->nullable()->after('name');
            $table->string('role')->nullable()->after('nama');
            $table->string('kode_role')->nullable()->after('role');
            $table->string('no_telp')->nullable()->after('kode_role');

            $table->unique(['cabang_id', 'kode_role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cabang_id', 'kode_role']);
            $table->dropConstrainedForeignId('cabang_id');
            $table->dropColumn(['nama', 'role', 'kode_role', 'no_telp']);
        });
    }
};
