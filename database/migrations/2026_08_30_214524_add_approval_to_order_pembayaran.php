<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran perlu di-APPROVE admin sales dulu (validasi DP masuk + bukti sah).
 * Hanya yang approved dihitung ke total dibayar. Data lama dianggap approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_pembayaran', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('jumlah'); // pending | approved | ditolak
            $table->foreignId('disetujui_oleh')->nullable()->after('dicatat_oleh')->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable()->after('disetujui_oleh');
        });

        // Data pembayaran lama dianggap sudah approved agar total tak berubah.
        DB::table('order_pembayaran')->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('order_pembayaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disetujui_oleh');
            $table->dropColumn(['status', 'disetujui_at']);
        });
    }
};
