<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('desain', function (Blueprint $table) {
            // Label ukuran opsional (mis. "8R", "10R"); null = berlaku semua ukuran.
            $table->string('ukuran')->nullable()->after('seri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('desain', function (Blueprint $table) {
            $table->dropColumn('ukuran');
        });
    }
};
