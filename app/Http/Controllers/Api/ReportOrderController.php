<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportOrderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API baca-saja Report Order — untuk web report eksternal DMA.
 *
 * Query-nya dibagi dengan halaman Report order (ReportOrderQuery), jadi angka
 * omset di web report selalu sama persis dengan yang dilihat di panel.
 */
class ReportOrderController extends Controller
{
    /** Filter yang sama dengan halaman Report order. */
    private function filter(Request $request): array
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'cabang_id' => ['nullable', 'integer'],
            'produk_id' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'in:berbayar,free'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
        ]);

        return $data;
    }

    /** Ringkasan saja — murah untuk dipanggil berulang (omset realtime). */
    public function ringkasan(Request $request): JsonResponse
    {
        $filter = $this->filter($request);

        return response()->json([
            'filter' => $filter,
            'ringkasan' => (new ReportOrderQuery($filter))->ringkasan(),
            'diambil_pada' => now()->toIso8601String(),
        ]);
    }

    /** Baris detail per item order, berhalaman. */
    public function index(Request $request): JsonResponse
    {
        $filter = $this->filter($request);

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $q = new ReportOrderQuery($filter);

        $rows = $q->baris()
            ->orderByDesc('o.tanggal_booking')
            ->orderBy('oi.id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'filter' => $filter,
            'ringkasan' => $q->ringkasan(),
            'data' => collect($rows->items())->map(fn ($r) => [
                'order_item_id' => (int) $r->id,
                'order_id' => (int) $r->order_id,
                'booking_code' => $r->booking_code,
                'tanggal_booking' => $r->tanggal_booking,
                'order_status' => $r->order_status,
                'order_dihapus' => $r->deleted_at !== null,
                'marketing' => $r->marketing_nama,
                'sekolah' => [
                    'id_sekolah' => $r->id_sekolah,
                    'nama' => $r->sekolah_nama,
                    'alamat' => $r->sekolah_alamat,
                ],
                'item' => [
                    'nama' => $r->item_nama,
                    'tipe' => $r->tipe_item,
                    'opsi' => $r->opsi_ukuran,
                    'is_free' => (bool) $r->is_free,
                ],
                'qty' => (int) $r->qty,
                'harga' => (int) $r->harga,
                'diskon' => (int) $r->diskon,
                'nominal' => (int) $r->nominal,
            ])->all(),
            'meta' => [
                'halaman' => $rows->currentPage(),
                'per_halaman' => $rows->perPage(),
                'total_baris' => $rows->total(),
                'total_halaman' => $rows->lastPage(),
            ],
            'diambil_pada' => now()->toIso8601String(),
        ]);
    }
}
