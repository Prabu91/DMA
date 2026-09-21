<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Kanban\Lampiran;
use App\Support\Kanban\Akses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lampiran kartu disimpan di disk privat; hanya yang boleh melihat board yang bisa membukanya. */
class LampiranController extends Controller
{
    public function __invoke(Request $request, Lampiran $lampiran): StreamedResponse|RedirectResponse
    {
        $board = $lampiran->kartu?->board;
        abort_unless($board && Akses::bolehLihat($request->user(), $board), 403);

        // Lampiran tautan tidak punya berkas: cukup diarahkan ke alamatnya.
        if ($lampiran->isTautan()) {
            return redirect()->away($lampiran->url);
        }
        abort_unless(Storage::disk('local')->exists($lampiran->path), 404);

        $disposisi = $request->boolean('unduh') || ! $lampiran->isGambar() && $lampiran->mime !== 'application/pdf'
            ? 'attachment'
            : 'inline';

        return Storage::disk('local')->response($lampiran->path, $lampiran->nama, [
            'Content-Type' => $lampiran->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposisi);
    }
}
