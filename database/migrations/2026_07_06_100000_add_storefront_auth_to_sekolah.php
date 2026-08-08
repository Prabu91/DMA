<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            // Email = identitas login sekolah (storefront). id_sekolah tetap sbagai kode akun.
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();

            // Future-proof Google login (Socialite) — JANGAN dibangun sekarang.
            // Dicocokkan by email nanti; password sudah nullable dari migrasi sebelumnya.
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropUnique(['google_id']);
            $table->dropColumn(['email', 'email_verified_at', 'google_id', 'avatar']);
        });
    }
};
