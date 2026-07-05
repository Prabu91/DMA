<x-app-layout>
    <x-slot name="header">
        <h1 class="text-lg font-medium text-ink">Tambah pengguna</h1>
        <p class="text-sm text-ink-muted">Buat akun baru dan tetapkan cabang &amp; peran.</p>
    </x-slot>

    @include('pengguna.form')
</x-app-layout>
