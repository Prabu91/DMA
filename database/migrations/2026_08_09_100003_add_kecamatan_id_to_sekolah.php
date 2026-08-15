<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            // Kecamatan sekolah → acuan auto-assign marketing per kecamatan.
            $table->foreignId('kecamatan_id')->nullable()->after('kota')
                ->constrained('kecamatan')->nullOnDelete();
            $table->index('kecamatan_id');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kecamatan_id');
        });
    }
};
