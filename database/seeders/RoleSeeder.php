<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Daftar role sistem DMA. Spatie yang mengelola otorisasi (single source of truth).
     */
    public const ROLES = [
        'super_admin',
        'operasional',
        'area',
        'marketing',
        'tim_event',
        'editor',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }
    }
}
