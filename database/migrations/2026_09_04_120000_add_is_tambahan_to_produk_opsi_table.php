<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai varian bisa MENAMBAH harga, bukan hanya menggantinya.
 *
 * Kasus nyata: Yearbook punya varian "Halaman" yang menentukan harga (mengganti),
 * lalu varian "Box" yang menambah Rp40.000 di atasnya. Sebelum ini semua nilai
 * varian selalu mengganti harga produk, sehingga tambahan box justru menghapus
 * harga halaman yang sudah dipilih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk_opsi', function (Blueprint $table) {
            $table->boolean('is_tambahan')->default(false)->after('harga_override');
        });
    }

    public function down(): void
    {
        Schema::table('produk_opsi', function (Blueprint $table) {
            $table->dropColumn('is_tambahan');
        });
    }
};
