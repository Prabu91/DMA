<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Redaksi;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Unduh REDAKSI.txt — pengganti lampiran REDAKSI.txt yang di Trello diketik
 * manual per kartu. Isinya selalu redaksi terbaru dari order.
 */
class OrderRedaksiController extends Controller
{
    public function __invoke(int $id): Response
    {
        $order = Order::with('sekolah')->findOrFail($id); // CabangScope membatasi ke cabang staf
        Gate::authorize('view', $order);

        $nama = 'REDAKSI_'.Str::of($order->booking_code ?? 'order-'.$order->id)->replaceMatches('/[^A-Za-z0-9_-]+/', '-').'.txt';

        // CRLF + BOM supaya terbaca rapi di Notepad Windows yang dipakai editor.
        $isi = "\u{FEFF}".str_replace("\n", "\r\n", Redaksi::untuk($order))."\r\n";

        return response($isi, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }
}
