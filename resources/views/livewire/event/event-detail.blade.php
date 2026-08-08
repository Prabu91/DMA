<div>
    @php
        $order = $this->order;
        $selesai = $order->event_status === \App\Support\OrderStatus::EVENT_SELESAI;
    @endphp

    <div class="mb-6 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('app.event.index') }}" wire:navigate class="mb-2 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                Kembali ke jadwal event
            </a>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg font-medium text-ink">{{ $order->booking_code ?? 'Event #'.$order->id }}</h1>
                <x-badge :variant="\App\Support\OrderStatus::badge($order->event_status)">{{ \App\Support\OrderStatus::label($order->event_status) }}</x-badge>
                @php $cd = $order->eventCountdown(); @endphp
                @if ($cd)
                    <x-badge :variant="$cd['state'] === 'past' ? 'danger' : ($cd['state'] === 'today' ? 'pending' : 'info')">{{ $cd['label'] }}</x-badge>
                @endif
            </div>
        </div>
        <a href="{{ route('app.event.ste', $order->id) }}" target="_blank"
           class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-line bg-card px-3 py-2 text-sm font-medium text-ink hover:bg-page">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            Cetak STE
        </a>
    </div>

    @if (session('event-flash'))
        <div class="mb-4 rounded-lg border border-status-success/20 bg-status-success/10 px-4 py-3 text-sm text-status-success">{{ session('event-flash') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($revisiMode)
                {{-- ============ FORM REVISI ============ --}}
                <x-card title="Revisi detail order">
                    <x-slot name="subtitle">Perbaiki data sekolah &amp; desain/kode item sesuai kondisi di lokasi.</x-slot>

                    <div class="space-y-4">
                        <x-input label="Nama sekolah" wire:model="namaSekolah" :error="$errors->first('namaSekolah')" />
                        <x-input label="Alamat sekolah" wire:model="alamatSekolah" :error="$errors->first('alamatSekolah')" />
                        <x-input label="Kota" wire:model="kotaSekolah" :error="$errors->first('kotaSekolah')" />
                    </div>

                    @if (count($this->desainOptionsPerItem) > 0)
                        <div class="mt-5 border-t border-line pt-4">
                            <h3 class="mb-2 text-sm font-medium text-ink">Desain / kode item</h3>
                            <div class="space-y-3">
                                @foreach ($order->items as $item)
                                    @if (isset($this->desainOptionsPerItem[$item->id]))
                                        <div>
                                            <x-select
                                                :label="$item->produk?->nama"
                                                wire:model="itemDesain.{{ $item->id }}"
                                                :options="$this->desainOptionsPerItem[$item->id]"
                                                :selected="$itemDesain[$item->id] ?? null"
                                                placeholder="— Tanpa desain —"
                                                :error="$errors->first('itemDesain.'.$item->id)" />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-5 flex items-center gap-3">
                        <x-button wire:click="simpanRevisi">
                            <span wire:loading.remove wire:target="simpanRevisi">Simpan revisi</span>
                            <span wire:loading wire:target="simpanRevisi">Menyimpan…</span>
                        </x-button>
                        <x-button wire:click="batalRevisi" variant="ghost">Batal</x-button>
                    </div>
                </x-card>
            @else
                {{-- ============ KONFIRMASI DI LOKASI ============ --}}
                <x-card title="Konfirmasi detail di lokasi">
                    @if ($selesai)
                        <p class="text-sm text-ink-muted">Event sudah selesai — detail terkunci.</p>
                    @elseif ($order->konfirmasi_lokasi_at)
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-sm text-status-success">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Detail dikonfirmasi sesuai pada {{ $order->konfirmasi_lokasi_at->translatedFormat('d M Y H:i') }}. Siap dilanjutkan ke penyelesaian.</span>
                            </div>
                            <x-button wire:click="mulaiRevisi" variant="ghost" size="sm">Masih perlu revisi</x-button>
                        </div>
                    @else
                        <p class="text-sm text-ink-muted">Cek ulang data sekolah &amp; item di lokasi, lalu pilih:</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-button wire:click="konfirmasiLokasi">
                                <span wire:loading.remove wire:target="konfirmasiLokasi">Detail sudah sesuai</span>
                                <span wire:loading wire:target="konfirmasiLokasi">Menyimpan…</span>
                            </x-button>
                            <x-button wire:click="mulaiRevisi" variant="secondary">Perlu revisi</x-button>
                        </div>
                    @endif
                </x-card>

                {{-- Detail sekolah & jadwal --}}
                <x-card title="Detail sekolah & jadwal">
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs text-ink-muted">Nama sekolah</dt><dd class="font-medium text-ink">{{ $order->sekolah?->nama ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">ID sekolah</dt><dd class="text-ink">{{ $order->sekolah?->id_sekolah ?? '—' }}</dd></div>
                        <div class="sm:col-span-2">
                        <dt class="text-xs text-ink-muted">Alamat</dt>
                        <dd class="text-ink">{{ $order->sekolah?->alamat ?? '—' }}{{ $order->sekolah?->kota ? ', '.$order->sekolah->kota : '' }}</dd>
                        @if ($order->sekolah?->maps_link)
                            <a href="{{ $order->sekolah->maps_link }}" target="_blank" rel="noopener"
                               class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand hover:text-brand-hover">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                                Buka di Google Maps
                            </a>
                        @endif
                    </div>
                        <div><dt class="text-xs text-ink-muted">PIC sekolah</dt><dd class="text-ink">{{ $order->sekolah?->pic_sekolah ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">No. telepon</dt><dd class="text-ink">{{ $order->sekolah?->no_telp_pic ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">Tanggal event</dt><dd class="font-medium text-ink">{{ $order->tanggal_event ? $order->tanggal_event->translatedFormat('l, d M Y') : 'Belum ditentukan' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">Jam event</dt><dd class="text-ink">{{ $order->jam_event ?: '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">Marketing</dt><dd class="text-ink">{{ $order->marketing?->nama ?? $order->marketing?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-ink-muted">Jumlah siswa</dt><dd class="text-ink">{{ $order->jumlah_siswa ?? '—' }}</dd></div>
                    </dl>
                </x-card>

                {{-- Rincian pesanan: desain & kode --}}
                <x-card title="Rincian pesanan (desain & kode)" padding="p-0">
                    @forelse ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 last:border-b-0">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <x-badge :variant="$item->tipe_item === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($item->tipe_item) }}</x-badge>
                                    <span class="truncate text-sm text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                                    @if ($item->is_free)<x-badge variant="success">Free</x-badge>@endif
                                </div>
                                <div class="mt-0.5 text-xs text-ink-muted">
                                    @if ($item->desain) Desain <span class="font-medium text-ink">{{ $item->desain->kode }}</span> · @endif
                                    @if ($item->opsi_ukuran) {{ $item->opsi_ukuran }} · @endif
                                    Qty {{ $item->qty }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-ink-muted">Tak ada item.</div>
                    @endforelse
                </x-card>
            @endif

            {{-- Riwayat aktivitas + pihak terlibat --}}
            @include('booking.partials.activity-timeline', ['order' => $order, 'activities' => $this->activities])
        </div>

        {{-- Penyelesaian event (OTP) --}}
        <div class="space-y-6">
            <x-card title="Penyelesaian event">
                @if ($selesai)
                    <div class="flex items-start gap-2 text-sm text-status-success">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @if ($order->event_selesai_at)
                            <span>Event selesai pada <span class="font-medium">{{ $order->event_selesai_at->translatedFormat('d M Y · H:i') }}</span>.</span>
                        @else
                            <span>Event sudah selesai.</span>
                        @endif
                    </div>
                @elseif (! $order->konfirmasi_lokasi_at)
                    <p class="text-sm text-ink-muted">Konfirmasi detail di lokasi dulu, lalu buat OTP untuk menyelesaikan event.</p>
                @elseif (! $order->eventOtpActive())
                    <p class="text-sm text-ink-muted">Buat OTP — dikirim ke guru (portal &amp; email). Guru akan membacakan kodenya ke Anda.</p>
                    <x-button wire:click="generateOtp" class="mt-3 w-full">
                        <span wire:loading.remove wire:target="generateOtp">Generate OTP untuk guru</span>
                        <span wire:loading wire:target="generateOtp">Mengirim…</span>
                    </x-button>
                    @error('otpInput')<p class="mt-2 text-sm text-status-danger">{{ $message }}</p>@enderror
                @else
                    <div class="rounded-lg border border-status-info/20 bg-status-info/10 px-3 py-2 text-xs text-status-info">
                        OTP sudah dikirim ke guru. Minta kodenya, lalu masukkan di bawah.
                        <span class="block text-status-info/80">Berlaku hingga {{ $order->otp_expires->translatedFormat('H:i') }} ({{ \App\Models\Order::OTP_EXPIRY_MINUTES }} menit).</span>
                    </div>
                    <div class="mt-3 space-y-2">
                        <x-input label="Kode OTP dari guru" wire:model="otpInput" inputmode="numeric" maxlength="6" placeholder="6 digit" :error="$errors->first('otpInput')" />
                        <x-button wire:click="selesaikanDenganOtp" class="w-full">
                            <span wire:loading.remove wire:target="selesaikanDenganOtp">Selesaikan event</span>
                            <span wire:loading wire:target="selesaikanDenganOtp">Memproses…</span>
                        </x-button>
                        {{-- Kirim ulang dengan cooldown (hitung mundur) --}}
                        <div wire:key="otp-cd-{{ $order->otp_expires?->timestamp }}"
                             x-data="{ s: {{ $order->otpResendSecondsLeft() }} }"
                             x-init="const t = setInterval(() => { if (s > 0) { s-- } else { clearInterval(t) } }, 1000)">
                            <button type="button" wire:click="generateOtp" x-bind:disabled="s > 0"
                                    class="w-full text-center text-xs text-ink-muted hover:text-ink disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-show="s <= 0">Kirim ulang OTP</span>
                                <span x-show="s > 0" x-cloak>Kirim ulang dalam <span x-text="s"></span> detik</span>
                            </button>
                        </div>
                    </div>
                @endif

                @if (! $selesai && auth()->user()->seesAllCabang())
                    <div class="mt-4 border-t border-line pt-3">
                        <button type="button" wire:click="selesaikanOverride" wire:confirm="Selesaikan event tanpa OTP? (override admin)"
                                class="text-xs font-medium text-ink-muted hover:text-status-danger">Selesaikan tanpa OTP (override admin)</button>
                    </div>
                @endif
            </x-card>

            <x-card title="Progres event">
                @php $ms = $order->milestones(); @endphp
                @if ($ms === [])
                    <p class="text-sm text-ink-muted">Tanggal event belum ditentukan.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($ms as $m)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <span class="text-ink">{{ $m['label'] }}</span>
                                @if ($m['state'] === 'confirmed')
                                    <x-badge variant="success">Terkonfirmasi</x-badge>
                                @elseif ($m['state'] === 'overdue')
                                    <x-badge variant="danger">Terlewat</x-badge>
                                @else
                                    <x-badge variant="neutral">{{ $m['due']->translatedFormat('d M') }}</x-badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-4 border-t border-line pt-3 text-xs">
                    <span class="text-ink-muted">Konfirmasi lokasi:</span>
                    @if ($order->konfirmasi_lokasi_at)
                        <span class="font-medium text-status-success">Sudah</span>
                    @else
                        <span class="text-ink-muted">Belum</span>
                    @endif
                </div>
            </x-card>
        </div>
    </div>
</div>
