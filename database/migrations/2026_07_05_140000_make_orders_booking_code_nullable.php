<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * booking_code baru dibuat setelah marketing_id terisi (Fase 6), sehingga
     * harus nullable. Unique index dipertahankan (Postgres mengizinkan >1 NULL).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN booking_code DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders ALTER COLUMN booking_code SET NOT NULL');
    }
};
