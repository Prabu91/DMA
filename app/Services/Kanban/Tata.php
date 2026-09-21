<?php

namespace App\Services\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Label;
use App\Models\User;
use App\Support\Kanban\Posisi;
use App\Support\Kanban\Warna;
use Illuminate\Support\Facades\DB;

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
