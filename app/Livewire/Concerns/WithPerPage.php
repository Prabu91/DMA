<?php

namespace App\Livewire\Concerns;

use App\Support\Tabel;
use Livewire\Attributes\Url;

/**
 * Pilihan jumlah baris per halaman untuk komponen daftar.
 * Dipakai bersama WithPagination dan <x-table-footer>.
 */
trait WithPerPage
{
    #[Url]
    public int $perPage = 20;

    /** @return int[] */
    public static function perPageOptions(): array
    {
        return Tabel::PER_PAGE;
    }

    /** Nilai di luar daftar diabaikan (bisa datang dari query string). */
    public function updatedPerPage(): void
    {
        if (! in_array((int) $this->perPage, static::perPageOptions(), true)) {
            $this->perPage = static::perPageOptions()[0];
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Jumlah baris yang aman dipakai di query. */
    protected function perPage(): int
    {
        return in_array((int) $this->perPage, static::perPageOptions(), true)
            ? (int) $this->perPage
            : static::perPageOptions()[0];
    }
}
