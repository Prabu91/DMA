<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Sumber tunggal query Report Order.
 *
 * Dipakai bersama halaman Report order (Livewire) dan API report. Kalau
 * query-nya digandakan, cepat atau lambat angka omset di web report akan
 * berbeda dengan yang dilihat DMA di panel — dan selisih uang paling sulit
 * dipercaya lagi begitu terjadi.
 *
 * Satu baris = satu item order (order_items). Hanya order yang SUDAH
 * ditugaskan ke marketing yang ikut (join ke users).
 */
class ReportOrderQuery
{
    /** @var array{q?:string, cabang_id?:string|int|null, produk_id?:string|int|null, jenis?:string, dari?:string, sampai?:string} */
    private array $f;

    public function __construct(array $filter = [])
    {
        $this->f = $filter;
    }

    private function nilai(string $kunci): string
    {
        return trim((string) ($this->f[$kunci] ?? ''));
    }

    /** Builder dasar (join + filter), tanpa select/order/paginate. */
    public function base()
    {
        $q = $this->nilai('q');

        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('sekolah as s', 's.id', '=', 'o.sekolah_id')
            ->join('users as m', 'm.id', '=', 'o.marketing_id')
            ->leftJoin('produk as p', 'p.id', '=', 'oi.produk_id')
            ->leftJoin('paket as pk', 'pk.id', '=', 'oi.paket_id')
            ->when($this->nilai('cabang_id') !== '', fn ($x) => $x->where('o.cabang_id', $this->nilai('cabang_id')))
            ->when($this->nilai('produk_id') !== '', fn ($x) => $x->where('oi.produk_id', $this->nilai('produk_id')))
            ->when($this->nilai('jenis') === 'berbayar', fn ($x) => $x->where('oi.is_free', false))
            ->when($this->nilai('jenis') === 'free', fn ($x) => $x->where('oi.is_free', true))
            ->when($this->nilai('dari') !== '', fn ($x) => $x->whereDate('o.tanggal_booking', '>=', $this->nilai('dari')))
            ->when($this->nilai('sampai') !== '', fn ($x) => $x->whereDate('o.tanggal_booking', '<=', $this->nilai('sampai')))
            ->when($q !== '', function ($x) use ($q) {
                $t = '%'.$q.'%';
                $x->where(fn ($w) => $w->where('p.nama', 'ilike', $t)
                    ->orWhere('pk.nama', 'ilike', $t)
                    ->orWhere('s.nama', 'ilike', $t)
                    ->orWhere('s.id_sekolah', 'ilike', $t)
                    ->orWhere('o.booking_code', 'ilike', $t));
            });
    }

    /** Builder baris beserta kolom tampilannya. */
    public function baris()
    {
        return $this->base()->select([
            'oi.id', 'oi.qty', 'oi.is_free', 'oi.tipe_item', 'oi.harga', 'oi.diskon', 'oi.opsi_ukuran',
            'o.id as order_id', 'o.booking_code', 'o.tanggal_booking', 'o.status as order_status',
            DB::raw('coalesce(m.nama, m.name) as marketing_nama'),
            's.id_sekolah', 's.nama as sekolah_nama', 's.alamat as sekolah_alamat',
            DB::raw('coalesce(p.nama, pk.nama) as item_nama'),
            DB::raw('(oi.harga - oi.diskon) * oi.qty as nominal'),
            'o.deleted_at', // query mentah tak kena SoftDeletes, jadi ditandai sendiri
        ]);
    }

    /**
     * Total qty & nominal HANYA dari order yang tidak dihapus — angka uang
     * tidak boleh terpengaruh order yang sudah dibuang ke sampah.
     * `baris` tetap menghitung semuanya (yang terhapus ditandai di tampilan).
     *
     * @return array{baris:int, qty:int, nominal:int}
     */
    public function ringkasan(): array
    {
        $hidup = $this->base()->whereNull('o.deleted_at');

        return [
            'baris' => (int) $this->base()->count(),
            'qty' => (int) (clone $hidup)->sum('oi.qty'),
            'nominal' => (int) (clone $hidup)->sum(DB::raw('(oi.harga - oi.diskon) * oi.qty')),
        ];
    }
}
