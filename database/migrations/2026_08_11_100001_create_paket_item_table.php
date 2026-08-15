<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Definisi Paket yang kaya: tiap isi paket = produk + varian + qty + harga + free.
 * Menggantikan pivot ramping paket_produk. Saat order, paket DIPECAH jadi
 * order_items produk berdasarkan baris-baris ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paket_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paket_id')->constrained('paket')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->string('opsi_ukuran')->nullable();
            $table->foreignId('desain_id')->nullable()->constrained('desain')->nullOnDelete();
            $table->integer('qty')->default(1);
            $table->integer('harga')->default(0);
            $table->boolean('is_free')->default(false);
            $table->timestamps();

            $table->index('paket_id');
        });

        // Migrasi data pivot lama → paket_item (qty=1, harga=produk.harga).
        $rows = DB::table('paket_produk as pp')
            ->join('produk as p', 'p.id', '=', 'pp.produk_id')
            ->get(['pp.paket_id', 'pp.produk_id', 'p.harga']);
        foreach ($rows as $r) {
            DB::table('paket_item')->insert([
                'paket_id' => $r->paket_id,
                'produk_id' => $r->produk_id,
                'qty' => 1,
                'harga' => (int) $r->harga,
                'is_free' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Sinkron paket.harga = Σ item non-free (harga × qty) agar konsisten dgn hargaJual.
        foreach (DB::table('paket')->pluck('id') as $paketId) {
            $sum = DB::table('paket_item')->where('paket_id', $paketId)->where('is_free', false)
                ->sum(DB::raw('harga * qty'));
            DB::table('paket')->where('id', $paketId)->update(['harga' => (int) $sum]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paket_item');
    }
};
