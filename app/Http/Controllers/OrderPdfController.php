<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OrderPdfController extends Controller
{
    /** Jalur staf: CabangScope membatasi ke cabang staf. */
    public function staf(int $id): Response
    {
        return $this->render(Order::findOrFail($id));
    }

    /** Jalur sekolah: isolasi eksplisit per sekolah_id. */
    public function sekolah(int $id): Response
    {
        $order = Order::where('sekolah_id', Auth::guard('sekolah')->id())->findOrFail($id);

        return $this->render($order);
    }

    private function render(Order $order): Response
    {
        $order->load(['items.produk', 'items.paket', 'items.desain', 'sekolah', 'cabang', 'marketing']);

        $pdf = Pdf::loadView('pdf.order', ['order' => $order])->setPaper('a4');

        return $pdf->stream('booking-'.($order->booking_code ?? $order->id).'.pdf');
    }

    /** Surat Tugas Event (STE) — staf. Detail order + sekolah + tim event. */
    public function ste(int $id): Response
    {
        $order = Order::with(['sekolah', 'cabang', 'marketing', 'timEvent', 'items.produk', 'items.paket'])
            ->findOrFail($id); // CabangScope membatasi ke cabang staf

        $pdf = Pdf::loadView('pdf.ste', ['order' => $order])->setPaper('a4');

        return $pdf->stream('ste-'.($order->booking_code ?? $order->id).'.pdf');
    }
}
