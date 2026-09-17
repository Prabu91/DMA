<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kanban DMA — pengganti Trello (target utama Fase 2).
 *
 * Susunannya mengikuti Trello: board → list (di sini "kolom", karena `list`
 * kata kunci PHP) → kartu, dengan label, anggota, checklist, komentar,
 * lampiran, dan riwayat aktivitas. Kartu boleh tertaut ke order (order_id);
 * board berjenis "order" diisi otomatis dari order.
 *
 * Urutan kolom & kartu memakai `posisi` pecahan (seperti Trello): memindah
 * satu kartu cukup mengubah satu baris, tanpa menomori ulang seisi list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_board', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 120);
            $table->text('deskripsi')->nullable();
            $table->string('warna', 20)->default('biru');
            $table->string('jenis', 20)->default('bebas');           // bebas | order
            $table->string('visibilitas', 20)->default('workspace');  // workspace | privat
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diarsipkan_at')->nullable();
            $table->timestamps();
            $table->index(['jenis', 'diarsipkan_at']);
        });

        Schema::create('kanban_board_anggota', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('peran', 20)->default('anggota');          // admin | anggota
            $table->boolean('berbintang')->default(false);
            $table->timestamps();
            $table->primary(['board_id', 'user_id']);
        });

        Schema::create('kanban_kolom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->string('nama', 120);
            $table->double('posisi');
            $table->string('warna', 20)->nullable();
            // Board order: satu kolom per marketing.
            $table->foreignId('marketing_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diarsipkan_at')->nullable();
            $table->timestamps();
            $table->index(['board_id', 'diarsipkan_at', 'posisi']);
        });

        Schema::create('kanban_kartu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->foreignId('kolom_id')->constrained('kanban_kolom')->cascadeOnDelete();
            $table->double('posisi');
            $table->string('judul', 255);
            $table->text('deskripsi')->nullable();
            // Satu order = satu kartu.
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->string('cover_warna', 20)->nullable();
            $table->unsignedBigInteger('cover_lampiran_id')->nullable();
            $table->date('mulai_pada')->nullable();
            $table->timestamp('tenggat_pada')->nullable();
            $table->timestamp('tenggat_selesai_at')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diarsipkan_at')->nullable();
            $table->timestamps();
            $table->index(['kolom_id', 'diarsipkan_at', 'posisi']);
            $table->index(['board_id', 'diarsipkan_at']);
        });

        Schema::create('kanban_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->string('nama', 60)->nullable();
            $table->string('warna', 20);
            $table->timestamps();
        });

        Schema::create('kanban_kartu_label', function (Blueprint $table) {
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('kanban_label')->cascadeOnDelete();
            $table->primary(['kartu_id', 'label_id']);
        });

        Schema::create('kanban_kartu_anggota', function (Blueprint $table) {
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['kartu_id', 'user_id']);
        });

        Schema::create('kanban_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->string('judul', 120);
            $table->double('posisi');
            $table->timestamps();
        });

        Schema::create('kanban_checklist_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('kanban_checklist')->cascadeOnDelete();
            $table->string('teks', 500);
            $table->double('posisi');
            $table->timestamp('selesai_at')->nullable();
            $table->foreignId('selesai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kanban_komentar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('isi');
            $table->timestamp('diubah_at')->nullable();
            $table->timestamps();
            $table->index(['kartu_id', 'created_at']);
        });

        Schema::create('kanban_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_id')->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama', 255);
            $table->string('path', 500);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->timestamps();
        });

        Schema::create('kanban_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('kanban_board')->cascadeOnDelete();
            $table->foreignId('kartu_id')->nullable()->constrained('kanban_kartu')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 40);
            $table->string('keterangan', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kartu_id', 'created_at']);
            $table->index(['board_id', 'created_at']);
        });

        // Board order: satu-satunya board sistem, diisi otomatis dari order.
        DB::table('kanban_board')->insert([
            'nama' => 'Order',
            'deskripsi' => 'Kartu dibuat otomatis dari order, satu list per marketing.',
            'warna' => 'oranye',
            'jenis' => 'order',
            'visibilitas' => 'workspace',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach ([
            'kanban_aktivitas', 'kanban_lampiran', 'kanban_komentar', 'kanban_checklist_item',
            'kanban_checklist', 'kanban_kartu_anggota', 'kanban_kartu_label', 'kanban_label',
            'kanban_kartu', 'kanban_kolom', 'kanban_board_anggota', 'kanban_board',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
