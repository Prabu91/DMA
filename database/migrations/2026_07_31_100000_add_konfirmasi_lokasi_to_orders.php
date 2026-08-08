<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Waktu tim event mengonfirmasi ulang detail order di lokasi (sekolah).
            $table->timestamp('konfirmasi_lokasi_at')->nullable()->after('konfirmasi_hh_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('konfirmasi_lokasi_at');
        });
    }
};
