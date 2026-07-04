<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('id_sekolah')->unique();
            $table->string('nama');
            $table->string('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->string('pic_sekolah')->nullable();
            $table->string('no_telp_pic')->nullable();
            $table->string('email_guru')->nullable();
            $table->string('maps_link')->nullable();
            $table->foreignId('cabang_id')->nullable()->constrained('cabang');
            $table->timestamps();

            $table->index('cabang_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekolah');
    }
};
