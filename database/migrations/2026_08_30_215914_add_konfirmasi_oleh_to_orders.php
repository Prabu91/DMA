<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['konfirmasi_lokasi_oleh', 'konfirmasi_h7_oleh', 'konfirmasi_h2_oleh', 'konfirmasi_hh_oleh'] as $col) {
                $table->foreignId($col)->nullable()->after('konfirmasi_hh_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['konfirmasi_lokasi_oleh', 'konfirmasi_h7_oleh', 'konfirmasi_h2_oleh', 'konfirmasi_hh_oleh'] as $col) {
                $table->dropConstrainedForeignId($col);
            }
        });
    }
};
