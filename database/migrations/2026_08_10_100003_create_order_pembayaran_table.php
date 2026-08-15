<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('jenis'); // dp | pelunasan
            $table->integer('jumlah'); // nominal (integer, bukan teks)
            $table->date('tanggal_bayar');
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('keterangan')->nullable();
            $table->string('bukti_path')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('tanggal_bayar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_pembayaran');
    }
};
