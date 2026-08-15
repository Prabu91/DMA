@props([
    'head' => null,
    'minWidth' => '760px',
])

{{-- Tabel data konsisten: kontainer rounded, scroll horizontal di layar sempit,
     header di atas, baris hover. Isi body lewat default slot. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-line bg-card']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm" style="min-width: {{ $minWidth }}">
            @if ($head)
                <thead>
                    <tr class="border-b border-line bg-page/60 text-xs uppercase tracking-wide text-ink-muted">
                        {{ $head }}
                    </tr>
                </thead>
            @endif
            <tbody class="divide-y divide-line">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
