<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Kanban\CoverMarketing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sajikan thumbnail marketing untuk cover kartu di board. */
class CoverMarketingController extends Controller
{
    public function __invoke(Request $request, User $user): StreamedResponse
    {
        $cover = app(CoverMarketing::class);
        abort_unless($cover->punyaCover($user), 404);

        $disk = Storage::disk('local');
        $path = (string) $user->kanban_cover_path;

        return $disk->response($path, $cover->nama($user), [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
            // Gambar jarang berubah; URL-nya membawa cap, jadi aman disimpan lama.
            'Cache-Control' => 'max-age=604800, private',
        ]);
    }
}
