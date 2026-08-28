<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Gaya" produk dinaikkan jadi master ber-CRUD: tabel `frame`.
 * Kolom `produk.gaya` di-rename jadi `produk.frame` (tetap string = nama frame).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frame', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('status')->default('aktif'); // aktif | nonaktif
            $table->timestamps();
        });

        // Seed frame dari daftar lama + nilai yang sudah dipakai produk.
        $names = collect(['MINIMALIS', 'BLOK', '3D', 'GLITER', 'LEMBARAN'])
            ->merge(DB::table('produk')->whereNotNull('gaya')->distinct()->pluck('gaya'))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique();

        foreach ($names as $nama) {
            DB::table('frame')->insertOrIgnore([
                'nama' => $nama,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('produk', function (Blueprint $table) {
            $table->renameColumn('gaya', 'frame');
        });
    }

    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->renameColumn('frame', 'gaya');
        });
        Schema::dropIfExists('frame');
    }
};
