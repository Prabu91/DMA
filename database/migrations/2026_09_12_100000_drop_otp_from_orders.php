<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur penyelesaian event berhenti di KONFIRMASI HARI-H; langkah OTP dihapus.
 *
 * Order lama yang Hari-H-nya sudah dikonfirmasi tapi tidak pernah diselesaikan
 * lewat OTP ikut diselaraskan ke aturan baru — kalau tidak, order itu akan
 * menggantung selamanya karena tombol OTP-nya sudah tidak ada, dan tahap
 * "Sampai kantor" (yang mensyaratkan event selesai) tak akan pernah terbuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereNotNull('konfirmasi_hh_at')
            ->where('status', '!=', 'batal')
            ->where(function ($q) {
                $q->whereNull('event_status')
                    ->orWhereNotIn('event_status', ['selesai', 'batal']);
            })
            ->update([
                'event_status' => 'selesai',
                'event_selesai_at' => DB::raw('konfirmasi_hh_at'),
            ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_expires')->nullable();
        });
        // Isi OTP tidak dipulihkan: kode sekali pakai & sudah kedaluwarsa.
    }
};
