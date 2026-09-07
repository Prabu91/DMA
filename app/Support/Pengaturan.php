<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Saklar aplikasi yang bisa diubah admin lewat halaman Pengaturan.
 * Dibaca di banyak request, jadi di-cache dan dibuang cache-nya saat disimpan.
 */
final class Pengaturan
{
    /** Sembunyikan harga katalog dari pengunjung yang belum masuk. */
    public const HARGA_PUBLIK_DISEMBUNYIKAN = 'harga_publik_disembunyikan';

    /**
     * Hash token API report. Token mentahnya TIDAK pernah disimpan — hanya
     * ditampilkan sekali saat dibuat, seperti perlakuan kata sandi.
     */
    public const API_TOKEN_HASH = 'api_report_token_hash';

    private const CACHE_KEY = 'pengaturan.semua';

    /** @return array<string, string|null> */
    public static function semua(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            // Tabel bisa belum ada saat migrasi pertama dijalankan.
            if (! app()->runningInConsole() || DB::getSchemaBuilder()->hasTable('pengaturan')) {
                return DB::table('pengaturan')->pluck('nilai', 'kunci')->all();
            }

            return [];
        });
    }

    public static function bool(string $kunci, bool $default = false): bool
    {
        $nilai = self::semua()[$kunci] ?? null;

        return $nilai === null ? $default : (bool) (int) $nilai;
    }

    public static function set(string $kunci, string|int|bool|null $nilai): void
    {
        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => $kunci],
            ['nilai' => is_bool($nilai) ? (string) (int) $nilai : $nilai, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public static function teks(string $kunci): ?string
    {
        return self::semua()[$kunci] ?? null;
    }

    public static function hapus(string $kunci): void
    {
        DB::table('pengaturan')->where('kunci', $kunci)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /** Harga boleh ditampilkan? Tamu publik ikut saklar; yang sudah masuk selalu boleh. */
    public static function bolehLihatHarga(): bool
    {
        if (auth('web')->check() || auth('sekolah')->check()) {
            return true;
        }

        return ! self::bool(self::HARGA_PUBLIK_DISEMBUNYIKAN);
    }
}
