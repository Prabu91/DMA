<?php

namespace App\Support\Kanban;

use App\Models\Kanban\Board;
use App\Models\User;

/**
 * Hak akses kanban, mengikuti pola workspace Trello:
 * - Semua staf adalah anggota workspace dan bisa membuat board.
 * - Board "semua staf" bisa dilihat siapa pun; yang belum bergabung hanya
 *   membaca sampai menekan "Gabung". Board "hanya anggota" tersembunyi.
 * - Admin pusat (super admin, operasional, admin sales) bisa melihat &
 *   mengubah semua board.
 * - Board Order bisa diubah semua staf — sama seperti board order di Trello
 *   yang diikuti semua orang.
 */
final class Akses
{
    public const PERAN_STAF = ['super_admin', 'operasional', 'admin_sales', 'marketing', 'tim_event', 'editor'];

    public const PERAN_ADMIN = ['super_admin', 'operasional', 'admin_sales'];

    public static function admin(User $user): bool
    {
        return $user->hasAnyRole(self::PERAN_ADMIN);
    }

    /**
     * Anggota board (peran admin/anggota). Baris berperan "pengamat" hanya
     * menandai bintang pada board yang tidak diikuti — bukan keanggotaan.
     */
    public static function anggota(User $user, Board $board): bool
    {
        return $board->relationLoaded('anggota')
            ? $board->anggota->contains(fn ($u) => $u->id === $user->id && $u->pivot->peran !== 'pengamat')
            : $board->anggota()->whereKey($user->id)->wherePivot('peran', '!=', 'pengamat')->exists();
    }

    public static function bolehLihat(User $user, Board $board): bool
    {
        return $board->visibilitas !== 'privat'
            || self::admin($user)
            || self::anggota($user, $board);
    }

    public static function bolehUbah(User $user, Board $board): bool
    {
        if ($board->diarsipkan_at !== null) {
            return false;
        }

        return self::admin($user)
            || $board->isOrder()
            || self::anggota($user, $board);
    }

    /** Ubah nama/warna/visibilitas, kelola anggota & label, arsipkan board. */
    public static function bolehKelola(User $user, Board $board): bool
    {
        if (self::admin($user)) {
            return true;
        }
        if ($board->isOrder()) {
            return false;
        }
        if ((int) $board->dibuat_oleh === (int) $user->id) {
            return true;
        }

        $baris = $board->anggota()->whereKey($user->id)->first();

        return $baris?->pivot?->peran === 'admin';
    }
}
