<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kartu templat: cetakan untuk membuat kartu baru, seperti template card Trello. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_kartu', function (Blueprint $table) {
            $table->boolean('templat')->default(false)->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_kartu', fn (Blueprint $table) => $table->dropColumn('templat'));
    }
};
