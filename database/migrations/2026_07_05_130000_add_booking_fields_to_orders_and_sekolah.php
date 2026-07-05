<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders.marketing_id sudah nullable sejak awal — tak perlu diubah.
        Schema::table('orders', function (Blueprint $table) {
            // Jalur booking: 'sekolah' (self-service) | 'marketing' (dibuatkan staf).
            $table->string('sumber')->nullable();
            $table->index('sumber');
        });

        Schema::table('sekolah', function (Blueprint $table) {
            // Kredensial login sekolah (multi-guard). Di-set/reset oleh staf.
            $table->string('password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['sumber']);
            $table->dropColumn('sumber');
        });

        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
