{{-- Grid stat cards. Variabel: $stats (array of ['label','value','icon_key','accent','placeholder'?]) --}}
<div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
    @foreach ($stats as $s)
        <x-stat-card
            :label="$s['label']"
            :value="$s['value']"
            :hint="($s['placeholder'] ?? false) ? 'Placeholder' : ($s['hint'] ?? null)"
            :icon="\App\Support\Icons::path($s['icon_key'] ?? null)"
            :accent="$s['accent'] ?? 'brand'"
        />
    @endforeach
</div>
