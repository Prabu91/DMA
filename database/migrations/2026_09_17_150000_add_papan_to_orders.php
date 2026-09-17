<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Papan order (kanban) — pengganti board Trello tahap C sampai P.
 *
 * tahap          : huruf tahap Trello (C, E, F, …, P). NULL = belum masuk papan
 *                  (order yang Hari-H-nya belum dikonfirmasi).
 * tahap_pj_id    : penanggung jawab di tahap itu (di Trello: list per orang).
 * tenggat_manual : tenggat yang ditetapkan SPV, menimpa tenggat hitungan
 *                  (di Trello: list "tgl 11", "sebelum tgl 10").
 * tertahan_*     : tanda tertahan beserta alasannya (di Trello: list "kode
 *                  menyusul", board "Hold ..."). Tanda, bukan tahap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('tahap', 2)->nullable()->index();
            $table->timestamp('tahap_masuk_at')->nullable();
            $table->foreignId('tahap_pj_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tenggat_manual')->nullable();
            $table->string('tertahan_alasan', 255)->nullable();
            $table->timestamp('tertahan_at')->nullable();
        });

        // Order yang event-nya sudah selesai langsung masuk tahap pertama papan.
        DB::table('orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'batal')
            ->where(fn ($q) => $q->whereNotNull('konfirmasi_hh_at')->orWhere('event_status', 'selesai'))
            ->update(['tahap' => 'C', 'tahap_masuk_at' => DB::raw('coalesce(konfirmasi_hh_at, event_selesai_at, now())')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tahap_pj_id');
            $table->dropColumn(['tahap', 'tahap_masuk_at', 'tenggat_manual', 'tertahan_alasan', 'tertahan_at']);
        });
    }
};
