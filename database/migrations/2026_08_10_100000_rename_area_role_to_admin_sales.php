<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename role spatie 'area' → 'admin_sales' (admin area = admin sales = admin finance).
 * Hanya mengubah kolom name di tabel roles → role_id tetap, sehingga assignment
 * user di model_has_roles tak terpengaruh. Tidak menyentuh cabang.kode_area.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('name', 'area')->where('guard_name', 'web')
            ->update(['name' => 'admin_sales']);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'admin_sales')->where('guard_name', 'web')
            ->update(['name' => 'area']);
    }
};
