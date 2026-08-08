@php
    $order = $this->order;
    $sf = $konteks === 'sekolah';
@endphp
<div>
    {{-- Breadcrumb (panel staf) --}}
    @unless ($sf)
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'url' => route('app.dashboard')],
            ['label' => 'Order', 'url' => route('app.order.index')],
            ['label' => $order->booking_code ?? 'Order #'.$order->id],
        ]" />
    @endunless

    {{-- Banner sukses (portal sekolah) --}}
    @if ($sf)
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-navy-900 px-5 py-4">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            </span>
            <div>
                <div class="text-[11px] font-extrabold tracking-[0.14em] text-brand">BOOKING BERHASIL</div>
                <div class="text-lg font-extrabold tracking-tight text-white">Reservasi kamu tersimpan!</div>
            </div>
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">{{ $sf ? 'Booking tersimpan' : ($order->booking_code ?? 'Order #'.$order->id) }}</h1>
            <p class="text-sm text-ink-muted">{{ $order->sekolah?->nama }} · {{ optional($order->tanggal_booking)->translatedFormat('d M Y H:i') }}</p>
        </div>
        <a href="{{ $this->kembaliUrl() }}" wire:navigate class="text-sm font-semibold text-ink-muted hover:text-ink">Selesai</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Status / tiket --}}
            <x-card :padding="$sf ? 'p-0' : 'p-5'">
                @if ($order->booking_code)
                    @if ($sf)
                        {{-- Tiket (portal sekolah) --}}
                        <div class="flex flex-col items-stretch sm:flex-row">
                            <div class="flex flex-col items-center justify-center gap-2 bg-page p-5 sm:border-r-2 sm:border-dashed sm:border-line">
                                <div class="h-[150px] w-[150px]">{!! $this->qrSvg() !!}</div>
                                <div class="text-[10px] font-extrabold tracking-[0.1em] text-ink-muted">SCAN DI LOKASI</div>
                            </div>
                            <div class="flex-1 p-5">
                                <x-badge variant="success">Kode booking</x-badge>
                                <div class="mt-2 text-xl font-extrabold tracking-wide text-navy">{{ $order->booking_code }}</div>
                                <div class="mt-4 flex gap-2">
                                    <x-button :href="$this->pdfUrl()" size="sm" target="_blank">Unduh / cetak PDF</x-button>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Inline (panel staf) --}}
                        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                            <div class="rounded-lg border border-line bg-white p-2">{!! $this->qrSvg() !!}</div>
                            <div>
                                <x-badge variant="success">Kode booking</x-badge>
                                <div class="mt-2 text-lg font-medium tracking-wide text-ink">{{ $order->booking_code }}</div>
                                <div class="mt-3 flex gap-2">
                                    <x-button :href="$this->pdfUrl()" size="sm" target="_blank">Unduh / cetak PDF</x-button>
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div @class(['p-5' => $sf])>
                        <x-badge variant="pending">Menunggu penugasan marketing</x-badge>
                        <p class="mt-2 text-xs text-ink-muted">Kode booking &amp; QR dibuat setelah marketing ditugaskan.</p>
                    </div>
                @endif
            </x-card>

            {{-- OTP penyelesaian event — untuk guru (portal sekolah) --}}
            @if ($sf && $order->event_status !== \App\Support\OrderStatus::EVENT_SELESAI && $order->eventOtpActive())
                <x-card>
                    <div class="rounded-xl border border-brand/20 bg-brand/5 p-5 text-center">
                        <div class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-brand">Kode OTP penyelesaian event</div>
                        <div class="mt-2 text-3xl font-extrabold tracking-[0.3em] text-navy">{{ $order->otp_code }}</div>
                        <p class="mx-auto mt-3 max-w-sm text-xs text-ink-muted">
                            Bacakan kode ini kepada tim event di lokasi sebagai konfirmasi event telah selesai.
                            Berlaku hingga {{ $order->otp_expires?->translatedFormat('d M Y H:i') }}.
                        </p>
                    </div>
                </x-card>
            @endif

            {{-- Jadwal event — dapat diubah staf (marketing/area) --}}
            @unless ($sf)
                <x-card title="Jadwal event">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-44">
                            <x-input label="Tanggal event" type="date" wire:model="tanggalEvent" :error="$errors->first('tanggalEvent')" />
                        </div>
                        <div class="w-28">
                            <x-input label="Jam (opsional)" type="time" wire:model="jamEvent" :error="$errors->first('jamEvent')" />
                        </div>
                        <x-button wire:click="simpanJadwal" wire:confirm="Simpan perubahan jadwal event?">
                            <span wire:loading.remove wire:target="simpanJadwal">Simpan jadwal</span>
                            <span wire:loading wire:target="simpanJadwal">Menyimpan…</span>
                        </x-button>
                        @if ($jadwalMsg)
                            <span class="pb-2.5 text-sm font-medium text-status-success">{{ $jadwalMsg }}</span>
                        @endif
                    </div>
                </x-card>
            @endunless

            {{-- Status pembayaran — dikelola staf --}}
            @unless ($sf)
                @php $st = $order->status ?: 'baru'; @endphp
                <x-card title="Status pembayaran">
                    <div class="flex items-center gap-2">
                        <x-badge :variant="\App\Support\OrderStatus::badge($st)">{{ \App\Support\OrderStatus::label($st) }}</x-badge>
                    </div>

                    <div class="mt-4">
                        <x-input label="Catatan pembayaran (opsional)" wire:model="catatan" placeholder="mis. DP 50% via transfer BCA" />
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if ($st === 'baru')
                            <x-button wire:click="ubahStatus('dp')" wire:confirm="Konfirmasi DP sudah diterima?" size="sm">Konfirmasi DP</x-button>
                            <x-button wire:click="ubahStatus('lunas')" wire:confirm="Tandai order ini LUNAS?" variant="secondary" size="sm">Tandai lunas</x-button>
                        @elseif ($st === 'dp')
                            <x-button wire:click="ubahStatus('lunas')" wire:confirm="Tandai order ini LUNAS?" size="sm">Tandai lunas</x-button>
                        @elseif ($st === 'lunas')
                            <span class="text-sm font-medium text-status-success">Pembayaran lunas ✓</span>
                        @endif

                        @if (in_array($st, ['baru', 'dp', 'lunas'], true))
                            <x-button wire:click="ubahStatus('batal')" wire:confirm="Batalkan order ini?" variant="danger" size="sm">Batalkan</x-button>
                        @elseif ($st === 'batal')
                            <x-button wire:click="ubahStatus('baru')" wire:confirm="Aktifkan kembali order ini?" variant="secondary" size="sm">Aktifkan kembali</x-button>
                        @endif
                    </div>

                    @if ($statusMsg)
                        <p class="mt-3 text-sm font-medium text-status-success">{{ $statusMsg }}</p>
                    @endif
                </x-card>
            @endunless

            {{-- Progres event: countdown + milestone H-7/H-2/Hari-H (staf) --}}
            @unless ($sf)
                <x-card title="Progres event">
                    @php $cd = $order->eventCountdown(); @endphp
                    @if (! $cd)
                        <p class="text-sm text-ink-muted">Isi tanggal event dulu untuk melacak milestone H-7 / H-2 / Hari-H.</p>
                    @else
                        <div class="mb-4 flex items-center gap-2">
                            <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                            <span class="text-sm text-ink-muted">menuju {{ $order->tanggal_event->translatedFormat('d M Y') }}</span>
                        </div>

                        <div class="space-y-2">
                            @foreach ($order->milestones() as $m)
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                                    <div>
                                        <div class="text-sm font-medium text-ink">{{ $m['label'] }}</div>
                                        <div class="text-xs text-ink-muted">
                                            {{ $m['due']->translatedFormat('d M Y') }}
                                            @if ($m['state'] === 'confirmed') · dikonfirmasi {{ $m['confirmedAt']->translatedFormat('d M') }}@endif
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($m['state'] === 'confirmed')
                                            <x-badge variant="success">Terkonfirmasi</x-badge>
                                        @else
                                            @if ($m['state'] === 'overdue')<x-badge variant="danger">Terlewat</x-badge>@endif
                                            <x-button wire:click="konfirmasiMilestone('{{ $m['key'] }}')" wire:confirm="Konfirmasi milestone {{ $m['label'] }} sekarang?" variant="secondary" size="sm">Konfirmasi</x-button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($milestoneMsg)
                            <p class="mt-3 text-sm font-medium text-status-success">{{ $milestoneMsg }}</p>
                        @endif
                    @endif
                </x-card>
            @endunless

            {{-- Tim event + STE (staf) --}}
            @unless ($sf)
                <x-card title="Tim event">
                    <x-slot name="actions">
                        <a href="{{ route('app.order.ste', $order->id) }}" target="_blank"
                           class="text-sm font-medium text-brand hover:text-brand-hover">Cetak STE →</a>
                    </x-slot>

                    @if ($this->timEventOptions->isEmpty())
                        <p class="text-sm text-ink-muted">Belum ada anggota tim event di cabang ini.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($this->timEventOptions as $u)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="timEventTerpilih" value="{{ $u->id }}"
                                           class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                    <span class="text-sm text-ink">{{ $u->nama ?? $u->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-4 flex items-center gap-3">
                            <x-button wire:click="simpanTimEvent" wire:confirm="Simpan penugasan tim event?" size="sm">Simpan tim</x-button>
                            @if ($timMsg)<span class="text-sm font-medium text-status-success">{{ $timMsg }}</span>@endif
                        </div>
                    @endif
                </x-card>
            @endunless

            {{-- Riwayat aktivitas + pihak terlibat (staf) --}}
            @unless ($sf)
                @include('booking.partials.activity-timeline', ['order' => $order, 'activities' => $this->activities])
            @endunless

            {{-- Proofing (segera hadir) — hanya untuk sekolah --}}
            @if ($konteks === 'sekolah')
                <x-card title="Proofing desain">
                    <div class="flex items-center gap-2">
                        <x-badge variant="neutral">Segera hadir</x-badge>
                        <span class="text-sm text-ink-muted">Pratinjau &amp; persetujuan desain akan tersedia di sini.</span>
                    </div>
                </x-card>
            @endif

            {{-- Item --}}
            <x-card title="Item" padding="p-0">
                @foreach ($this->paidItems as $item)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-badge :variant="$item->tipe_item === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($item->tipe_item) }}</x-badge>
                                <span class="truncate text-sm {{ $sf ? 'font-bold' : '' }} text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-muted">
                                @if ($item->desain) Desain {{ $item->desain->kode }} · @endif
                                @if ($item->opsi_ukuran) {{ $item->opsi_ukuran }} · @endif
                                {{ $item->qty }}{{ $item->produk?->isPerSiswa() ? ' siswa' : '' }} × Rp{{ number_format($item->harga, 0, ',', '.') }}
                            </div>
                        </div>
                        <div class="text-sm font-medium text-ink">Rp{{ number_format($item->harga * $item->qty, 0, ',', '.') }}</div>
                    </div>
                @endforeach

                @foreach ($this->freeItems as $item)
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                        <div class="flex items-center gap-2">
                            <x-badge variant="success">Free</x-badge>
                            <span class="text-sm text-ink">{{ $item->produk?->nama ?? 'Produk' }}</span>
                            @if ($item->opsi_ukuran)<span class="text-xs text-ink-muted">{{ $item->opsi_ukuran }}</span>@endif
                            <span class="text-xs text-ink-muted">×{{ $item->qty }}</span>
                        </div>
                        <div class="text-sm font-medium text-status-success">Rp0</div>
                    </div>
                @endforeach
            </x-card>
        </div>

        <div>
            <x-card title="Ringkasan">
                <dl class="space-y-2 text-sm">
                    @if ($sf)
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">Jadwal event</dt>
                            <dd class="text-right {{ $order->tanggal_event ? 'font-semibold text-ink' : 'text-ink-muted' }}">
                                @if ($order->tanggal_event)
                                    {{ $order->tanggal_event->translatedFormat('d M Y') }}@if ($order->jam_event) · {{ $order->jam_event }}@endif
                                @else
                                    Belum ditentukan
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div class="flex justify-between"><dt class="text-ink-muted">Jumlah siswa</dt><dd class="text-ink">{{ $order->jumlah_siswa }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-muted">Item gratis</dt><dd class="font-semibold text-status-success">{{ $this->freeItems->count() }} item</dd></div>
                    <div class="flex items-baseline justify-between border-t border-line pt-3">
                        <dt class="{{ $sf ? 'text-sm font-bold text-ink' : 'text-base font-medium text-ink' }}">Total</dt>
                        <dd class="{{ $sf ? 'text-2xl font-extrabold text-navy' : 'text-base font-medium text-ink' }}">Rp{{ number_format($order->total, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </x-card>
        </div>
    </div>
</div>
