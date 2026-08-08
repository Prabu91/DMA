<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
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
        // Daftar order tetap boleh dibuka; isinya sudah dibatasi CabangScope.
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $this->sameCabang($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['operasional', 'area', 'marketing', 'tim_event']);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->sameCabang($user, $order)
            && $user->hasAnyRole(['operasional', 'area', 'marketing', 'tim_event']);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->sameCabang($user, $order)
            && $user->hasAnyRole(['operasional', 'area']);
    }

    /**
     * Kelola pelaksanaan event (konfirmasi ulang, revisi, OTP, selesai).
     * Hanya anggota tim event yang di-assign ke order ini — atau admin
     * lintas cabang (super_admin/operasional) sebagai jalur override.
     */
    public function manageEvent(User $user, Order $order): bool
    {
        if ($user->seesAllCabang()) {
            return true;
        }

        return $user->hasRole('tim_event')
            && $order->timEvent()->whereKey($user->id)->exists();
    }

    /**
     * operasional lihat semua cabang; selain itu harus satu cabang.
     */
    protected function sameCabang(User $user, Order $order): bool
    {
        if ($user->seesAllCabang()) {
            return true;
        }

        return $user->cabang_id !== null
            && $user->cabang_id === $order->cabang_id;
    }
}
