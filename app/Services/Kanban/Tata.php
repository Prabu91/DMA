<?php

namespace App\Services\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Label;
use App\Models\Kanban\Lampiran;
use App\Models\User;
use App\Support\Kanban\Posisi;
use App\Support\Kanban\Warna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Operasi susunan kanban: membuat board/kolom/kartu dan memindah-urutkan
 * mereka. Semua perpindahan lewat sini supaya urutan & riwayat aktivitas
 * konsisten, dari mana pun asalnya (seret, menu "Pindahkan", sinkron order).
 */
class Tata
{
    public function buatBoard(string $nama, string $warna, string $visibilitas, User $oleh): Board
    {
        return DB::transaction(function () use ($nama, $warna, $visibilitas, $oleh) {
            $board = Board::create([
                'nama' => trim($nama),
                'warna' => array_key_exists($warna, Warna::BOARD) ? $warna : 'biru',
                'visibilitas' => $visibilitas === 'privat' ? 'privat' : 'workspace',
                'jenis' => Board::JENIS_BEBAS,
                'dibuat_oleh' => $oleh->id,
            ]);
            $board->anggota()->attach($oleh->id, ['peran' => 'admin']);

            foreach (Warna::LABEL_AWAL as $w) {
                Label::create(['board_id' => $board->id, 'warna' => $w]);
            }

            $board->catat('board_dibuat', $board->nama, null, $oleh->id);

            return $board;
        });
    }

    public function tambahKolom(Board $board, string $nama, User $oleh, ?int $marketingId = null): Kolom
    {
        $akhir = (float) $board->semuaKolom()->whereNull('diarsipkan_at')->max('posisi');

        $kolom = Kolom::create([
            'board_id' => $board->id,
            'nama' => trim($nama),
            'posisi' => $akhir + Posisi::JARAK,
            'marketing_id' => $marketingId,
        ]);
        $board->catat('kolom_dibuat', $kolom->nama, null, $oleh->id);

        return $kolom;
    }

    public function tambahKartu(Kolom $kolom, string $judul, ?User $oleh, array $isian = []): Kartu
    {
        $akhir = (float) Kartu::where('kolom_id', $kolom->id)->whereNull('diarsipkan_at')->max('posisi');

        $kartu = Kartu::create(array_merge([
            'board_id' => $kolom->board_id,
            'kolom_id' => $kolom->id,
            'posisi' => $akhir + Posisi::JARAK,
            'judul' => mb_substr(trim($judul), 0, 255),
            'dibuat_oleh' => $oleh?->id,
        ], $isian));

        if ($oleh) {
            // Pembuat kartu otomatis mengikutinya, seperti di Trello.
            app(Kabar::class)->ikut($kartu, $oleh);
        }

        $kolom->board->catat('kartu_dibuat', 'ke list '.$kolom->nama, $kartu, $oleh?->id);

        return $kartu;
    }

    /**
     * Salin kartu ke sebuah list (padanan "Copy card" Trello). Yang ikut
     * disalin ditentukan $bawa: label, anggota, checklist, lampiran.
     * Komentar, riwayat, dan kaitan order tidak pernah ikut.
     */
    public function salinKartu(Kartu $asal, Kolom $tujuan, string $judul, array $bawa, User $oleh): Kartu
    {
        return DB::transaction(function () use ($asal, $tujuan, $judul, $bawa, $oleh) {
            $salinan = $this->tambahKartu($tujuan, $judul, $oleh, [
                'deskripsi' => $asal->deskripsi,
                'mulai_pada' => $asal->mulai_pada,
                'tenggat_pada' => $asal->tenggat_pada,
                'cover_warna' => $asal->cover_warna,
            ]);

            $seBoard = (int) $asal->board_id === (int) $tujuan->board_id;

            if (in_array('label', $bawa, true) && $seBoard) {
                $salinan->label()->attach($asal->label()->pluck('kanban_label.id')->all());
            }

            if (in_array('anggota', $bawa, true)) {
                $salinan->anggota()->attach($asal->anggota()->pluck('users.id')->all());
            }

            if (in_array('checklist', $bawa, true)) {
                foreach ($asal->checklist()->with('item')->get() as $checklist) {
                    $baru = Checklist::create([
                        'kartu_id' => $salinan->id,
                        'judul' => $checklist->judul,
                        'posisi' => $checklist->posisi,
                    ]);
                    foreach ($checklist->item as $item) {
                        ChecklistItem::create([
                            'checklist_id' => $baru->id,
                            'teks' => $item->teks,
                            'posisi' => $item->posisi,
                            'user_id' => $item->user_id,
                            'tenggat_pada' => $item->tenggat_pada,
                        ]);
                    }
                }
            }

            if (in_array('lampiran', $bawa, true)) {
                $this->salinLampiran($asal, $salinan, $oleh);
            }

            $tujuan->board->catat('kartu_disalin', 'dari '.$asal->judul, $salinan, $oleh->id);

            return $salinan->fresh();
        });
    }

    private function salinLampiran(Kartu $asal, Kartu $salinan, User $oleh): void
    {
        foreach ($asal->lampiran()->get() as $lampiran) {
            $path = 'kanban/'.$salinan->board_id.'/'.$salinan->id.'/'.Str::random(40).'.'.pathinfo($lampiran->path, PATHINFO_EXTENSION);

            if (! Storage::disk('local')->exists($lampiran->path) || ! Storage::disk('local')->copy($lampiran->path, $path)) {
                continue;
            }

            $baru = Lampiran::create([
                'kartu_id' => $salinan->id,
                'user_id' => $oleh->id,
                'nama' => $lampiran->nama,
                'path' => $path,
                'mime' => $lampiran->mime,
                'ukuran' => $lampiran->ukuran,
            ]);

            if ((int) $asal->cover_lampiran_id === (int) $lampiran->id) {
                $salinan->update(['cover_lampiran_id' => $baru->id]);
            }
        }
    }

    /** Salin satu list beserta kartunya (padanan "Copy list"). */
    public function salinKolom(Kolom $asal, string $nama, User $oleh): Kolom
    {
        return DB::transaction(function () use ($asal, $nama, $oleh) {
            $board = $asal->board;
            $salinan = $this->tambahKolom($board, $nama, $oleh);

            foreach ($asal->kartu()->get() as $kartu) {
                // Kartu order tidak digandakan: satu order hanya boleh punya satu kartu.
                if ($kartu->order_id) {
                    continue;
                }
                $this->salinKartu($kartu, $salinan, $kartu->judul, ['label', 'anggota', 'checklist'], $oleh);
            }

            $board->catat('kolom_disalin', $asal->nama.' ke '.$salinan->nama, null, $oleh->id);

            return $salinan->fresh();
        });
    }

    /** Salin board: list selalu ikut, kartunya opsional (padanan "Copy board"). */
    public function salinBoard(Board $asal, string $nama, bool $denganKartu, User $oleh): Board
    {
        return DB::transaction(function () use ($asal, $nama, $denganKartu, $oleh) {
            $board = Board::create([
                'nama' => trim($nama),
                'warna' => $asal->warna,
                'visibilitas' => $asal->visibilitas,
                'jenis' => Board::JENIS_BEBAS,
                'dibuat_oleh' => $oleh->id,
            ]);
            $board->anggota()->attach($oleh->id, ['peran' => 'admin']);

            $petaLabel = [];
            foreach ($asal->label()->get() as $label) {
                $petaLabel[$label->id] = Label::create([
                    'board_id' => $board->id,
                    'nama' => $label->nama,
                    'warna' => $label->warna,
                ])->id;
            }

            foreach ($asal->kolom()->get() as $kolom) {
                $baru = Kolom::create([
                    'board_id' => $board->id,
                    'nama' => $kolom->nama,
                    'posisi' => $kolom->posisi,
                    'warna' => $kolom->warna,
                ]);

                if (! $denganKartu) {
                    continue;
                }

                foreach ($kolom->kartu()->get() as $kartu) {
                    if ($kartu->order_id) {
                        continue;
                    }
                    $salinan = $this->salinKartu($kartu, $baru, $kartu->judul, ['anggota', 'checklist'], $oleh);
                    $salinan->update(['templat' => $kartu->templat]);
                    $salinan->label()->attach(
                        $kartu->label()->pluck('kanban_label.id')->map(fn ($id) => $petaLabel[$id] ?? null)->filter()->all()
                    );
                }
            }

            $board->catat('board_disalin', 'dari '.$asal->nama, null, $oleh->id);

            return $board->fresh();
        });
    }

    /**
     * Pindahkan kartu ke $tujuan (boleh kolom yang sama atau board lain) pada
     * urutan $indeks (0 = paling atas).
     */
    public function pindahKartu(Kartu $kartu, Kolom $tujuan, int $indeks, User $oleh): void
    {
        DB::transaction(function () use ($kartu, $tujuan, $indeks, $oleh) {
            $asal = $kartu->kolom;
            $posisi = $this->posisiKartu($tujuan, $kartu->id, $indeks);

            $pindahBoard = (int) $kartu->board_id !== (int) $tujuan->board_id;
            $kartu->update([
                'kolom_id' => $tujuan->id,
                'board_id' => $tujuan->board_id,
                'posisi' => $posisi,
            ]);

            if ($pindahBoard) {
                // Label milik board lama tidak berlaku di board baru.
                $kartu->label()->detach();
                $tujuan->board->catat('kartu_pindah', 'dari board '.$asal?->board?->nama.' ke list '.$tujuan->nama, $kartu, $oleh->id);
            } elseif ((int) $asal?->id !== (int) $tujuan->id) {
                $tujuan->board->catat('kartu_pindah', 'dari list '.$asal?->nama.' ke list '.$tujuan->nama, $kartu, $oleh->id);
            }
        });
    }

    public function pindahKolom(Kolom $kolom, int $indeks, User $oleh): void
    {
        $lain = Kolom::where('board_id', $kolom->board_id)->whereNull('diarsipkan_at')
            ->whereKeyNot($kolom->id)->orderBy('posisi')->pluck('posisi')->all();

        $posisi = Posisi::untukIndeks($lain, $indeks);
        if ($posisi === null) {
            $this->nomoriUlangKolom($kolom->board_id);
            $lain = Kolom::where('board_id', $kolom->board_id)->whereNull('diarsipkan_at')
                ->whereKeyNot($kolom->id)->orderBy('posisi')->pluck('posisi')->all();
            $posisi = Posisi::untukIndeks($lain, $indeks);
        }

        $kolom->update(['posisi' => $posisi]);
    }

    private function posisiKartu(Kolom $tujuan, int $kecualiId, int $indeks): float
    {
        $ambil = fn () => Kartu::where('kolom_id', $tujuan->id)->whereNull('diarsipkan_at')
            ->whereKeyNot($kecualiId)->orderBy('posisi')->pluck('posisi')->all();

        $posisi = Posisi::untukIndeks($ambil(), $indeks);
        if ($posisi === null) {
            $this->nomoriUlangKartu($tujuan->id);
            $posisi = Posisi::untukIndeks($ambil(), $indeks);
        }

        return $posisi;
    }

    private function nomoriUlangKartu(int $kolomId): void
    {
        $ids = Kartu::where('kolom_id', $kolomId)->whereNull('diarsipkan_at')->orderBy('posisi')->pluck('id')->all();
        foreach ($ids as $i => $id) {
            Kartu::whereKey($id)->update(['posisi' => ($i + 1) * Posisi::JARAK]);
        }
    }

    private function nomoriUlangKolom(int $boardId): void
    {
        $ids = Kolom::where('board_id', $boardId)->whereNull('diarsipkan_at')->orderBy('posisi')->pluck('id')->all();
        foreach ($ids as $i => $id) {
            Kolom::whereKey($id)->update(['posisi' => ($i + 1) * Posisi::JARAK]);
        }
    }
}
