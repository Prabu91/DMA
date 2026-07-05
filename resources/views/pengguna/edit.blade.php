<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Ubah pengguna</h1>
        <p class="text-sm text-ink-muted">Perbarui data {{ $pengguna->nama ?? $pengguna->name }}.</p>
    </x-slot>

    @include('pengguna.form', ['pengguna' => $pengguna])
</x-app-layout>
