<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithSorting;
use App\Models\Cabang;
use App\Models\Produk;
use App\Services\ReportOrderQuery;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Report Order (super_admin) — laporan penjualan per PRODUK.
 * Satu baris = satu item order (order_items). Satu order dengan 4 produk
 * tampil 4 baris. Fokus: melihat produk apa terjual berapa banyak.
 * Bisa difilter (cari, cabang, produk, jenis item, rentang tanggal) + paginasi.
 */
#[Layout('layouts.app', ['contentWidth' => 'max-w-screen-2xl'])]
class ReportOrder extends Component
{
    use WithPagination, WithSorting;

    /** @return array<string, string> field sort → kolom/alias SQL. */
    protected function sortableColumns(): array
    {
        return [
            'booking' => 'o.booking_code',
            'tanggal' => 'o.tanggal_booking',
            'marketing' => 'marketing_nama',
            'id_sekolah' => 's.id_sekolah',
            'sekolah' => 's.nama',
            'item' => 'item_nama',
            'qty' => 'oi.qty',
            'nominal' => 'nominal',
        ];
    }

    #[Url]
    public string $q = '';

    #[Url]
    public string $cabangId = '';

    #[Url]
    public string $produkId = '';

    #[Url]
    public string $jenis = ''; // '' semua | 'berbayar' | 'free'

    #[Url]
    public string $dari = '';  // tanggal booking dari

    #[Url]
    public string $sampai = ''; // tanggal booking sampai

    #[Url]
    public int $perPage = 25;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
    }

    public function updated($name): void
    {
        if (in_array($name, ['q', 'cabangId', 'produkId', 'jenis', 'dari', 'sampai', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilter(): void
    {
        $this->reset(['q', 'cabangId', 'produkId', 'jenis', 'dari', 'sampai']);
        $this->resetPage();
    }

    public function adaFilter(): bool
    {
        return $this->q !== '' || $this->cabangId !== '' || $this->produkId !== ''
            || $this->jenis !== '' || $this->dari !== '' || $this->sampai !== '';
    }

    /** Query dibagi dengan API report — lihat ReportOrderQuery. */
    private function query(): ReportOrderQuery
    {
        return new ReportOrderQuery([
            'q' => $this->q,
            'cabang_id' => $this->cabangId,
            'produk_id' => $this->produkId,
            'jenis' => $this->jenis,
            'dari' => $this->dari,
            'sampai' => $this->sampai,
        ]);
    }

    public function render()
    {
        $q = $this->query();

        $rows = $this->applySort($q->baris(), 'o.tanggal_booking', 'desc')
            ->orderBy('oi.id')
            ->paginate($this->perPage);

        $ringkasan = $q->ringkasan();

        return view('livewire.report-order', [
            'rows' => $rows,
            'totalBaris' => $ringkasan['baris'],
            'totalQty' => $ringkasan['qty'],
            'totalNominal' => $ringkasan['nominal'],
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'produkOptions' => Produk::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }
}
