<?php

namespace App\Http\Controllers\Kanban;

use App\Http\Controllers\Controller;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Warna;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh isi board sebagai CSV — padanan "Export" Trello, untuk arsip & Excel. */
class EksporBoardController extends Controller
{
    public function __invoke(Request $request, Board $board): StreamedResponse
    {
        abort_unless(Akses::bolehLihat($request->user(), $board), 403);

        $arsip = $request->boolean('arsip');
        $nama = 'board-'.$board->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($board, $arsip) {
            $keluar = fopen('php://output', 'wb');
            // BOM supaya Excel membaca huruf beraksen & emoji dengan benar.
            fwrite($keluar, "\xEF\xBB\xBF");

            $bidang = $board->bidang()->get();

            fputcsv($keluar, array_merge([
                'List', 'Kartu', 'Deskripsi', 'Label', 'Anggota', 'Mulai', 'Tenggat',
                'Tenggat selesai', 'Checklist selesai', 'Checklist total', 'Komentar',
                'Lampiran', 'Kode order', 'Diarsipkan', 'Dibuat',
            ], $bidang->pluck('nama')->all()));

            Kartu::query()
                ->where('board_id', $board->id)
                ->unless($arsip, fn ($q) => $q->whereNull('diarsipkan_at'))
                ->with(['kolom:id,nama,posisi', 'label', 'anggota:id,nama,name', 'order:id,booking_code', 'bidangNilai'])
                ->withCount(['komentar', 'lampiran', 'checklistItem', 'checklistItem as checklist_selesai_count' => fn ($q) => $q->whereNotNull('selesai_at')])
                ->orderBy('kolom_id')->orderBy('posisi')
                ->chunk(200, function ($kartu) use ($keluar, $bidang) {
                    foreach ($kartu as $k) {
                        $isiBidang = $k->bidangNilai->pluck('nilai', 'bidang_id');

                        fputcsv($keluar, array_merge([
                            $k->kolom?->nama,
                            $k->judul,
                            $k->deskripsi,
                            $k->label->map(fn ($l) => $l->nama ?: Warna::namaLabel($l->warna))->join(', '),
                            $k->anggota->map(fn ($a) => $a->nama ?? $a->name)->join(', '),
                            $k->mulai_pada?->format('Y-m-d'),
                            $k->tenggat_pada?->format('Y-m-d H:i'),
                            $k->tenggat_selesai_at?->format('Y-m-d H:i'),
                            $k->checklist_selesai_count,
                            $k->checklist_item_count,
                            $k->komentar_count,
                            $k->lampiran_count,
                            $k->order?->booking_code,
                            $k->diarsipkan_at?->format('Y-m-d H:i'),
                            $k->created_at?->format('Y-m-d H:i'),
                        ], $bidang->map(fn ($b) => $b->tampilkan($isiBidang[$b->id] ?? null))->all()));
                    }
                });

            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
