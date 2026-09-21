<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Kanban\Board;
use App\Support\Kanban\Akses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Latar board disimpan di disk privat; dilayani lewat controller agar tetap terkunci akses. */
class LatarBoardController extends Controller
{
    public function __invoke(Request $request, Board $board): StreamedResponse
    {
        abort_unless(Akses::bolehLihat($request->user(), $board), 403);
        abort_unless($board->latar_path && Storage::disk('local')->exists($board->latar_path), 404);

        return Storage::disk('local')->response($board->latar_path, 'latar', [
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
