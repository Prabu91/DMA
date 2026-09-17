<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order susulan: kesepakatan Fase 1, setiap susulan (siswa yang difoto
 * belakangan) adalah ORDER BARU supaya tidak menahan order yang sudah jalan.
 * Kolom ini menautkannya ke order induk, sehingga editor, laporan, dan tim
 * event tahu susulan itu milik event yang mana.
 *
 * SENGAJA tanpa foreign key. Order di sampah dibersihkan permanen setelah
 * masa simpan; kalau tautannya ikut dikosongkan (nullOnDelete), susulan itu
 * berubah jadi order biasa dan tiba-tiba dituntut H-7/H-2 lagi. Dengan kolom
 * biasa, order tetap dikenali sebagai susulan walau induknya sudah tiada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('order_induk_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_induk_id']);
            $table->dropColumn('order_induk_id');
        });
    }
};
