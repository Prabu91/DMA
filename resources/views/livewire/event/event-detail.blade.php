<div>
    @php
        $order = $this->order;
        $selesai = $order->event_status === \App\Support\OrderStatus::EVENT_SELESAI;
        $terkunci = $order->isLocked();
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
                @if ($terkunci)<x-badge variant="neutral">Terkunci (final)</x-badge>@endif
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

    @if ($terkunci && ! $selesai)
        <div class="mb-4 rounded-lg border-l-4 border-navy bg-navy/5 px-4 py-3 text-sm text-ink">
            <span class="font-medium">Order terkunci.</span> Hari-H telah dikonfirmasi — data & item tidak bisa diubah lagi. Lanjutkan ke penyelesaian (OTP).
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($revisiMode)
                {{-- ============ FORM REVISI DATA SEKOLAH & DESAIN ============ --}}
                <x-card title="Revisi data sekolah & desain">
                    <x-slot name="subtitle">Perbaiki data sekolah &amp; desain/kode item sesuai kondisi di lokasi.</x-slot>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input label="Nama sekolah" wire:model="namaSekolah" :error="$errors->first('namaSekolah')" class="sm:col-span-2" />
                        <x-input label="PIC sekolah" wire:model="picSekolah" :error="$errors->first('picSekolah')" />
                        <x-input label="No. telepon PIC" wire:model="noTelpSekolah" :error="$errors->first('noTelpSekolah')" />
                        <x-input label="Alamat sekolah" wire:model="alamatSekolah" :error="$errors->first('alamatSekolah')" class="sm:col-span-2" />
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
                {{-- ============ KONFIRMASI & FINALISASI DI LOKASI ============ --}}
                @unless ($terkunci)
                    <x-card title="Konfirmasi di lokasi">
                        <p class="text-sm text-ink-muted">Cek ulang data sekolah &amp; item. Bila perlu, revisi atau tambah/kurangi item di bawah. Setelah semua benar, konfirmasi Hari-H (final).</p>
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            @unless ($order->konfirmasi_lokasi_at)
                                <x-button wire:click="konfirmasiLokasi" variant="secondary" size="sm">
                                    <span wire:loading.remove wire:target="konfirmasiLokasi">Tandai detail sudah dicek</span>
                                    <span wire:loading wire:target="konfirmasiLokasi">Menyimpan…</span>
                                </x-button>
                            @else
                                <span class="inline-flex items-center gap-1.5 text-sm text-status-success">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Data sekolah dicek {{ $order->konfirmasi_lokasi_at->translatedFormat('d M · H:i') }}@if ($order->konfirmasiLokasiOleh) · {{ $order->konfirmasiLokasiOleh->name }}@endif
                                </span>
                            @endunless
                            <x-button wire:click="mulaiRevisi" variant="ghost" size="sm">Revisi data sekolah/desain</x-button>
                        </div>
                    </x-card>
                @endunless

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

                {{-- Rincian pesanan (editor item) --}}
                <x-card title="Rincian pesanan" padding="p-0">
                    <x-slot name="actions">
                        <span class="text-sm font-medium text-ink">Total Rp{{ number_format($order->total, 0, ',', '.') }}</span>
                    </x-slot>
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
                                    Rp{{ number_format($item->harga, 0, ',', '.') }} × {{ $item->qty }}
                                </div>
                            </div>
                            @if (! $terkunci && ! $item->is_free)
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button type="button" wire:click="ubahQtyItem({{ $item->id }}, {{ $item->qty - 1 }})" @disabled($item->qty <= 1)
                                            class="grid h-8 w-8 place-items-center rounded-lg border border-line text-ink hover:bg-page disabled:opacity-40">−</button>
                                    <span class="w-8 text-center text-sm text-ink">{{ $item->qty }}</span>
                                    <button type="button" wire:click="ubahQtyItem({{ $item->id }}, {{ $item->qty + 1 }})"
                                            class="grid h-8 w-8 place-items-center rounded-lg border border-line text-ink hover:bg-page">+</button>
                                    <button type="button" wire:click="hapusItem({{ $item->id }})" wire:confirm="Hapus item {{ $item->produk?->nama ?? $item->paket?->nama }}?"
                                            class="ml-1 grid h-8 w-8 place-items-center rounded-lg border border-line text-status-danger hover:bg-status-danger/10" title="Hapus item">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-ink-muted">Tak ada item.</div>
                    @endforelse

                    {{-- Tambah item --}}
                    @unless ($terkunci)
                        <div class="border-t border-line bg-page/50 px-5 py-4">
                            <h3 class="mb-3 text-sm font-medium text-ink">Tambah item</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-select label="Tipe" wire:model.live="tambahTipe" :options="['produk' => 'Produk', 'paket' => 'Paket']" :selected="$tambahTipe" />
                                <x-input type="number" min="1" label="Jumlah" wire:model="tambahQty" :error="$errors->first('tambahQty')" />

                                @if ($tambahTipe === 'produk')
                                    <x-select label="Produk" wire:model.live="tambahProdukId" :options="$this->produkOptions->pluck('nama', 'id')->all()" :selected="$tambahProdukId" placeholder="— Pilih produk —" :error="$errors->first('tambahProdukId')" class="sm:col-span-2" />
                                    @if (! empty($this->opsiTambah))
                                        <x-select label="Ukuran/opsi" wire:model="tambahOpsi" :options="$this->opsiTambah" :selected="$tambahOpsi" placeholder="— Tanpa opsi —" />
                                    @endif
                                    @if (! empty($this->desainTambah))
                                        <x-select label="Desain" wire:model="tambahDesainId" :options="$this->desainTambah" :selected="$tambahDesainId" placeholder="— Tanpa desain —" />
                                    @endif
                                @else
                                    <x-select label="Paket" wire:model="tambahPaketId" :options="$this->paketOptions->pluck('nama', 'id')->all()" :selected="$tambahPaketId" placeholder="— Pilih paket —" :error="$errors->first('tambahPaketId')" class="sm:col-span-2" />
                                @endif
                            </div>
                            <div class="mt-3">
                                <x-button wire:click="tambahItem" size="sm">
                                    <span wire:loading.remove wire:target="tambahItem">Tambah ke order</span>
                                    <span wire:loading wire:target="tambahItem">Menambah…</span>
                                </x-button>
                            </div>
                        </div>
                    @endunless
                </x-card>

                {{-- Konfirmasi Hari-H (final) --}}
                @unless ($terkunci)
                    @php $bolehHariH = $order->konfirmasi_lokasi_at && $order->konfirmasi_h2_at && $order->tanggal_event; @endphp
                    <x-card title="Konfirmasi Hari-H (final)">
                        <p class="text-sm text-ink-muted">Setelah semua data &amp; item benar, konfirmasi Hari-H. <span class="font-medium text-ink">Order akan dikunci</span> dan tidak bisa diubah lagi, lalu lanjut ke penyelesaian (OTP).</p>
                        <div class="mt-3">
                            <x-button wire:click="konfirmasiHariH" wire:confirm="Konfirmasi Hari-H? Order akan FINAL & terkunci — tidak bisa diubah lagi." variant="primary" :disabled="! $bolehHariH">
                                <span wire:loading.remove wire:target="konfirmasiHariH">Konfirmasi Hari-H &amp; kunci order</span>
                                <span wire:loading wire:target="konfirmasiHariH">Memproses…</span>
                            </x-button>
                        </div>
                        @unless ($bolehHariH)
                            <ul class="mt-2 space-y-1 text-xs text-ink-muted">
                                @if ($order->tanggal_event === null)<li class="text-status-danger">⬜ Tanggal event belum ditentukan.</li>@endif
                                <li>{{ $order->konfirmasi_lokasi_at ? '✅' : '⬜' }} Konfirmasi data sekolah dulu.</li>
                                <li>{{ $order->konfirmasi_h2_at ? '✅' : '⬜' }} Konfirmasi H-2 (oleh admin sales) dulu.</li>
                            </ul>
                        @endunless
                    </x-card>
                @endunless
            @endif

            {{-- Riwayat aktivitas + pihak terlibat --}}
            @include('booking.partials.activity-timeline', ['order' => $order, 'activities' => $this->activities])
        </div>

        {{-- Penyelesaian event (OTP) + sampai kantor --}}
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

                    {{-- Sampai kantor (setelah event selesai) --}}
                    <div class="mt-4 border-t border-line pt-4">
                        @if ($order->sampai_kantor_at)
                            <div class="flex items-center gap-2 text-sm text-status-success">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819" /></svg>
                                <span>Sampai kantor pada <span class="font-medium">{{ $order->sampai_kantor_at->translatedFormat('d M Y · H:i') }}</span>.</span>
                            </div>
                        @else
                            <p class="text-sm text-ink-muted">Sudah kembali ke kantor? Catat waktunya.</p>
                            <x-button wire:click="sampaiKantor" wire:confirm="Catat waktu sampai kantor sekarang?" class="mt-3 w-full">
                                <span wire:loading.remove wire:target="sampaiKantor">Sampai kantor</span>
                                <span wire:loading wire:target="sampaiKantor">Menyimpan…</span>
                            </x-button>
                        @endif
                    </div>
                @elseif (! $terkunci)
                    <p class="text-sm text-ink-muted">Konfirmasi <span class="font-medium text-ink">data sekolah</span> dan <span class="font-medium text-ink">Hari-H (final)</span> dulu — tombol OTP muncul setelah keduanya selesai.</p>
                    <ul class="mt-2 space-y-1 text-xs text-ink-muted">
                        <li>{{ $order->konfirmasi_lokasi_at ? '✅' : '⬜' }} Data sekolah</li>
                        <li>{{ $order->konfirmasi_hh_at ? '✅' : '⬜' }} Hari-H (final)</li>
                    </ul>
                @elseif (! $order->eventOtpActive())
                    <p class="text-sm text-ink-muted">Buat OTP — kode tampil di akun sekolah (guru). Guru akan membacakan kodenya ke Anda.</p>
                    <x-button wire:click="generateOtp" class="mt-3 w-full">
                        <span wire:loading.remove wire:target="generateOtp">Generate OTP untuk guru</span>
                        <span wire:loading wire:target="generateOtp">Membuat…</span>
                    </x-button>
                    @error('otpInput')<p class="mt-2 text-sm text-status-danger">{{ $message }}</p>@enderror
                @else
                    <div class="rounded-lg border border-status-info/20 bg-status-info/10 px-3 py-2 text-xs text-status-info">
                        OTP tampil di akun sekolah. Minta kodenya ke guru, lalu masukkan di bawah.
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

            {{-- Catat pembayaran (tim event di lokasi) --}}
            <x-card title="Pembayaran">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-ink-muted">Total setelah diskon</dt><dd class="text-ink">Rp{{ number_format($order->totalSetelahDiskon(), 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-ink-muted">Sudah dibayar</dt><dd class="text-status-success">Rp{{ number_format($order->totalDibayar(), 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-line pt-1.5"><dt class="font-bold text-ink">Outstanding</dt><dd class="font-bold text-brand-hover">Rp{{ number_format($order->outstanding(), 0, ',', '.') }}</dd></div>
                </dl>

                @if ($order->status !== \App\Support\OrderStatus::BATAL && $order->outstanding() > 0)
                    <div class="mt-4 space-y-3 border-t border-line pt-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-select label="Jenis" wire:model="bayarJenis" :options="\App\Models\OrderPembayaran::JENIS" :selected="$bayarJenis" />
                            <div class="space-y-1.5">
                                <x-input type="number" min="1" :max="$order->outstanding()" label="Nominal (Rp)" wire:model="bayarJumlah" :error="$errors->first('bayarJumlah')" />
                                <p class="text-xs text-ink-muted">Sisa tagihan Rp{{ number_format($order->outstanding(), 0, ',', '.') }}.</p>
                            </div>
                            <x-input type="date" label="Tanggal bayar" wire:model="bayarTanggal" :error="$errors->first('bayarTanggal')" />
                            <div class="space-y-1.5">
                                <span class="block text-sm font-medium text-ink">Bukti bayar (wajib)</span>
                                <input type="file" wire:model="bayarBukti" accept="image/*"
                                       class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
                                @error('bayarBukti')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <p class="text-xs text-ink-muted">Pembayaran baru dihitung setelah disetujui admin sales.</p>
                        <x-button wire:click="catatPembayaran" size="sm">
                            <span wire:loading.remove wire:target="catatPembayaran,bayarBukti">Catat pembayaran</span>
                            <span wire:loading wire:target="catatPembayaran,bayarBukti">Menyimpan…</span>
                        </x-button>
                    </div>
                @endif

                @if ($order->pembayaran->isNotEmpty())
                    @php $badgeVar = ['approved' => 'success', 'pending' => 'pending', 'ditolak' => 'danger']; @endphp
                    <div class="mt-4 space-y-2 border-t border-line pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Riwayat pembayaran</p>
                        @foreach ($order->pembayaran as $p)
                            <div class="rounded-lg border border-line px-3 py-2 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-medium text-ink">Rp{{ number_format($p->jumlah, 0, ',', '.') }}
                                        <span class="text-xs font-normal text-ink-muted">· {{ \App\Models\OrderPembayaran::JENIS[$p->jenis] ?? $p->jenis }}</span>
                                    </span>
                                    <x-badge :variant="$badgeVar[$p->status] ?? 'neutral'">{{ \App\Models\OrderPembayaran::STATUS[$p->status] ?? $p->status }}</x-badge>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-muted">
                                    <span>{{ $p->tanggal_bayar->translatedFormat('d M Y') }}</span>
                                    @if ($p->pencatat)<span>· dicatat {{ $p->pencatat->name }}</span>@endif
                                    @if ($p->penyetuju)<span>· {{ $p->isApproved() ? 'disetujui' : 'ditolak' }} {{ $p->penyetuju->name }}</span>@endif
                                    @if ($p->bukti_path)<a href="{{ \Illuminate\Support\Facades\Storage::url($p->bukti_path) }}" target="_blank" class="font-medium text-brand-hover hover:underline">lihat bukti</a>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card title="Lacak status pesanan">
                <x-order-tracking :order="$order" />
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
            </x-card>
        </div>
    </div>
</div>
