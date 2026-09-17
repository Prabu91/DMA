{{--
    Redaksi (teks cetak) + folder kerja editor untuk satu order (panel staf).
    Pengganti lampiran REDAKSI.txt dan path folder yang di Trello diketik
    manual per kartu.
--}}
@php
    $redaksi = \App\Support\Redaksi::untuk($order);
    $dikoreksi = \App\Support\Redaksi::dikoreksi($order);
    $perluDicek = \App\Support\Redaksi::perluDicek($order);
    $folderItem = $this->folderItem;
    $salinJs = 'navigator.clipboard.writeText($el.dataset.teks).then(() => { tersalin = $el.dataset.kunci; setTimeout(() => tersalin = null, 1500) })';
@endphp

<x-card title="Redaksi & folder kerja">
    <x-slot name="subtitle">Teks yang ikut dicetak, dan folder foto di server kantor untuk editor.</x-slot>

    <div x-data="{ tersalin: null }" class="space-y-5">
        {{-- Redaksi --}}
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="text-sm font-medium text-ink">Redaksi</h4>
                    @if ($dikoreksi)
                        <x-badge variant="brand">Dikoreksi untuk order ini</x-badge>
                    @else
                        <x-badge variant="neutral">Dari data sekolah</x-badge>
                    @endif
                </div>
                @unless ($redaksiEdit)
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <button type="button" data-teks="{{ $redaksi }}" data-kunci="redaksi" x-on:click="{{ $salinJs }}"
                                class="font-medium text-brand hover:text-brand-hover">
                            <span x-show="tersalin !== 'redaksi'">Salin</span>
                            <span x-show="tersalin === 'redaksi'" x-cloak>Tersalin ✓</span>
                        </button>
                        <a href="{{ route('app.order.redaksi', $order->id) }}" class="font-medium text-brand hover:text-brand-hover">Unduh .txt</a>
                        @if ($this->bisaUbahRedaksi)
                            <button type="button" wire:click="mulaiEditRedaksi" class="font-medium text-brand hover:text-brand-hover">Koreksi</button>
                        @endif
                    </div>
                @endunless
            </div>

            @if ($redaksiEdit)
                <div class="mt-2 space-y-2">
                    <textarea wire:model="redaksiTeks" rows="3"
                              class="block w-full rounded-lg border-line bg-card text-sm text-ink focus:border-brand focus:ring-brand/30"></textarea>
                    @error('redaksiTeks')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                    <p class="text-xs text-ink-muted">Koreksi ini hanya berlaku untuk order ini — data sekolah tidak ikut berubah.</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button size="sm" wire:click="simpanRedaksi">
                            <span wire:loading.remove wire:target="simpanRedaksi">Simpan</span>
                            <span wire:loading wire:target="simpanRedaksi">Menyimpan…</span>
                        </x-button>
                        <x-button size="sm" variant="secondary" wire:click="batalEditRedaksi">Batal</x-button>
                        @if ($dikoreksi)
                            <x-button size="sm" variant="ghost" wire:click="redaksiIkutSekolah">Kembalikan ke data sekolah</x-button>
                        @endif
                    </div>
                </div>
            @else
                @if ($redaksi !== '')
                    <pre class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-line bg-page/60 px-3 py-2 font-sans text-sm text-ink">{{ $redaksi }}</pre>
                @else
                    <p class="mt-2 text-sm text-status-danger">Redaksi kosong — nama/alamat sekolah belum diisi.</p>
                @endif
            @endif

            @if ($perluDicek && ! $redaksiEdit)
                <p class="mt-2 rounded-lg border border-status-pending/30 bg-status-pending/10 px-3 py-2 text-xs text-ink">
                    Redaksi mengandung garis bawah ( _ ). Nama di data sekolah kadang berakhiran seperti <span class="font-mono">_0925</span> karena salah penamaan —
                    pastikan tidak ikut tercetak, dan koreksi bila perlu.
                </p>
            @endif

            @if ($redaksiMsg)
                <p class="mt-2 text-sm font-medium text-status-success">{{ $redaksiMsg }}</p>
            @endif
        </div>

        {{-- Folder kerja per item --}}
        <div>
            <h4 class="text-sm font-medium text-ink">Folder kerja per item</h4>
            <div class="mt-2 divide-y divide-line rounded-lg border border-line">
                @foreach ($order->items as $it)
                    @php $path = $folderItem[$it->id] ?? ''; @endphp
                    <div wire:key="folder-{{ $it->id }}" class="space-y-1.5 px-3 py-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-ink">{{ $it->produk?->nama ?? $it->paket?->nama }}</span>
                                @if ($it->opsi_ukuran)<span class="text-xs text-ink-muted">{{ $it->opsi_ukuran }}</span>@endif
                                @if ($it->is_free)<x-badge variant="success">Free</x-badge>@endif
                                @if ($it->tanpa_redaksi)<x-badge variant="neutral">Tanpa redaksi</x-badge>@endif
                            </div>
                            @if ($this->bisaUbahRedaksi)
                                <x-toggle wire:click="toggleTanpaRedaksi({{ $it->id }})" :checked="! $it->tanpa_redaksi"
                                          wire:loading.attr="disabled" wire:target="toggleTanpaRedaksi({{ $it->id }})"
                                          label="Pakai redaksi" class="shrink-0" />
                            @endif
                        </div>
                        <div class="flex items-start gap-2">
                            <code class="min-w-0 flex-1 break-all rounded bg-page/60 px-2 py-1 text-xs text-ink">{{ $path }}</code>
                            <button type="button" data-teks="{{ $path }}" data-kunci="f{{ $it->id }}" x-on:click="{{ $salinJs }}"
                                    class="shrink-0 pt-0.5 text-xs font-medium text-brand hover:text-brand-hover">
                                <span x-show="tersalin !== 'f{{ $it->id }}'">Salin</span>
                                <span x-show="tersalin === 'f{{ $it->id }}'" x-cloak>✓</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-ink-muted">Pola folder diatur admin di halaman Pengaturan.</p>
        </div>
    </div>
</x-card>
