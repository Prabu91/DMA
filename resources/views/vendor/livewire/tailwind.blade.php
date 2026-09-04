{{--
    Paginasi DMA — menimpa view bawaan Livewire.
    Label berbahasa Indonesia dan memakai token desain aplikasi (line/ink/brand/card/page).
    Ringkasan "Menampilkan x–y dari z" TIDAK ditaruh di sini; itu tugas <x-table-footer>.
--}}
@php
    // Cuplikan scroll bawaan Livewire — harus dibentuk di view ini sendiri.
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()"
        : '';

    $pageName = $paginator->getPageName();
    $base = 'inline-flex min-h-[36px] items-center justify-center rounded-lg border px-3 text-sm font-medium transition';
    $normal = $base.' border-line bg-card text-ink hover:border-brand/50 hover:bg-page';
    $mati = $base.' cursor-default border-line bg-page text-ink-muted/50';
    $aktif = $base.' border-brand bg-brand text-white';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-between gap-2">
        {{-- Ponsel: cukup sebelumnya / berikutnya + posisi halaman --}}
        <div class="flex flex-1 items-center justify-between gap-2 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="{{ $mati }}">Sebelumnya</span>
            @else
                <button type="button" class="{{ $normal }}" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">Sebelumnya</button>
            @endif

            <span class="text-xs text-ink-muted">Hal. {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <button type="button" class="{{ $normal }}" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled">Berikutnya</button>
            @else
                <span class="{{ $mati }}">Berikutnya</span>
            @endif
        </div>

        {{-- Layar lebar: nomor halaman lengkap --}}
        <div class="hidden w-full flex-wrap items-center justify-end gap-1.5 sm:flex">
            @if ($paginator->onFirstPage())
                <span class="{{ $mati }}" aria-disabled="true" aria-label="Halaman sebelumnya">Sebelumnya</span>
            @else
                <button type="button" class="{{ $normal }}" wire:click="previousPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="Halaman sebelumnya">Sebelumnya</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-sm text-ink-muted">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $aktif }}" wire:key="paginator-{{ $pageName }}-page{{ $page }}" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" class="{{ $normal }}" wire:key="paginator-{{ $pageName }}-page{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="Ke halaman {{ $page }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button type="button" class="{{ $normal }}" wire:click="nextPage('{{ $pageName }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" aria-label="Halaman berikutnya">Berikutnya</button>
            @else
                <span class="{{ $mati }}" aria-disabled="true" aria-label="Halaman berikutnya">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
