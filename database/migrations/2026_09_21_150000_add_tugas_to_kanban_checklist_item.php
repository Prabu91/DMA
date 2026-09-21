<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Item checklist bisa ditugaskan ke orang dan diberi tenggat sendiri, seperti di Trello. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_checklist_item', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('teks')->constrained('users')->nullOnDelete();
            $table->timestamp('tenggat_pada')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_checklist_item', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('tenggat_pada');
        });
    }
};
