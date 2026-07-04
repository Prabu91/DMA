<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Password default semua akun contoh.
     */
    private const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        // Role lintas cabang (cabang_id & kode_role null).
        $this->makeUser([
            'name' => 'Super Admin',
            'nama' => 'Super Admin',
            'email' => 'superadmin@dma.test',
            'no_telp' => '081200000001',
        ], role: 'super_admin');

        $this->makeUser([
            'name' => 'Operasional Pusat',
            'nama' => 'Operasional Pusat',
            'email' => 'operasional@dma.test',
            'no_telp' => '081200000002',
        ], role: 'operasional');

        // Tim per cabang. kode_role unik per cabang (composite unique cabang_id+kode_role).
        Cabang::all()->each(function (Cabang $cabang) {
            $slug = strtolower($cabang->kode_area);

            $this->makeUser([
                'name' => "Area {$cabang->kode_area}",
                'nama' => "Area {$cabang->kode_area}",
                'email' => "area.{$slug}@dma.test",
                'cabang_id' => $cabang->id,
                'kode_role' => 'AREA',
                'no_telp' => '08120000'.$cabang->id.'10',
            ], role: 'area');

            foreach ([1, 2] as $n) {
                $this->makeUser([
                    'name' => "Marketing {$cabang->kode_area} {$n}",
                    'nama' => "Marketing {$cabang->kode_area} {$n}",
                    'email' => "marketing{$n}.{$slug}@dma.test",
                    'cabang_id' => $cabang->id,
                    'kode_role' => "MKT{$n}",
                    'no_telp' => '08120000'.$cabang->id.'2'.$n,
                ], role: 'marketing');
            }

            $this->makeUser([
                'name' => "Tim Event {$cabang->kode_area}",
                'nama' => "Tim Event {$cabang->kode_area}",
                'email' => "event.{$slug}@dma.test",
                'cabang_id' => $cabang->id,
                'kode_role' => 'EVT1',
                'no_telp' => '08120000'.$cabang->id.'30',
            ], role: 'tim_event');

            $this->makeUser([
                'name' => "Editor {$cabang->kode_area}",
                'nama' => "Editor {$cabang->kode_area}",
                'email' => "editor.{$slug}@dma.test",
                'cabang_id' => $cabang->id,
                'kode_role' => 'EDT1',
                'no_telp' => '08120000'.$cabang->id.'40',
            ], role: 'editor');
        });
    }

    /**
     * Buat/ambil user by email (idempotent), simpan label role, lalu assign role spatie.
     */
    private function makeUser(array $attributes, string $role): void
    {
        $attributes += [
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'role' => $role, // kolom label sesuai ERD (bukan acuan otorisasi)
        ];

        $user = User::firstOrCreate(
            ['email' => $attributes['email']],
            $attributes
        );

        $user->syncRoles([$role]);
    }
}
