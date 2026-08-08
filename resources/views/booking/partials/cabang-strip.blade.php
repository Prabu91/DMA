{{-- Strip cabang klik-able (admin). Vars: $cabangs (collection), $counts ([id=>n]), $aktif (string cabangId), $total (int) --}}
<div class="mb-4 flex flex-wrap gap-2">
    <button type="button" wire:click="$set('cabangId', '')"
        @class([
            'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm transition-colors',
            'border-brand bg-brand/5 text-brand' => $aktif === '',
            'border-line bg-card text-ink-muted hover:border-brand/40' => $aktif !== '',
        ])>
        Semua
        <span class="rounded-full bg-ink/5 px-1.5 text-xs font-semibold text-ink">{{ $total }}</span>
    </button>
    @foreach ($cabangs as $c)
        <button type="button" wire:click="$set('cabangId', '{{ $c->id }}')"
            @class([
                'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm transition-colors',
                'border-brand bg-brand/5 text-brand' => $aktif === (string) $c->id,
                'border-line bg-card text-ink-muted hover:border-brand/40' => $aktif !== (string) $c->id,
            ])>
            {{ $c->nama }}
            <span class="rounded-full bg-ink/5 px-1.5 text-xs font-semibold text-ink">{{ $counts[$c->id] ?? 0 }}</span>
        </button>
    @endforeach
</div>
