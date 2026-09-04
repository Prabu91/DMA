@props(['paginator'])

{{--
    Kaki daftar/tabel: ringkasan jumlah + pemilih baris per halaman + navigasi halaman.
    Komponen Livewire pemakainya wajib memakai trait WithPerPage (properti $perPage)
    dan WithPagination.
--}}
@if ($paginator->total() > 0)
    <div class="mt-4 space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-ink-muted">
                Menampilkan {{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}
                dari {{ number_format($paginator->total(), 0, ',', '.') }} data
            </p>

            <label class="flex items-center gap-2 text-xs text-ink-muted">
                <span class="shrink-0">Baris per halaman</span>
                <select wire:model.live="perPage"
                        class="min-h-[36px] rounded-lg border border-line bg-card py-1 pl-3 pr-9 text-sm text-ink focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                    @foreach (\App\Support\Tabel::PER_PAGE as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        {{ $paginator->links() }}
    </div>
@endif
