<?php

namespace App\Policies;

use App\Models\Sekolah;
use App\Models\User;

class SekolahPolicy
{
    /**
     * super_admin diberi akses penuh ke semua ability.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sekolah $sekolah): bool
    {
        return $this->sameCabang($user, $sekolah);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['operasional', 'area', 'marketing']);
    }

    public function update(User $user, Sekolah $sekolah): bool
    {
        return $this->sameCabang($user, $sekolah)
            && $user->hasAnyRole(['operasional', 'area', 'marketing']);
    }

    public function delete(User $user, Sekolah $sekolah): bool
    {
        return $this->sameCabang($user, $sekolah)
            && $user->hasAnyRole(['operasional', 'area']);
    }

    protected function sameCabang(User $user, Sekolah $sekolah): bool
    {
        if ($user->seesAllCabang()) {
            return true;
        }

        return $user->cabang_id !== null
            && $user->cabang_id === $sekolah->cabang_id;
    }
}
