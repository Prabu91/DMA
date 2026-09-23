<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\Board;
use App\Support\Kanban\Akses;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Bilah mengambang di bawah layar (seperti bilah bawah Trello): tombol "Board"
 * untuk kembali ke board yang sedang — atau terakhir — dibuka, dan "Switch
 * boards" untuk lompat ke board lain tanpa mampir ke halaman Semua board.
 */
class BilahApung extends Component
{
    /** Board yang sedang dibuka (diambil dari route), kalau memang di halaman board. */
    public ?int $boardId = null;

    /** Daftar board baru diambil saat panel dibuka, supaya tiap halaman tidak ikut menanggungnya. */
    public bool $buka = false;

    public string $cari = '';

    public function mount(): void
    {
        $param = request()->route('board');

        $this->boardId = $param instanceof Board
            ? $param->id
            : (is_numeric($param) ? (int) $param : null);
    }

    /** Tujuan tombol "Board": board yang sedang dibuka, atau yang terakhir dikunjungi. */
    #[Computed]
    public function boardTujuan(): ?Board
    {
        $user = auth()->user();

        $id = $this->boardId ?: DB::table('kanban_kunjungan')
            ->where('user_id', $user->id)
            ->orderByDesc('dibuka_at')
            ->value('board_id');

        $board = $id ? Board::find($id) : null;

        return $board && Akses::bolehLihat($user, $board) ? $board : null;
    }

    public function togglePanel(): void
    {
        $this->buka = ! $this->buka;

        if (! $this->buka) {
            $this->cari = '';
        }

        unset($this->kelompok);
    }

    public function tutupPanel(): void
    {
        $this->buka = false;
        $this->cari = '';
    }

    /** Board yang boleh dilihat, dikelompokkan seperti halaman Semua board. */
    #[Computed]
    public function kelompok(): array
    {
        if (! $this->buka) {
            return [];
        }

        $user = auth()->user();
        $cari = trim($this->cari);
        $kunjungan = DB::table('kanban_kunjungan')->where('user_id', $user->id)->pluck('dibuka_at', 'board_id');

        $semua = Board::query()
            ->with(['anggota' => fn ($q) => $q->whereKey($user->id)])
            ->whereNull('diarsipkan_at')
            ->when($cari !== '', fn ($q) => $q->where('nama', 'ilike', '%'.$cari.'%'))
            ->orderByRaw("case when jenis = 'order' then 0 else 1 end")
            ->orderBy('nama')
            ->get()
            ->filter(fn (Board $b) => Akses::bolehLihat($user, $b))
            ->map(function (Board $b) use ($kunjungan) {
                $b->setAttribute('saya_bintang', (bool) $b->anggota->first()?->pivot?->berbintang);
                $b->setAttribute('dibuka_at', $kunjungan[$b->id] ?? null);

                return $b;
            })
            ->values();

        if ($cari !== '') {
            return [['judul' => 'Search results', 'board' => $semua->take(12)]];
        }

        $baru = $semua->filter(fn (Board $b) => $b->dibuka_at !== null)
            ->sortByDesc('dibuka_at')->take(4)->values();

        return array_values(array_filter([
            ['judul' => 'Starred', 'board' => $semua->where('saya_bintang', true)->values()],
            ['judul' => 'Recently opened', 'board' => $baru],
            ['judul' => 'All boards', 'board' => $semua->take(12)],
        ], fn ($k) => $k['board']->isNotEmpty()));
    }

    public function render()
    {
        return view('livewire.kanban.bilah-apung');
    }
}
