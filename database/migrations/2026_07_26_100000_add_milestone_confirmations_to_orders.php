<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda konfirmasi milestone event (H-7 / H-2 / Hari-H) oleh marketing.
 * Null = belum dikonfirmasi. Countdown & "terlewat" dihitung dari tanggal_event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('konfirmasi_h7_at')->nullable()->after('event_status');
            $table->timestamp('konfirmasi_h2_at')->nullable()->after('konfirmasi_h7_at');
            $table->timestamp('konfirmasi_hh_at')->nullable()->after('konfirmasi_h2_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['konfirmasi_h7_at', 'konfirmasi_h2_at', 'konfirmasi_hh_at']);
        });
    }
};
