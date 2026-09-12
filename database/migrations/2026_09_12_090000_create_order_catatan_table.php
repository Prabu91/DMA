<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan staf pada sebuah order — berupa utas, bukan satu kolom yang ditimpa,
 * supaya siapa menulis apa dan kapan tetap terbaca.
 *
 * Sengaja TIDAK memakai tabel order_activities: yang itu jejak audit yang
 * ditulis sistem. Mencampur catatan manusia ke sana membuat keduanya sulit
 * dibaca dan sulit disaring.
 *
 * Penulisnya dibiarkan null bila akunnya dihapus — catatannya tetap ada
 * sebagai riwayat, hanya kehilangan nama penulis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_catatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('isi');
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_catatan');
    }
};
