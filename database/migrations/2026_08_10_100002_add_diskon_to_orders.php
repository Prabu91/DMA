<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Diskon disetujui (efektif mengurangi tagihan).
            $table->integer('diskon')->default(0)->after('total');
            // Alur pengajuan: marketing ajukan → admin_sales setujui/ubah/tolak.
            $table->integer('diskon_diajukan')->nullable()->after('diskon');
            $table->string('diskon_status')->nullable()->after('diskon_diajukan'); // diajukan|disetujui|ditolak
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['diskon', 'diskon_diajukan', 'diskon_status']);
        });
    }
};
