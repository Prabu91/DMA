<?php

namespace App\Livewire\Kanban;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Lonceng notifikasi di kepala halaman kanban. */
class Lonceng extends Component
{
    public bool $hanyaBelumDibaca = false;

    #[Computed]
    public function kabar(): Collection
    {
        return auth()->user()->notifications()
            ->when($this->hanyaBelumDibaca, fn ($q) => $q->whereNull('read_at'))
            ->limit(25)->get();
    }

    #[Computed]
    public function belumDibaca(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function baca(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->update(['read_at' => now()]);
        $this->segarkan();
    }

    public function bacaSemua(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->segarkan();
    }

    private function segarkan(): void
    {
        unset($this->kabar, $this->belumDibaca);
    }

    public function render()
    {
        return view('livewire.kanban.lonceng');
    }
}
