<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithSorting;
use App\Models\Cabang;
use App\Models\Produk;
use Illuminate\Support\Facades\DB;
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
#[Layout('layouts.app')]
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

    /** Builder dasar (join + filter), tanpa select/order/paginate. */
    private function baseQuery()
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('sekolah as s', 's.id', '=', 'o.sekolah_id')
            ->join('users as m', 'm.id', '=', 'o.marketing_id') // hanya order yang SUDAH ditugaskan marketing
            ->leftJoin('produk as p', 'p.id', '=', 'oi.produk_id')
            ->leftJoin('paket as pk', 'pk.id', '=', 'oi.paket_id')
            ->when($this->cabangId !== '', fn ($x) => $x->where('o.cabang_id', $this->cabangId))
            ->when($this->produkId !== '', fn ($x) => $x->where('oi.produk_id', $this->produkId))
            ->when($this->jenis === 'berbayar', fn ($x) => $x->where('oi.is_free', false))
            ->when($this->jenis === 'free', fn ($x) => $x->where('oi.is_free', true))
            ->when($this->dari !== '', fn ($x) => $x->whereDate('o.tanggal_booking', '>=', $this->dari))
            ->when($this->sampai !== '', fn ($x) => $x->whereDate('o.tanggal_booking', '<=', $this->sampai))
            ->when(trim($this->q) !== '', function ($x) {
                $t = '%'.trim($this->q).'%';
                $x->where(fn ($w) => $w->where('p.nama', 'ilike', $t)
                    ->orWhere('pk.nama', 'ilike', $t)
                    ->orWhere('s.nama', 'ilike', $t)
                    ->orWhere('s.id_sekolah', 'ilike', $t)
                    ->orWhere('o.booking_code', 'ilike', $t));
            });
    }

    public function render()
    {
        $rowsQuery = (clone $this->baseQuery())
            ->select([
                'oi.id', 'oi.qty', 'oi.is_free', 'oi.tipe_item', 'oi.harga', 'oi.diskon',
                'o.id as order_id', 'o.booking_code', 'o.tanggal_booking',
                DB::raw('coalesce(m.nama, m.name) as marketing_nama'),
                's.id_sekolah', 's.nama as sekolah_nama', 's.alamat as sekolah_alamat',
                DB::raw('coalesce(p.nama, pk.nama) as item_nama'),
                DB::raw('(oi.harga - oi.diskon) * oi.qty as nominal'), // nominal setelah diskon
            ]);

        $rows = $this->applySort($rowsQuery, 'o.tanggal_booking', 'desc')
            ->orderBy('oi.id')
            ->paginate($this->perPage);

        // Ringkasan untuk seluruh hasil filter (bukan hanya halaman ini).
        $totalBaris = $rows->total(); // paginate sudah menghitung total → tak perlu COUNT lagi
        $totalQty = (int) (clone $this->baseQuery())->sum('oi.qty');
        $totalNominal = (int) (clone $this->baseQuery())->sum(DB::raw('(oi.harga - oi.diskon) * oi.qty'));

        return view('livewire.report-order', [
            'rows' => $rows,
            'totalBaris' => $totalBaris,
            'totalQty' => $totalQty,
            'totalNominal' => $totalNominal,
            'cabangOptions' => Cabang::orderBy('nama')->pluck('nama', 'id')->all(),
            'produkOptions' => Produk::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }
}
