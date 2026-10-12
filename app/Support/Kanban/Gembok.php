<?php

namespace App\Support\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Kunci;
use App\Models\Kanban\KunciAkses;
use App\Models\User;

/**
 * Kunci board / list / kartu.
 *
 * Aturannya:
 * - Yang terkunci tetap bisa dibuka dan dibaca. Yang dibatasi hanya mengubahnya
 *   (pindah, ganti judul/deskripsi, arsip, label, anggota, tanggal). Komentar,
 *   lampiran, dan centang checklist sengaja tetap terbuka supaya tim produksi
 *   tetap bisa bekerja di kartu yang dikunci.
 * - Kunci paling spesifik yang menang: kartu mengalahkan list, list mengalahkan
 *   board. Jadi satu board boleh dikunci menyeluruh, lalu satu list di dalamnya
 *   diberi aturan sendiri.
 * - Admin pusat selalu bisa menembus, supaya kartu tidak pernah tersandera
 *   kalau orang yang diberi akses sedang tidak ada.
 *
 * Dipakai sebagai singleton supaya kuncinya cukup diambil sekali per permintaan;
 * papan dengan ratusan kartu tidak boleh memicu satu query per kartu.
 */
final class Gembok
{
    /** @var array<string, array<int, Kunci>> */
    private array $kunci = [];

    /** @var array<int, true> */
    private array $sudahDimuat = [];

    public static function ini(): self
    {
        return app(self::class);
    }

    /** Buang simpanan — dipanggil setelah kunci berubah. */
    public function lupakan(): void
    {
        $this->kunci = [];
        $this->sudahDimuat = [];
    }

    // ---------------- Mencari kunci yang berlaku ----------------

    public function untukBoard(Board|int $board): ?Kunci
    {
        $id = $board instanceof Board ? (int) $board->id : $board;
        $this->muat($id);

        return $this->kunci['board'][$id] ?? null;
    }

    public function untukKolom(Kolom $kolom): ?Kunci
    {
        $this->muat((int) $kolom->board_id);

        return $this->kunci['kolom'][(int) $kolom->id]
            ?? $this->kunci['board'][(int) $kolom->board_id]
            ?? null;
    }

    public function untukKartu(Kartu $kartu): ?Kunci
    {
        $this->muat((int) $kartu->board_id);

        return $this->kunci['kartu'][(int) $kartu->id]
            ?? $this->kunci['kolom'][(int) $kartu->kolom_id]
            ?? $this->kunci['board'][(int) $kartu->board_id]
            ?? null;
    }

    // ---------------- Boleh mengubah? ----------------

    public function bolehUbahBoard(?User $user, Board|int $board): bool
    {
        return $this->bolehTembus($user, $this->untukBoard($board));
    }

    public function bolehUbahKolom(?User $user, Kolom $kolom): bool
    {
        return $this->bolehTembus($user, $this->untukKolom($kolom));
    }

    public function bolehUbahKartu(?User $user, Kartu $kartu): bool
    {
        return $this->bolehTembus($user, $this->untukKartu($kartu));
    }

    /** Tidak terkunci, admin pusat, atau namanya/perannya ada di daftar akses. */
    public function bolehTembus(?User $user, ?Kunci $kunci): bool
    {
        if ($kunci === null) {
            return true;
        }
        if (! $user instanceof User) {
            return false;
        }
        if (Akses::admin($user)) {
            return true;
        }

        return $kunci->akses->contains(function (KunciAkses $a) use ($user) {
            if ($a->user_id !== null) {
                return (int) $a->user_id === (int) $user->id;
            }

            return $a->peran !== null && $user->hasRole($a->peran);
        });
    }

    // ---------------- Memasang & melepas ----------------

    /**
     * Pasang kunci (atau perbarui daftar aksesnya). Daftar akses ditulis ulang
     * seluruhnya supaya tidak ada sisa baris lama yang diam-diam masih berlaku.
     *
     * @param  array<int, int|string>  $userId
     * @param  array<int, string>  $peran
     */
    public function pasang(string $jenis, int $targetId, array $userId, array $peran, User $oleh): Kunci
    {
        abort_unless(in_array($jenis, Kunci::JENIS, true), 422);

        $kunci = Kunci::firstOrCreate(
            ['jenis' => $jenis, 'target_id' => $targetId],
            ['user_id' => $oleh->id],
        );

        $kunci->akses()->delete();

        foreach (array_unique(array_map('intval', $userId)) as $id) {
            if ($id > 0) {
                $kunci->akses()->create(['user_id' => $id]);
            }
        }
        foreach (array_unique($peran) as $satu) {
            if (in_array($satu, Akses::PERAN_STAF, true)) {
                $kunci->akses()->create(['peran' => $satu]);
            }
        }

        $this->lupakan();

        return $kunci->load('akses.pengguna:id,nama,name');
    }

    public function lepas(string $jenis, int $targetId): void
    {
        Kunci::where('jenis', $jenis)->where('target_id', $targetId)->get()->each->delete();
        $this->lupakan();
    }

    // ---------------- Pemuatan ----------------

    /** Ambil semua kunci milik satu board sekaligus: board, list-nya, dan kartunya. */
    private function muat(int $boardId): void
    {
        if (isset($this->sudahDimuat[$boardId])) {
            return;
        }
        $this->sudahDimuat[$boardId] = true;

        $baris = Kunci::query()
            ->with(['akses.pengguna:id,nama,name', 'pengunci:id,nama,name'])
            ->where(fn ($q) => $q->where('jenis', 'board')->where('target_id', $boardId))
            ->orWhere(fn ($q) => $q->where('jenis', 'kolom')
                ->whereIn('target_id', Kolom::where('board_id', $boardId)->select('id')))
            ->orWhere(fn ($q) => $q->where('jenis', 'kartu')
                ->whereIn('target_id', Kartu::where('board_id', $boardId)->select('id')))
            ->get();

        foreach ($baris as $k) {
            $this->kunci[$k->jenis][(int) $k->target_id] = $k;
        }
    }
}
