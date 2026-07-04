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
 * - Selain itu => hanya baris dengan cabang_id = cabang user.
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

        // Kualifikasikan nama kolom agar aman saat query memakai join.
        $builder->where($model->getTable().'.cabang_id', $user->cabang_id);
    }
}
