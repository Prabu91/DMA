<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redaksi = teks yang ikut dicetak di produk: nama lengkap + alamat sekolah.
 * Di Trello diketik manual lalu dilampirkan sebagai REDAKSI.txt.
 *
 * orders.redaksi NULL berarti "ikut data sekolah" — koreksi per order hanya
 * diisi bila nama cetak memang harus beda, dan TIDAK mengubah data sekolah.
 * order_items.tanpa_redaksi menandai produk yang dicetak tanpa teks
 * (di Trello: "PFM-008 TANPA REDAKSI").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('redaksi')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('tanpa_redaksi')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('redaksi');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('tanpa_redaksi');
        });
    }
};
