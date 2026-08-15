<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon pindah dari level-order (G2) ke PER ITEM (per satuan produk).
 * order_items.diskon (disetujui) + diskon_diajukan (usulan marketing).
 * orders.diskon_status tetap (state approval keseluruhan order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('diskon')->default(0)->after('harga');          // diskon per satuan (disetujui)
            $table->integer('diskon_diajukan')->nullable()->after('diskon'); // usulan marketing
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['diskon', 'diskon_diajukan']); // level-order diganti per-item
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['diskon', 'diskon_diajukan']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('diskon')->default(0)->after('total');
            $table->integer('diskon_diajukan')->nullable()->after('diskon');
        });
    }
};
