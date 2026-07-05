<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Tandai event sebuah order sebagai selesai.
     *
     * Order sudah ter-scope CabangScope (route-model binding hanya menemukan
     * order di cabang user, kecuali super_admin/operasional). Selain itu,
     * pelaku harus anggota tim event order tersebut atau melihat semua cabang.
     */
    public function complete(Request $request, Order $order): RedirectResponse
    {
        $user = $request->user();

        $boleh = $user->seesAllCabang()
            || $order->timEvent()->whereKey($user->id)->exists();

        abort_unless($boleh, 403);

        $order->update(['event_status' => OrderStatus::EVENT_SELESAI]);

        return back()->with('status', 'event-selesai');
    }
}
