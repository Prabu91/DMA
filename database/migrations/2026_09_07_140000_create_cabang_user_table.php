<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu pengguna bisa memegang beberapa cabang, sederajat — tidak ada cabang utama.
 *
 * Kolom users.cabang_id SENGAJA tidak dihapus dulu. Kolom itu dipakai 150-an kali
 * di test dan menjadi dasar CabangScope, yaitu batas pemisah data antar cabang;
 * membongkarnya sekaligus berisiko membocorkan data satu cabang ke cabang lain.
 * Sebagai gantinya User::cabangIds() membaca GABUNGAN pivot + kolom lama, jadi
 * kolom itu cuma jadi salah satu cabang, bukan yang istimewa. Pemindahan penuh
 * bisa dilakukan terpisah setelah pivot terbukti stabil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabang_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'cabang_id']);
        });

        // Backfill: cabang yang sudah dipegang tiap user sekarang.
        $baris = DB::table('users')
            ->whereNotNull('cabang_id')
            ->get(['id', 'cabang_id'])
            ->map(fn ($u) => [
                'user_id' => $u->id,
                'cabang_id' => $u->cabang_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

        foreach (array_chunk($baris, 200) as $bagian) {
            DB::table('cabang_user')->insert($bagian);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cabang_user');
    }
};
