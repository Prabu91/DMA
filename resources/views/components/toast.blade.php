@props(['success' => null, 'error' => null])

{{--
    Notifikasi melayang untuk komponen Livewire yang punya properti $success / $error.
    Dipakai menggantikan banner di puncak halaman, yang tidak terlihat kalau
    pengguna sedang menggulir daftar panjang (mis. menghapus baris ke-40).

    Tampil/tidaknya murni ditentukan state Livewire, bukan state Alpine — supaya
    pesan yang sama bisa muncul lagi kalau tindakannya diulang.
--}}
<div class="pointer-events-none fixed inset-x-0 bottom-0 z-[80] flex flex-col items-center gap-2 px-4 pb-4 sm:inset-x-auto sm:right-4 sm:items-end">
    @if ($success)
        <div wire:key="toast-sukses" x-data x-init="setTimeout(() => $wire.set('success', null), 5000)"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-status-success/25 bg-card p-3 shadow-lg ring-1 ring-black/5">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-status-success" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="min-w-0 flex-1 text-sm text-ink">{{ $success }}</p>
            <button type="button" wire:click="$set('success', null)" aria-label="Tutup"
                    class="-m-1 shrink-0 rounded p-1 text-ink-muted hover:text-ink">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    @endif

    @if ($error)
        {{-- Galat tidak hilang sendiri: biasanya menjelaskan kenapa tindakan ditolak. --}}
        <div wire:key="toast-galat"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-status-danger/25 bg-card p-3 shadow-lg ring-1 ring-black/5">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-status-danger" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <p class="min-w-0 flex-1 text-sm text-ink">{{ $error }}</p>
            <button type="button" wire:click="$set('error', null)" aria-label="Tutup"
                    class="-m-1 shrink-0 rounded p-1 text-ink-muted hover:text-ink">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
    @endif
</div>
