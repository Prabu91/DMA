<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QC item per peran — pengganti tiga checklist kembar di kartu Trello
 * (MARKETING, TEAM EVENT, ADMIN) yang isinya item order yang sama.
 *
 * Marketing tidak perlu kolom: item order memang dibuat marketing. Tim event
 * mencentang di lokasi sebelum konfirmasi Hari-H; admin mencentang ulang
 * sesudah event (H+1) berdasarkan invoice & DO.
 *
 * Kolom per peran, bukan tabel terpisah: perannya tetap dua, dan progres QC
 * dibaca di banyak tempat (halaman event, halaman order, nanti kartu kanban).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('qc_event_at')->nullable();
            $table->foreignId('qc_event_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_admin_at')->nullable();
            $table->foreignId('qc_admin_oleh')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qc_event_oleh');
            $table->dropConstrainedForeignId('qc_admin_oleh');
            $table->dropColumn(['qc_event_at', 'qc_admin_at']);
        });
    }
};
