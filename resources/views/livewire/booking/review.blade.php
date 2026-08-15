<div>
    @php
        $siapTampil = $konteks === 'publik' ? $this->siapReview : $this->valid;
        $sf = in_array($konteks, ['publik', 'sekolah'], true);
    @endphp

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">{{ $konteks === 'publik' ? 'Checkout' : 'Review booking' }}</h1>
            <p class="text-sm text-ink-muted">Periksa kembali sebelum menyimpan.</p>
        </div>
        <a href="{{ $this->keranjangUrl() }}" wire:navigate class="text-sm font-semibold text-ink-muted hover:text-ink">← Keranjang</a>
    </div>

    @if (! $siapTampil)
        <x-card>
            <div class="py-8 text-center">
                <p class="text-sm text-ink-muted">Data booking belum lengkap.</p>
                <a href="{{ $this->keranjangUrl() }}" wire:navigate class="mt-1 inline-block text-sm font-medium text-brand hover:text-brand-hover">Kembali ke keranjang</a>
            </div>
        </x-card>
    @else
        @if ($error)
            <div class="mb-4 rounded-lg border border-status-danger/20 bg-status-danger/10 px-4 py-3 text-sm text-status-danger">{{ $error }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="Booking untuk">
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$this->ctx['sekolah']?->nama" size="sm" />
                        <div>
                            <div class="text-sm font-medium text-ink">{{ $this->ctx['sekolah']?->nama }}</div>
                            <div class="text-xs text-ink-muted">
                                {{ $this->ctx['sekolah']?->id_sekolah }} ·
                                {{ $this->ctx['sumber'] === 'sekolah' ? 'Booking mandiri' : 'Dibuat marketing' }} ·
                                {{ $this->jumlahSiswa }} siswa
                            </div>
                        </div>
                    </div>
                </x-card>

                <x-card title="Item" padding="p-0">
                    @foreach ($this->lines as $line)
                        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <x-badge :variant="$line['tipe'] === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($line['tipe']) }}</x-badge>
                                    <span class="truncate text-sm {{ $sf ? 'font-bold' : '' }} text-ink">{{ $line['nama'] }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-ink-muted">
                                    @if ($line['desain']) Desain {{ $line['desain'] }} · @endif
                                    @if ($line['ukuran']) {{ $line['ukuran'] }} · @endif
                                    {{ $line['qty'] }} × Rp{{ number_format($line['unit'], 0, ',', '.') }}
                                </div>
                            </div>
                            <div class="text-sm font-medium text-ink">Rp{{ number_format($line['total'], 0, ',', '.') }}</div>
                        </div>
                    @endforeach
                </x-card>

                @if (count($this->freeItems) > 0)
                    <x-card title="Item gratis (free sekolah)" padding="p-0">
                        @foreach ($this->freeItems as $free)
                            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                                <div class="flex items-center gap-2">
                                    <x-badge variant="success">Free</x-badge>
                                    <span class="text-sm text-ink">{{ $free['nama'] ?? 'Produk' }}</span>
                                    @if (! empty($free['ukuran']))<span class="text-xs text-ink-muted">{{ $free['ukuran'] }}</span>@endif
                                    <span class="text-xs text-ink-muted">×{{ $free['qty'] }}</span>
                                </div>
                                <div class="text-sm font-medium text-status-success">Rp0</div>
                            </div>
                        @endforeach
                    </x-card>
                @endif
            </div>

            <div>
                <x-card title="Ringkasan">
                    @if ($konteks === 'publik')
                        <div class="mb-4">
                            <x-input label="Jumlah siswa" type="number" min="1" wire:model.live.debounce.500ms="jumlahSiswaInput" hint="Dipakai untuk aturan free sekolah." />
                        </div>
                    @endif

                    {{-- Jadwal pelaksanaan event (wajib tanggal; jam opsional). --}}
                    <div class="mb-4 space-y-3">
                        <x-input label="Tanggal event" type="date" wire:model="tanggalEvent"
                                 min="{{ now()->toDateString() }}" :error="$errors->first('tanggalEvent')"
                                 hint="Tanggal pelaksanaan foto. Bisa diubah marketing bila perlu." />
                        <x-input label="Jam event (opsional)" type="time" wire:model="jamEvent" :error="$errors->first('jamEvent')" />
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-muted">Subtotal</dt><dd class="text-ink">Rp{{ number_format($this->subtotal, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Item gratis</dt><dd class="font-semibold text-status-success">{{ count($this->freeItems) }} item</dd></div>
                        <div class="flex items-baseline justify-between border-t border-line pt-3">
                            <dt class="{{ $sf ? 'text-sm font-bold text-ink' : 'text-base font-medium text-ink' }}">Total</dt>
                            <dd class="{{ $sf ? 'text-2xl font-extrabold text-navy' : 'text-base font-medium text-ink' }}">Rp{{ number_format($this->total, 0, ',', '.') }}</dd>
                        </div>
                    </dl>

                    <x-button wire:click="simpan" class="mt-5 w-full">
                        <span wire:loading.remove wire:target="simpan">Simpan booking</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </x-button>
                    <p class="mt-2 text-center text-xs text-ink-muted">Kode booking dibuat setelah marketing ditugaskan.</p>
                </x-card>
            </div>
        </div>
    @endif
</div>
