<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code')->unique();
            $table->foreignId('sekolah_id')->constrained('sekolah');
            $table->foreignId('marketing_id')->nullable()->constrained('users');
            $table->foreignId('cabang_id')->constrained('cabang');
            $table->string('status')->nullable();
            $table->string('event_status')->nullable();
            $table->date('tanggal_event')->nullable();
            $table->string('jam_event')->nullable();
            $table->integer('jumlah_siswa')->nullable();
            $table->string('keterangan')->nullable();
            $table->integer('total')->default(0);
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires')->nullable();
            $table->string('tahun_ajaran')->nullable();
            $table->timestamp('tanggal_booking')->nullable();
            $table->timestamps();

            // Index performa (FK di Postgres tidak otomatis ter-index).
            // booking_code sudah ter-index lewat unique().
            $table->index('sekolah_id');
            $table->index('marketing_id');
            $table->index('cabang_id');
            $table->index('status');
            $table->index('tanggal_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
