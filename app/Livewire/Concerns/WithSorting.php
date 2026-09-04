<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Sorting kolom untuk komponen daftar (dipakai bersama <x-table.th sortable>).
 * Komponen wajib menyediakan whitelist kolom lewat sortableColumns() =
 * [field => kolom_sql]. Field di luar whitelist diabaikan (anti-injeksi).
 */
trait WithSorting
{
    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDir = 'asc';

    /** @return array<string, string> [field => kolom_sql] */
    abstract protected function sortableColumns(): array;

    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, $this->sortableColumns())) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * Set sort dari nilai gabungan "field|dir" — dipakai <x-sort-select> pada
     * daftar yang berbentuk kartu (tidak punya header kolom untuk diklik).
     */
    public function setSort(string $value): void
    {
        [$field, $dir] = array_pad(explode('|', $value, 2), 2, 'asc');

        if (! array_key_exists($field, $this->sortableColumns())) {
            return;
        }

        $this->sortField = $field;
        $this->sortDir = $dir === 'desc' ? 'desc' : 'asc';

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Terapkan sort ke builder (Eloquent/Query). $default = urutan bila belum ada sort. */
    protected function applySort($query, ?string $defaultColumn = null, string $defaultDir = 'desc')
    {
        $cols = $this->sortableColumns();

        if ($this->sortField !== '' && isset($cols[$this->sortField])) {
            return $query->orderBy($cols[$this->sortField], $this->sortDir === 'asc' ? 'asc' : 'desc');
        }

        return $defaultColumn ? $query->orderBy($defaultColumn, $defaultDir) : $query;
    }
}
