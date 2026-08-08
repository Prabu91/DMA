<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Waktu event ditandai selesai (via OTP atau override admin).
            $table->timestamp('event_selesai_at')->nullable()->after('konfirmasi_lokasi_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('event_selesai_at');
        });
    }
};
