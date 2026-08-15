<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Waktu tim event tiba kembali di kantor setelah event (setelah OTP).
            $table->timestamp('sampai_kantor_at')->nullable()->after('event_selesai_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sampai_kantor_at');
        });
    }
};
