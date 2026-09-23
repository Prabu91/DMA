<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Support\Kanban\Akses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Kartu saya" sekaligus pencarian lintas board — padanan halaman Cards &
 * Search di Trello. Hanya kartu di board yang boleh dilihat yang muncul.
 */
#[Layout('layouts.kanban')]
class KartuSaya extends Component
{
    use WithPagination;

    public const TAB = [
        'saya' => 'Assigned to me',
        'ikuti' => 'Following',
        'semua' => 'All cards',
    ];

    #[Url]
    public string $tab = 'saya';

    #[Url(as: 'q')]
    public string $cari = '';

    #[Url(as: 'board')]
    public ?int $boardId = null;

    #[Url(as: 'tenggat')]
    public string $saringTenggat = '';

    #[Url(as: 'arsip')]
    public bool $termasukArsip = false;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'saya';
        }
    }

    /** Board yang boleh dilihat pengguna ini. */
    #[Computed]
    public function boardTerlihat(): Collection
    {
        $user = auth()->user();

        return Board::query()->aktif()->orderBy('nama')->get()
            ->filter(fn (Board $b) => Akses::bolehLihat($user, $b))
            ->values();
    }

    #[Computed]
    public function hasil()
    {
        $user = auth()->user();

        return Kartu::query()
            ->whereIn('board_id', $this->boardTerlihat->pluck('id'))
            ->when($this->boardId, fn ($q) => $q->where('board_id', $this->boardId))
            ->unless($this->termasukArsip, fn ($q) => $q->whereNull('diarsipkan_at'))
            ->when($this->tab === 'saya', fn ($q) => $q->whereHas('anggota', fn ($a) => $a->whereKey($user->id)))
            ->when($this->tab === 'ikuti', fn ($q) => $q->whereHas('pengikut', fn ($a) => $a->whereKey($user->id)))
            ->when(trim($this->cari) !== '', function (Builder $q) {
                $kata = trim($this->cari);
                $suka = '%'.$kata.'%';
                // "#123" mencari nomor kartu; selebihnya judul & deskripsi.
                $q->where(function ($w) use ($kata, $suka) {
                    $w->where('judul', 'ilike', $suka)->orWhere('deskripsi', 'ilike', $suka);
                    if (preg_match('/^#?(\d+)$/', $kata, $cocok)) {
                        $w->orWhere('kanban_kartu.id', (int) $cocok[1]);
                    }
                });
            })
            ->when($this->saringTenggat === 'lewat', fn ($q) => $q->whereNull('tenggat_selesai_at')->whereNotNull('tenggat_pada')->where('tenggat_pada', '<', now()))
            ->when($this->saringTenggat === 'minggu', fn ($q) => $q->whereNull('tenggat_selesai_at')->whereBetween('tenggat_pada', [now(), now()->addWeek()]))
            ->when($this->saringTenggat === 'tanpa', fn ($q) => $q->whereNull('tenggat_pada'))
            ->with(['board:id,nama,warna', 'kolom:id,nama', 'label', 'anggota:id,nama,name'])
            ->orderByRaw('tenggat_pada is null')
            ->orderBy('tenggat_pada')
            ->latest('kanban_kartu.updated_at')
            ->paginate(20);
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, ['tab', 'cari', 'boardId', 'saringTenggat', 'termasukArsip'], true)) {
            $this->resetPage();
        }
    }

    public function gantiTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TAB) ? $tab : 'saya';
        $this->resetPage();
    }

    public function bersihkan(): void
    {
        $this->reset(['cari', 'boardId', 'saringTenggat', 'termasukArsip']);
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.kanban.kartu-saya')->title('My cards');
    }
}
