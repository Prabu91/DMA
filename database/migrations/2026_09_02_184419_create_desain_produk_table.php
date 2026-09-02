<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot desain ↔ produk (many-to-many). Satu desain bisa dipakai di banyak produk.
 * Kolom `ukuran` (JSON array) = opsi ukuran produk yang berlaku untuk desain ini
 * pada produk tsb; null/[] = berlaku untuk semua ukuran produk itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desain_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desain_id')->constrained('desain')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->json('ukuran')->nullable(); // ["8R","10RP"]; null = semua ukuran
            $table->timestamps();
            $table->unique(['desain_id', 'produk_id']);
        });

        // Backfill dari model lama (desain.produk_id tunggal / desain.ukuran tunggal).
        foreach (DB::table('desain')->get() as $d) {
            $ukuran = $d->ukuran ? json_encode([$d->ukuran]) : null;

            if ($d->produk_id) {
                // Desain sudah spesifik satu produk.
                DB::table('desain_produk')->insert([
                    'desain_id' => $d->id, 'produk_id' => $d->produk_id,
                    'ukuran' => $ukuran, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                // Desain berbasis kategori → tempel ke semua produk di kategorinya.
                $produkIds = DB::table('produk')->where('kategori_id', $d->kategori_id)->pluck('id');
                foreach ($produkIds as $pid) {
                    DB::table('desain_produk')->insert([
                        'desain_id' => $d->id, 'produk_id' => $pid,
                        'ukuran' => $ukuran, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('desain_produk');
    }
};
