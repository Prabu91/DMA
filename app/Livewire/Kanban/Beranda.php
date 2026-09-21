<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\Board;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Warna;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Daftar board — halaman pertama setelah login di subdomain kanban. */
#[Layout('layouts.kanban')]
class Beranda extends Component
{
    public bool $bukaBuat = false;

    public string $nama = '';

    public string $warna = 'biru';

    public string $visibilitas = 'workspace';

    public bool $lihatArsip = false;

    public ?string $pesan = null;

    /** Board yang boleh dilihat pengguna, dengan status bintang & keanggotaannya. */
    #[Computed]
    public function board(): Collection
    {
        $user = auth()->user();

        $kunjungan = DB::table('kanban_kunjungan')->where('user_id', $user->id)->pluck('dibuka_at', 'board_id');

        return Board::query()
            ->with(['anggota' => fn ($q) => $q->whereKey($user->id)])
            ->withCount(['kartu' => fn ($q) => $q->whereNull('diarsipkan_at')])
            ->when($this->lihatArsip, fn ($q) => $q->whereNotNull('diarsipkan_at'), fn ($q) => $q->whereNull('diarsipkan_at'))
            ->orderByRaw("case when jenis = 'order' then 0 else 1 end")
            ->orderBy('nama')
            ->get()
            ->filter(fn (Board $b) => Akses::bolehLihat($user, $b))
            ->map(function (Board $b) use ($user, $kunjungan) {
                $baris = $b->anggota->first();
                $b->setAttribute('saya_bintang', (bool) $baris?->pivot?->berbintang);
                $b->setAttribute('saya_anggota', $baris !== null && $baris->pivot->peran !== 'pengamat');
                $b->setAttribute('saya_kelola', Akses::bolehKelola($user, $b));
                $b->setAttribute('dibuka_at', $kunjungan[$b->id] ?? null);

                return $b;
            })
            ->values();
    }

    #[Computed]
    public function kelompok(): array
    {
        $semua = $this->board;

        if ($this->lihatArsip) {
            return [['judul' => 'Board diarsipkan', 'board' => $semua]];
        }

        $baru = $semua->filter(fn ($b) => $b->dibuka_at !== null)
            ->sortByDesc('dibuka_at')->take(4)->values();

        return array_values(array_filter([
            ['judul' => 'Berbintang', 'board' => $semua->where('saya_bintang', true)->values()],
            ['judul' => 'Baru dibuka', 'board' => $baru],
            ['judul' => 'Board Anda', 'board' => $semua->filter(fn ($b) => $b->saya_anggota || $b->isOrder())->values()],
            ['judul' => 'Board lain di workspace', 'board' => $semua->filter(fn ($b) => ! $b->saya_anggota && ! $b->isOrder())->values()],
        ], fn ($k) => $k['board']->isNotEmpty()));
    }

    public function bukaFormBuat(): void
    {
        $this->reset(['nama', 'warna', 'visibilitas']);
        $this->resetErrorBag();
        $this->bukaBuat = true;
    }

    public function buat()
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:120'],
            'warna' => ['required', Rule::in(array_keys(Warna::BOARD))],
            'visibilitas' => ['required', Rule::in(array_keys(Board::VISIBILITAS))],
        ], ['nama.required' => 'Beri nama board.']);

        $board = app(Tata::class)->buatBoard($this->nama, $this->warna, $this->visibilitas, auth()->user());

        return $this->redirect(route('kanban.board', $board), navigate: true);
    }

    public function bintang(int $boardId): void
    {
        $user = auth()->user();
        $board = Board::findOrFail($boardId);
        abort_unless(Akses::bolehLihat($user, $board), 403);

        $baris = $board->anggota()->whereKey($user->id)->first();
        if ($baris) {
            $board->anggota()->updateExistingPivot($user->id, ['berbintang' => ! $baris->pivot->berbintang]);
        } else {
            // Bintang tanpa bergabung: dicatat sebagai pengamat (bukan anggota).
            $board->anggota()->attach($user->id, ['peran' => 'pengamat', 'berbintang' => true]);
        }

        unset($this->board, $this->kelompok);
    }

    public function pulihkan(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        abort_unless(Akses::bolehKelola(auth()->user(), $board), 403);

        $board->update(['diarsipkan_at' => null]);
        $board->catat('board_dipulihkan');
        unset($this->board, $this->kelompok);
    }

    /** Hapus board yang sudah diarsipkan, beserta semua list & kartunya. */
    public function hapus(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        abort_unless(Akses::bolehKelola(auth()->user(), $board), 403);
        abort_if($board->isOrder() || $board->diarsipkan_at === null, 422);

        $nama = $board->nama;
        app(Tata::class)->hapusBoard($board, auth()->user());

        $this->pesan = 'Board "'.$nama.'" dihapus permanen.';
        unset($this->board, $this->kelompok);
    }

    public function render()
    {
        return view('livewire.kanban.beranda')->title('Semua board');
    }
}
