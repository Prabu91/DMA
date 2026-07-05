<?php

namespace App\Policies;

use App\Models\User;

/**
 * Katalog global — hanya super_admin & operasional yang mengelola aturan free.
 */
class AturanFreeSekolahPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('operasional');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('operasional');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('operasional');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('operasional');
    }
}
