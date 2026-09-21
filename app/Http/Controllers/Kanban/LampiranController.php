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
        // ?kecil=1 dipakai sampul kartu & daftar lampiran supaya tidak mengunduh berkas asli.
        $berkas = $request->boolean('kecil') ? $lampiran->pathKecil() : $lampiran->path;
        abort_unless($berkas && Storage::disk('local')->exists($berkas), 404);

        $disposisi = $request->boolean('unduh') || ! $lampiran->isGambar() && $lampiran->mime !== 'application/pdf'
            ? 'attachment'
            : 'inline';

        return Storage::disk('local')->response($berkas, $lampiran->nama, [
            'Content-Type' => $lampiran->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            // Berkas lampiran tidak pernah berubah isinya, jadi aman disimpan peramban.
            'Cache-Control' => 'private, max-age=604800',
        ], $disposisi);
    }
}
