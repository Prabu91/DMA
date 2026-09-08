<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Membatasi query hanya pada cabang milik user yang sedang login.
 *
 * Aturan:
 * - Tidak ada user login (guest / CLI / seeder) => TIDAK difilter, supaya
 *   perintah artisan & seeding tetap bisa mengakses semua data.
 * - super_admin & operasional (User::seesAllCabang()) => TIDAK difilter,
 *   mereka melihat semua cabang.
 * - Selain itu => hanya baris yang cabang_id-nya termasuk cabang user.
 *   User bisa memegang beberapa cabang sekaligus (sederajat, tanpa cabang utama);
 *   user tanpa cabang sama sekali tidak melihat apa pun.
 *
 * Global scope ini berlaku untuk SEMUA query Eloquent model terkait,
 * termasuk find($id), sehingga akses lintas-cabang lewat id langsung
 * otomatis menghasilkan null.
 */
class CabangScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if (method_exists($user, 'seesAllCabang') && $user->seesAllCabang()) {
            return;
        }

        // Guard `sekolah` juga melewati scope ini, dan model Sekolah tidak punya
        // cabangIds() — hanya kolom cabang_id-nya sendiri. Tanpa cadangan ini,
        // portal sekolah kehilangan seluruh datanya.
        $cabangIds = method_exists($user, 'cabangIds')
            ? $user->cabangIds()
            : array_values(array_filter([$user->cabang_id ?? null], fn ($id) => $id !== null));

        // Tanpa cabang = tidak melihat apa pun. Ini menjaga perilaku lama, saat
        // cabang_id null membuat pembandingan tak pernah cocok.
        if ($cabangIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Kualifikasikan nama kolom agar aman saat query memakai join.
        $builder->whereIn($model->getTable().'.cabang_id', $cabangIds);
    }
}
