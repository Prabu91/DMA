<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Foto bukti transfer DP (1 foto). Disimpan di disk public/bukti-bayar.
            $table->string('bukti_dp_path')->nullable()->after('keterangan');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('bukti_dp_path');
        });
    }
};
