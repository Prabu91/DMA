<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kecamatan', function (Blueprint $table) {
            // Kode wilayah (BPS/emsifa) — jejak sumber saat impor dari public API.
            $table->string('kode')->nullable()->after('nama');
            $table->index('kode');
        });
    }

    public function down(): void
    {
        Schema::table('kecamatan', function (Blueprint $table) {
            $table->dropIndex(['kode']);
            $table->dropColumn('kode');
        });
    }
};
