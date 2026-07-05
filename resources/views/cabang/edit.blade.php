<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Ubah cabang</h1>
        <p class="text-sm text-ink-muted">Perbarui data {{ $cabang->nama }}.</p>
    </x-slot>

    @include('cabang.form', ['cabang' => $cabang])
</x-app-layout>
