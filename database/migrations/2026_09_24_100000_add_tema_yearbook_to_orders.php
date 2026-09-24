<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tema yearbook: isian opsional yang dilengkapi marketing bila pesanannya yearbook. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->string('tema_yearbook', 200)->nullable()->after('keterangan'));
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tema_yearbook'));
    }
};
