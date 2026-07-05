{{-- Form cabang dipakai create & edit. Variabel: $cabang (opsional) --}}
@php $cabang = $cabang ?? null; @endphp

<x-card>
    <form method="POST" action="{{ $cabang ? route('cabang.update', $cabang) : route('cabang.store') }}" class="max-w-lg space-y-4">
        @csrf
        @if ($cabang)
            @method('PATCH')
        @endif

        <x-input name="nama" label="Nama cabang" :value="old('nama', $cabang?->nama)" required autofocus placeholder="mis. DMA Jakarta" />

        <x-input name="kode_area" label="Kode area" :value="old('kode_area', $cabang?->kode_area)" placeholder="mis. JKT" hint="Opsional." />

        <div class="flex items-center gap-3 pt-2">
            <x-button type="submit">{{ $cabang ? 'Simpan perubahan' : 'Simpan' }}</x-button>
            <x-button :href="route('cabang.index')" variant="ghost">Batal</x-button>
        </div>
    </form>
</x-card>
