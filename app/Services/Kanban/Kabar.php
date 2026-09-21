<?php

namespace App\Services\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\User;
use App\Notifications\KanbanKabar;
use App\Support\Kanban\Akses;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Mengabari orang tentang kartu: lonceng di aplikasi + email, seperti Trello.
 *
 * Yang dikabari adalah pengikut kartu. Orang otomatis mengikuti kartu ketika
 * membuatnya, ditugaskan, berkomentar, atau disebut (@nama).
 */
class Kabar
{
    public function ikut(Kartu $kartu, int|User $user): void
    {
        $kartu->pengikut()->syncWithoutDetaching([$this->id($user) => ['created_at' => now()]]);
    }

    public function berhentiIkut(Kartu $kartu, int|User $user): void
    {
        $kartu->pengikut()->detach($this->id($user));
    }

    public function mengikuti(Kartu $kartu, int|User $user): bool
    {
        return $kartu->pengikut()->whereKey($this->id($user))->exists();
    }

    /** Komentar baru: kabari pengikut, dan sebutan @nama dikabari terpisah. */
    public function komentar(Kartu $kartu, string $isi, User $oleh): void
    {
        $this->ikut($kartu, $oleh);
        $disebut = $this->sebutan($isi, $kartu->board);

        foreach ($disebut as $user) {
            $this->ikut($kartu, $user);
        }

        $this->kirim($disebut, new KanbanKabar(KanbanKabar::SEBUT, $kartu, $oleh, $isi), $oleh);
        $this->kirim(
            $this->pengikutLain($kartu, $oleh, $disebut->pluck('id')->all()),
            new KanbanKabar(KanbanKabar::KOMENTAR, $kartu, $oleh, $isi),
            $oleh,
        );
    }

    /** Sebutan di deskripsi kartu (tanpa mengabari seluruh pengikut). */
    public function deskripsi(Kartu $kartu, string $isi, User $oleh): void
    {
        $disebut = $this->sebutan($isi, $kartu->board);
        foreach ($disebut as $user) {
            $this->ikut($kartu, $user);
        }

        $this->kirim($disebut, new KanbanKabar(KanbanKabar::SEBUT, $kartu, $oleh, $isi), $oleh);
    }

    public function ditugaskan(Kartu $kartu, User $target, User $oleh): void
    {
        $this->ikut($kartu, $target);
        $this->kirim(collect([$target]), new KanbanKabar(KanbanKabar::DITUGASKAN, $kartu, $oleh), $oleh);
    }

    /** Ditugaskan mengerjakan satu item checklist. */
    public function ditugaskanItem(Kartu $kartu, User $target, User $oleh, string $item): void
    {
        $this->ikut($kartu, $target);
        $this->kirim(collect([$target]), new KanbanKabar(KanbanKabar::DITUGASKAN, $kartu, $oleh, 'Item checklist: '.$item), $oleh);
    }

    /** Perubahan kartu yang layak dikabarkan ke pengikut (pindah list, diarsipkan). */
    public function perubahan(Kartu $kartu, string $jenis, User $oleh, ?string $cuplikan = null): void
    {
        $this->kirim($this->pengikutLain($kartu, $oleh), new KanbanKabar($jenis, $kartu, $oleh, $cuplikan), $oleh);
    }

    public function tenggat(Kartu $kartu): int
    {
        $pengikut = $kartu->pengikut()->get()->merge($kartu->anggota()->get())->unique('id');
        $this->kirim($pengikut, new KanbanKabar(KanbanKabar::TENGGAT, $kartu, null, $kartu->tenggat_pada?->translatedFormat('j M Y, H:i')));

        return $pengikut->count();
    }

    /**
     * Cari sebutan "@Nama" di teks. Nama dicocokkan dengan nama anggota board
     * (board order: semua staf), termasuk versi tanpa spasi seperti @ShantyDwi.
     *
     * @return Collection<int, User>
     */
    public function sebutan(string $teks, ?Board $board): Collection
    {
        if ($board === null || ! str_contains($teks, '@')) {
            return collect();
        }

        return $this->calon($board)
            ->filter(fn (User $u) => $this->disebut($teks, $u))
            ->values();
    }

    private function disebut(string $teks, User $user): bool
    {
        foreach (array_unique(array_filter([$user->nama, $user->name])) as $nama) {
            foreach ([$nama, str_replace(' ', '', $nama)] as $bentuk) {
                // Sesudah nama harus batas kata, supaya @Rina tidak cocok dengan @Rinawati.
                if (preg_match('/(?<![\w@])@'.preg_quote($bentuk, '/').'(?![\p{L}\p{N}])/iu', $teks)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Orang yang boleh disebut di board ini. */
    public function calon(Board $board): Collection
    {
        return $board->isOrder()
            ? User::role(Akses::PERAN_STAF)->orderByRaw('coalesce(nama, name)')->get(['id', 'nama', 'name', 'email'])
            : $board->anggota()->wherePivot('peran', '!=', 'pengamat')->orderByRaw('coalesce(nama, name)')->get(['users.id', 'nama', 'name', 'email']);
    }

    /** @return Collection<int, User> */
    private function pengikutLain(Kartu $kartu, ?User $kecuali, array $kecualiId = []): Collection
    {
        return $kartu->pengikut()
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
            ->when($kecualiId, fn ($q) => $q->whereIntegerNotInRaw('users.id', $kecualiId))
            ->get();
    }

    /**
     * Kirim kabar. Lonceng ditulis lebih dulu, jadi gangguan SMTP tidak
     * menghilangkan notifikasi — kegagalan email hanya dicatat di log.
     */
    private function kirim(Collection $penerima, KanbanKabar $kabar, ?User $oleh = null): void
    {
        foreach ($penerima as $user) {
            if ($oleh && (int) $user->id === (int) $oleh->id) {
                continue;
            }

            try {
                $user->notify($kabar);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function id(int|User $user): int
    {
        return $user instanceof User ? $user->id : $user;
    }
}
