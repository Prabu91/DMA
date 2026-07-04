<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->string('tipe_item');
            // Tepat satu dari produk_id / paket_id / desain_id terisi sesuai tipe_item.
            $table->foreignId('produk_id')->nullable()->constrained('produk');
            $table->foreignId('paket_id')->nullable()->constrained('paket');
            $table->foreignId('desain_id')->nullable()->constrained('desain');
            $table->string('opsi_ukuran')->nullable(); // snapshot string, bukan FK
            $table->integer('qty')->default(1);
            $table->integer('harga')->default(0);
            $table->boolean('is_free')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
