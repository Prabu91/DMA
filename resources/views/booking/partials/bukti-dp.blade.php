{{-- Bukti bayar DP (1 foto). Vars: $order, $flashMsg (opsional). --}}
<div class="mt-4 border-t border-line pt-4">
    <div class="flex items-center justify-between gap-3">
        <span class="text-sm font-medium text-ink">Bukti bayar DP</span>
        @if ($order->bukti_dp_path)
            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->bukti_dp_path) }}" target="_blank" rel="noopener"
               class="text-xs font-medium text-brand hover:text-brand-hover">Lihat foto →</a>
        @endif
    </div>

    @if ($order->bukti_dp_path)
        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->bukti_dp_path) }}" target="_blank" rel="noopener" class="mt-2 block">
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->bukti_dp_path) }}" alt="Bukti DP"
                 class="h-28 w-full max-w-xs rounded-lg border border-line object-cover">
        </a>
    @else
        <p class="mt-1 text-xs text-ink-muted">Belum ada bukti DP.</p>
    @endif

    <div class="mt-3 space-y-2">
        <input type="file" wire:model="buktiDp" accept="image/*"
               class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
        @error('buktiDp')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
        <div class="flex items-center gap-3">
            <x-button wire:click="uploadBuktiDp" size="sm" variant="secondary">
                <span wire:loading.remove wire:target="uploadBuktiDp,buktiDp">{{ $order->bukti_dp_path ? 'Ganti bukti DP' : 'Unggah bukti DP' }}</span>
                <span wire:loading wire:target="uploadBuktiDp,buktiDp">Mengunggah…</span>
            </x-button>
            @if (! empty($flashMsg))<span class="text-sm font-medium text-status-success">{{ $flashMsg }}</span>@endif
        </div>
    </div>
</div>
