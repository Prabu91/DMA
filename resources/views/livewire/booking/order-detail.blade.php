@php
    $order = $this->order;
    $sf = $konteks === 'sekolah';
@endphp
<div>
    @unless ($sf)<x-bukti-viewer />@endunless
    {{-- Breadcrumb (panel staf) --}}
    @unless ($sf)
        <x-breadcrumb :items="[
            ['label' => 'Dashboard', 'url' => route('app.dashboard')],
            ['label' => 'Order', 'url' => route('app.order.index')],
            ['label' => $order->booking_code ?? 'Order #'.$order->id],
        ]" />
    @endunless

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <h1 class="{{ $sf ? 'text-2xl font-extrabold tracking-tight text-ink' : 'text-lg font-medium text-ink' }}">{{ $sf ? 'Booking tersimpan' : ($order->booking_code ?? 'Order #'.$order->id) }}</h1>
            <p class="text-sm text-ink-muted">{{ $order->sekolah?->nama }}@unless ($sf) · <span class="font-mono">{{ $order->sekolah?->id_sekolah }}</span>@endunless · {{ optional($order->tanggal_booking)->translatedFormat('d M Y H:i') }}</p>
        </div>
        <a href="{{ $this->kembaliUrl() }}" wire:navigate class="text-sm font-semibold text-ink-muted hover:text-ink">Selesai</a>
    </div>

    {{-- Order susulan: tunjukkan induknya dengan jelas --}}
    @if ($order->isSusulan())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/30 bg-brand/5 px-4 py-3">
            <div class="flex items-center gap-3">
                <x-badge variant="brand">Susulan</x-badge>
                <p class="text-sm text-ink">
                    Order susulan dari
                    @if ($order->induk)
                        @if ($sf)
                            <a href="{{ route('sekolah.riwayat.show', $order->induk->id) }}" wire:navigate class="font-medium text-brand hover:text-brand-hover">{{ $order->induk->booking_code ?? 'order #'.$order->induk->id }}</a>
                        @else
                            <a href="{{ route('app.order.show', $order->induk->id) }}" wire:navigate class="font-medium text-brand hover:text-brand-hover">{{ $order->induk->booking_code ?? 'order #'.$order->induk->id }}</a>
                        @endif
                        @if ($order->induk->tanggal_event)<span class="text-ink-muted"> · event {{ $order->induk->tanggal_event->translatedFormat('d M Y') }}</span>@endif
                        @if ($order->induk->trashed())<span class="text-ink-muted"> (sudah dihapus)</span>@endif
                    @else
                        <span class="text-ink-muted">order yang sudah dihapus permanen</span>
                    @endif
                </p>
            </div>
            @unless ($sf)
                <span class="text-xs text-ink-muted">Langsung ke hari event · tanpa H-7/H-2 · tanpa item free</span>
            @endunless
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Perlu tindakan (staf): sorot yang belum dilakukan --}}
            @unless ($sf)
                @php
                    $todo = [];
                    if ($order->status !== \App\Support\OrderStatus::BATAL) {
                        $pemb = $order->pembayaran;
                        if ($pemb->isEmpty()) {
                            $todo[] = 'Belum input DP';
                        } elseif ($pemb->where('status', \App\Models\OrderPembayaran::STATUS_PENDING)->isNotEmpty()) {
                            $todo[] = 'Ada pembayaran menunggu approval admin sales';
                        }
                        if ($order->tanggal_event && $order->timEvent->isEmpty()) {
                            $todo[] = 'Belum assign tim event';
                        }
                        foreach ($order->milestones() as $m) {
                            if ($m['state'] === 'overdue') {
                                $todo[] = 'Milestone '.$m['label'].' terlewat — segera konfirmasi';
                            }
                        }
                    }
                @endphp
                @if ($todo !== [])
                    <div class="rounded-xl border border-status-pending/30 bg-status-pending/10 p-4">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-status-pending" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            <h3 class="text-sm font-bold text-ink">Perlu tindakan</h3>
                        </div>
                        <ul class="mt-2 space-y-1 pl-7 text-sm text-ink">
                            @foreach ($todo as $t)
                                <li class="list-disc">{{ $t }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endunless

            {{-- Status / tiket — untuk client disembunyikan sampai kode booking/QR terbit --}}
            @if ($order->booking_code || ! $sf)
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
            @endif

            {{-- Item (diletakkan di atas agar rincian pesanan langsung terlihat) --}}
            @php
                // Harga item boleh dikoreksi admin sales/super admin walau order
                // sudah terkunci — kunci menjaga jadwal & susunan item, bukan
                // melindungi salah ketik harga.
                $bisaUbahHarga = ! $sf && $this->isAdminSales && $order->status !== \App\Support\OrderStatus::BATAL;
            @endphp
            <x-card title="Item" padding="p-0">
                @if ($this->tampilQc)
                    @php
                        $qcE = $order->qcProgress('event');
                        $qcA = $order->qcProgress('admin');
                    @endphp
                    <x-slot name="actions">
                        <div class="flex flex-wrap justify-end gap-1.5">
                            <x-badge :variant="$qcE['done'] === $qcE['total'] ? 'success' : 'pending'">Tim event {{ $qcE['done'] }}/{{ $qcE['total'] }}</x-badge>
                            <x-badge :variant="$qcA['done'] === $qcA['total'] ? 'success' : 'neutral'">Admin {{ $qcA['done'] }}/{{ $qcA['total'] }}</x-badge>
                        </div>
                    </x-slot>
                @endif
                @foreach ($this->paidItems as $item)
                    <div wire:key="item-{{ $item->id }}" class="border-b border-line px-5 py-3 last:border-b-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-badge :variant="$item->tipe_item === 'paket' ? 'brand' : 'neutral'">{{ ucfirst($item->tipe_item) }}</x-badge>
                                    <span class="text-sm {{ $sf ? 'font-bold' : 'font-medium' }} text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-ink-muted">
                                    @if ($item->desain) Desain {{ $item->desain->kode }} · @endif
                                    @if ($item->opsi_ukuran) {{ $item->opsi_ukuran }} · @endif
                                    {{ $item->qty }} × Rp{{ number_format($item->harga, 0, ',', '.') }}
                                </div>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-0.5">
                                <span class="text-sm font-medium text-ink">Rp{{ number_format($item->harga * $item->qty, 0, ',', '.') }}</span>
                                @if ($bisaUbahHarga && $hargaEditItemId !== $item->id)
                                    <button type="button" wire:click="mulaiEditHarga({{ $item->id }})"
                                            class="text-xs font-medium text-brand hover:text-brand-hover">Ubah harga</button>
                                @endif
                            </div>
                        </div>

                        @if ($bisaUbahHarga && $hargaEditItemId === $item->id)
                            <div class="mt-3 rounded-lg border border-line bg-page/50 p-3">
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="w-36 shrink-0">
                                        <x-input type="number" min="0" step="1000" label="Harga satuan (Rp)"
                                                 wire:model="hargaBaru" :error="$errors->first('hargaBaru')" />
                                    </div>
                                    <x-button size="sm" wire:click="simpanHarga">
                                        <span wire:loading.remove wire:target="simpanHarga">Simpan</span>
                                        <span wire:loading wire:target="simpanHarga">Menyimpan…</span>
                                    </x-button>
                                    <x-button size="sm" variant="secondary" wire:click="batalEditHarga">Batal</x-button>
                                </div>
                                <p class="mt-2 text-xs text-ink-muted">Total order &amp; sisa tagihan ikut dihitung ulang, dan perubahan tercatat di riwayat aktivitas.</p>
                            </div>
                        @endif
                        @if ($this->tampilQc)
                            @include('booking.partials.qc-item', ['item' => $item])
                        @endif
                    </div>
                @endforeach

                @foreach ($this->freeItems as $item)
                    <div wire:key="item-{{ $item->id }}" class="border-b border-line px-5 py-3 last:border-b-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge variant="success">Free</x-badge>
                                <span class="text-sm text-ink">{{ $item->produk?->nama ?? 'Produk' }}</span>
                                @if ($item->opsi_ukuran)<span class="text-xs text-ink-muted">{{ $item->opsi_ukuran }}</span>@endif
                                <span class="text-xs text-ink-muted">×{{ $item->qty }}</span>
                            </div>
                            <div class="shrink-0 text-sm font-medium text-status-success">Rp0</div>
                        </div>
                        @if ($this->tampilQc)
                            @include('booking.partials.qc-item', ['item' => $item])
                        @endif
                    </div>
                @endforeach

                @if (! $sf && $hargaMsg)
                    <p class="border-t border-line px-5 py-3 text-sm font-medium text-status-success">{{ $hargaMsg }}</p>
                @endif
            </x-card>

            {{-- Redaksi & folder kerja editor (staf) --}}
            @unless ($sf)
                @include('booking.partials.redaksi-folder', ['order' => $order])
            @endunless

            {{-- Visual tracking status order — hanya panel staf; disembunyikan dari portal sekolah (client) untuk sementara --}}
            @unless ($sf)
                <x-card title="Lacak status pesanan">
                    <x-order-tracking :order="$order" />
                </x-card>
            @endunless

            {{-- Order susulan milik order ini (staf) --}}
            @if (! $sf && ! $order->isSusulan() && ($order->susulan->isNotEmpty() || $this->bisaBuatSusulan))
                <x-card title="Order susulan">
                    <x-slot name="subtitle">Siswa yang difoto belakangan dibuat sebagai order baru yang tertaut ke order ini.</x-slot>

                    @if ($order->susulan->isNotEmpty())
                        <div class="-mx-5 mb-4 border-y border-line">
                            @foreach ($order->susulan as $sus)
                                <a href="{{ route('app.order.show', $sus->id) }}" wire:navigate wire:key="sus-{{ $sus->id }}"
                                   class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-2.5 last:border-b-0 hover:bg-page">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-ink">{{ $sus->booking_code ?? 'Order #'.$sus->id }}</div>
                                        <div class="text-xs text-ink-muted">
                                            {{ $sus->tanggal_event ? 'Event '.$sus->tanggal_event->translatedFormat('d M Y') : 'Tanggal event belum diisi' }}
                                            · {{ (int) $sus->jumlah_siswa }} siswa
                                            · Rp{{ number_format((int) $sus->total, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-badge :variant="\App\Support\OrderStatus::badge($sus->status)">{{ $sus->statusLabel() }}</x-badge>
                                        @if ($sus->event_status === \App\Support\OrderStatus::EVENT_SELESAI)<x-badge variant="success">Event selesai</x-badge>@endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="mb-4 text-sm text-ink-muted">Belum ada order susulan.</p>
                    @endif

                    @if ($this->bisaBuatSusulan)
                        <x-confirm action="buatSusulan" title="Buat order susulan"
                                   message="Keranjang Anda akan dikosongkan lalu dikunci ke sekolah {{ $order->sekolah?->nama }}. Pilih item susulan di etalase seperti biasa. Lanjutkan?"
                                   confirm-label="Ya, buat susulan" variant="secondary" confirm-variant="primary" size="sm">+ Buat order susulan</x-confirm>
                    @endif
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
                        <x-confirm action="simpanJadwal" title="Simpan jadwal" message="Simpan perubahan jadwal event?">Simpan jadwal</x-confirm>
                        @if ($jadwalMsg)
                            <span class="flex min-h-[44px] items-center text-sm font-medium text-status-success">{{ $jadwalMsg }}</span>
                        @endif
                    </div>
                </x-card>
            @endunless

            {{-- Pembayaran — dikelola staf --}}
            @unless ($sf)
                @php $st = $order->status ?: 'baru'; @endphp
                <x-card title="Pembayaran">
                    <div class="flex items-center gap-2">
                        <x-badge :variant="\App\Support\OrderStatus::badge($st)">{{ \App\Support\OrderStatus::label($st) }}</x-badge>
                        <span class="text-xs text-ink-muted">status mengikuti pembayaran otomatis</span>
                    </div>

                    {{-- Ringkasan finansial --}}
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-muted">Total</dt><dd class="text-ink">Rp{{ number_format($order->total, 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Diskon</dt><dd class="text-ink">Rp{{ number_format($order->totalDiskon(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-medium text-ink">Total setelah diskon</dt><dd class="font-medium text-ink">Rp{{ number_format($order->totalSetelahDiskon(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-ink-muted">Sudah dibayar</dt><dd class="text-status-success">Rp{{ number_format($order->totalDibayar(), 0, ',', '.') }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2"><dt class="font-bold text-ink">Outstanding</dt><dd class="font-bold text-brand-hover">Rp{{ number_format($order->outstanding(), 0, ',', '.') }}</dd></div>
                    </dl>

                    {{-- Catat pembayaran --}}
                    @unless ($st === 'batal')
                        <div class="mt-5 border-t border-line pt-4">
                            <h3 class="mb-3 text-sm font-medium text-ink">Catat pembayaran</h3>
                            @if ($order->outstanding() <= 0)
                                <p class="text-sm text-ink-muted">Tagihan sudah terbayar penuh (disetujui). Tidak ada sisa untuk dicatat.</p>
                            @else
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-select label="Jenis" wire:model="bayarJenis" :options="\App\Models\OrderPembayaran::JENIS" :selected="$bayarJenis" />
                                    <x-input type="number" min="1" :max="$order->outstanding()" label="Nominal (Rp)" wire:model="bayarJumlah" :error="$errors->first('bayarJumlah')" placeholder="mis. 500000" hint="Sisa tagihan Rp{{ number_format($order->outstanding(), 0, ',', '.') }}" />
                                    <x-input type="date" label="Tanggal bayar" wire:model="bayarTanggal" :error="$errors->first('bayarTanggal')" />
                                    <div class="space-y-1.5">
                                        <span class="block text-sm font-medium text-ink">Bukti bayar <span class="text-status-danger">(wajib)</span></span>
                                        <input type="file" wire:model="bayarBukti" accept="image/*"
                                               class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
                                        @error('bayarBukti')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-3">
                                    <x-button wire:click="catatPembayaran" size="sm">
                                        <span wire:loading.remove wire:target="catatPembayaran,bayarBukti">Catat pembayaran</span>
                                        <span wire:loading wire:target="catatPembayaran,bayarBukti">Menyimpan…</span>
                                    </x-button>
                                    @if ($bayarMsg)<span class="text-sm font-medium text-status-success">{{ $bayarMsg }}</span>@endif
                                </div>
                                <p class="mt-2 text-xs text-ink-muted">Pembayaran baru dihitung setelah <span class="font-medium">disetujui admin sales</span>.</p>
                            @endif
                        </div>
                    @endunless

                    {{-- Riwayat pembayaran --}}
                    @if ($order->pembayaran->isNotEmpty())
                        <div class="mt-5 border-t border-line pt-4">
                            <h3 class="mb-2 text-sm font-medium text-ink">Riwayat pembayaran</h3>
                            <div class="space-y-2">
                                @foreach ($order->pembayaran as $p)
                                    @php $badgeVar = ['approved' => 'success', 'pending' => 'pending', 'ditolak' => 'danger'][$p->status] ?? 'neutral'; @endphp
                                    <div class="rounded-lg border border-line px-3 py-2.5">
                                        @if ($this->isAdminSales && $editPembayaranId === $p->id)
                                            {{-- Form edit inline --}}
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                <x-select label="Jenis" wire:model="editJenis" :options="\App\Models\OrderPembayaran::JENIS" :selected="$editJenis" />
                                                <x-input type="number" min="1" label="Nominal (Rp)" wire:model="editJumlah" :error="$errors->first('editJumlah')" />
                                                <x-input type="date" label="Tanggal bayar" wire:model="editTanggal" :error="$errors->first('editTanggal')" />
                                                <div class="space-y-1.5">
                                                    <span class="block text-sm font-medium text-ink">Ganti bukti (opsional)</span>
                                                    <input type="file" wire:model="editBukti" accept="image/*"
                                                           class="block w-full text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-page file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-line">
                                                    @error('editBukti')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs text-ink-muted">Menyimpan edit akan mengembalikan status ke <span class="font-medium">menunggu approval</span>.</p>
                                            <div class="mt-2 flex items-center gap-2">
                                                <x-button wire:click="simpanEditPembayaran" size="sm">Simpan</x-button>
                                                <x-button wire:click="batalEditPembayaran" variant="ghost" size="sm">Batal</x-button>
                                            </div>
                                        @else
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-sm font-medium text-ink">Rp{{ number_format($p->jumlah, 0, ',', '.') }}</span>
                                                        <x-badge variant="neutral">{{ \App\Models\OrderPembayaran::JENIS[$p->jenis] ?? $p->jenis }}</x-badge>
                                                        <x-badge :variant="$badgeVar">{{ \App\Models\OrderPembayaran::STATUS[$p->status] ?? $p->status }}</x-badge>
                                                    </div>
                                                    <div class="mt-0.5 text-xs text-ink-muted">
                                                        {{ optional($p->tanggal_bayar)->translatedFormat('d M Y') }}
                                                        · dicatat {{ $p->pencatat?->nama ?? $p->pencatat?->name ?? '—' }}
                                                        @if ($p->penyetuju) · {{ $p->status === 'ditolak' ? 'ditolak' : 'disetujui' }} {{ $p->penyetuju->nama ?? $p->penyetuju->name }}@endif
                                                        @if ($p->bukti_path) · <button type="button" x-on:click="$dispatch('show-bukti', { url: '{{ route('app.bukti-bayar', $p->id) }}' })" class="font-medium text-brand hover:text-brand-hover">lihat bukti</button>@endif
                                                    </div>
                                                </div>
                                                @if ($this->isAdminSales)
                                                    <div class="flex shrink-0 items-center gap-1.5">
                                                        @if ($p->status === 'pending')
                                                            <x-confirm action="approvePembayaran" :arg="$p->id" title="Setujui pembayaran" message="Pastikan dana benar-benar masuk & bukti sah. Setujui pembayaran ini?" confirm-label="Ya, setujui" size="sm">Setujui</x-confirm>
                                                            <x-confirm action="tolakPembayaran" :arg="$p->id" title="Tolak pembayaran" message="Tolak pembayaran ini?" confirm-label="Ya, tolak" variant="ghost" confirm-variant="danger" size="sm">Tolak</x-confirm>
                                                        @endif
                                                        <x-button wire:click="editPembayaran({{ $p->id }})" variant="secondary" size="sm">Edit</x-button>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Batal / aktifkan --}}
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                        @if ($st === 'batal')
                            <x-confirm action="ubahStatus" arg="baru" title="Aktifkan kembali" message="Aktifkan kembali order ini?" variant="secondary" confirm-variant="primary" size="sm">Aktifkan kembali</x-confirm>
                        @else
                            <x-confirm action="ubahStatus" arg="batal" title="Batalkan order" message="Batalkan order ini? Tindakan ini menghentikan proses order." variant="danger" size="sm">Batalkan order</x-confirm>
                        @endif
                        @if ($statusMsg)<span class="text-sm font-medium text-status-success">{{ $statusMsg }}</span>@endif
                    </div>
                </x-card>

                {{-- Diskon per item — marketing ajukan, admin sales setujui/ubah --}}
                <x-card title="Diskon per produk">
                    @php $ds = $order->diskon_status; @endphp
                    <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
                        @if ($ds === \App\Models\Order::DISKON_DISETUJUI)
                            <x-badge variant="success">Disetujui</x-badge><span class="text-ink">Total diskon Rp{{ number_format($order->totalDiskon(), 0, ',', '.') }}</span>
                        @elseif ($ds === \App\Models\Order::DISKON_DIAJUKAN)
                            <x-badge variant="pending">Diajukan</x-badge><span class="text-ink-muted">menunggu persetujuan admin sales</span>
                        @elseif ($ds === \App\Models\Order::DISKON_DITOLAK)
                            <x-badge variant="danger">Ditolak</x-badge>
                        @else
                            <span class="text-ink-muted">Belum ada diskon.</span>
                        @endif
                    </div>

                    @php
                        // Admin sales: boleh set/ubah selama belum disetujui. Marketing: hanya saat belum diajukan.
                        $editable = ! $sf && $st !== 'batal' && (
                            $this->isAdminSales
                                ? $ds !== \App\Models\Order::DISKON_DISETUJUI
                                : ! in_array($ds, [\App\Models\Order::DISKON_DIAJUKAN, \App\Models\Order::DISKON_DISETUJUI], true)
                        );
                    @endphp

                    <div class="space-y-2">
                        @foreach ($order->items->where('is_free', false) as $item)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line px-3 py-2">
                                <div class="min-w-0">
                                    <div class="text-sm text-ink">{{ $item->produk?->nama ?? $item->paket?->nama }}</div>
                                    <div class="text-xs text-ink-muted">Harga Rp{{ number_format($item->harga, 0, ',', '.') }} × {{ $item->qty }} · efektif Rp{{ number_format($item->hargaEfektif(), 0, ',', '.') }}</div>
                                </div>
                                <div class="w-40">
                                    @if ($editable)
                                        <x-input type="number" min="0" :max="$item->harga" label="Diskon/satuan" wire:model="diskonItem.{{ $item->id }}" :error="$errors->first('diskonItem.'.$item->id)" />
                                    @else
                                        <div class="text-right text-sm text-ink">Rp{{ number_format($item->diskon, 0, ',', '.') }}<span class="text-xs text-ink-muted">/satuan</span></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($editable)
                        <div class="mt-4 flex items-center gap-2">
                            @if ($this->isAdminSales)
                                <x-confirm action="setujuiDiskon" title="Terapkan diskon" message="Terapkan diskon per item ini?" size="sm">{{ $ds === \App\Models\Order::DISKON_DIAJUKAN ? 'Setujui' : 'Terapkan diskon' }}</x-confirm>
                                @if ($ds === \App\Models\Order::DISKON_DIAJUKAN)
                                    <x-confirm action="tolakDiskon" title="Tolak diskon" message="Tolak pengajuan diskon?" variant="ghost" confirm-variant="danger" size="sm">Tolak</x-confirm>
                                @endif
                            @else
                                <x-button wire:click="ajukanDiskon" size="sm" variant="secondary">Ajukan diskon</x-button>
                            @endif
                        </div>
                    @endif

                    @if ($diskonMsg)<p class="mt-3 text-sm font-medium text-status-success">{{ $diskonMsg }}</p>@endif
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
                                @php $isHari = $m['key'] === 'hh'; @endphp
                                <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2.5">
                                    <div>
                                        <div class="text-sm font-medium text-ink">{{ $m['label'] }}
                                            @if ($isHari)<span class="text-xs font-normal text-ink-muted">· oleh tim event di lokasi</span>@endif
                                        </div>
                                        <div class="text-xs text-ink-muted">
                                            {{ $m['due']->translatedFormat('d M Y') }}
                                            @if ($m['state'] === 'confirmed') · dikonfirmasi {{ $m['confirmedAt']->translatedFormat('d M') }}@if ($m['oleh']) oleh {{ $m['oleh']->name }}@endif @endif
                                        </div>
                                        @if ($m['state'] === 'locked')
                                            <div class="mt-0.5 text-xs text-ink-muted/80">
                                                @if ($m['key'] === 'h7') Terkunci — DP harus disetujui dulu.
                                                @elseif ($m['key'] === 'h2') Terkunci — konfirmasi H-7 dulu.
                                                @else Terkunci — konfirmasi H-2 dulu.
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($m['state'] === 'confirmed')
                                            <x-badge variant="success">Terkonfirmasi</x-badge>
                                        @elseif ($m['state'] === 'locked')
                                            <x-badge variant="neutral">🔒 Terkunci</x-badge>
                                        @elseif ($isHari)
                                            {{-- Hari-H hanya dikonfirmasi tim event → read-only di panel staf --}}
                                            @if ($m['state'] === 'overdue')<x-badge variant="danger">Terlewat</x-badge>@else<x-badge variant="neutral">Menunggu tim event</x-badge>@endif
                                        @elseif ($this->isAdminSales && ! $order->terkunciUntuk(auth()->user()))
                                            @if ($m['state'] === 'overdue')<x-badge variant="danger">Terlewat</x-badge>@endif
                                            <x-confirm action="konfirmasiMilestone" arg="{{ $m['key'] }}" title="Konfirmasi {{ $m['label'] }}" message="Konfirmasi milestone {{ $m['label'] }} sekarang?" variant="secondary" confirm-variant="primary" size="sm">Konfirmasi</x-confirm>
                                        @else
                                            {{-- Marketing / non-admin sales: read-only --}}
                                            @if ($m['state'] === 'overdue')<x-badge variant="danger">Terlewat</x-badge>@else<x-badge variant="neutral">Menunggu admin</x-badge>@endif
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($order->isSusulan())
                            <p class="mt-3 text-xs text-ink-muted">Order susulan langsung ke hari event — tidak ada konfirmasi H-7 &amp; H-2.</p>
                        @elseif (! $this->isAdminSales)
                            <p class="mt-3 text-xs text-ink-muted">Konfirmasi H-7 &amp; H-2 dilakukan oleh admin area/sales.</p>
                        @endif

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
                        @if ($order->steTersedia())
                            <a href="{{ route('app.order.ste', $order->id) }}" target="_blank"
                               class="text-sm font-medium text-brand hover:text-brand-hover">Cetak STE →</a>
                        @else
                            <span class="text-sm text-ink-muted" title="STE terbit setelah konfirmasi H-2">STE tersedia setelah H-2</span>
                        @endif
                    </x-slot>

                    @if ($this->bisaAssignTimEvent)
                        {{-- Admin: bisa assign --}}
                        @if ($this->timEventOptions->isEmpty())
                            <p class="text-sm text-ink-muted">Belum ada anggota tim event di cabang ini.</p>
                        @else
                            <div x-data="{ q: '' }" class="space-y-2">
                                <div class="relative">
                                    <svg class="pointer-events-none absolute inset-y-0 left-3 my-auto h-4 w-4 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                                    <input type="search" x-model="q" placeholder="Cari nama tim event…"
                                           class="block w-full rounded-lg border border-line bg-card py-2 pl-9 pr-3 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                                </div>
                                <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-line p-2">
                                    @foreach ($this->timEventOptions as $u)
                                        <label x-show="q === '' || {{ \Illuminate\Support\Js::from(mb_strtolower($u->nama ?? $u->name)) }}.includes(q.trim().toLowerCase())"
                                               class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-page">
                                            <input type="checkbox" wire:model="timEventTerpilih" value="{{ $u->id }}"
                                                   class="h-4 w-4 rounded border-line text-brand focus:ring-2 focus:ring-brand/30">
                                            <span class="text-sm text-ink">{{ $u->nama ?? $u->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-4 flex items-center gap-3">
                                <x-confirm action="simpanTimEvent" message="Simpan penugasan tim event ini?" size="sm">Simpan tim</x-confirm>
                                @if ($timMsg)<span class="text-sm font-medium text-status-success">{{ $timMsg }}</span>@endif
                            </div>
                        @endif
                    @else
                        {{-- Marketing/lainnya: read-only, hanya lihat siapa tim eventnya --}}
                        @if ($order->timEvent->isEmpty())
                            <p class="text-sm text-ink-muted">Belum ada tim event yang ditugaskan. <span class="text-ink-muted/70">Penugasan dilakukan oleh admin.</span></p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($order->timEvent as $u)
                                    <li class="flex items-center gap-2 text-sm text-ink">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand/10 text-xs font-bold text-brand">{{ mb_substr($u->nama ?? $u->name, 0, 1) }}</span>
                                        {{ $u->nama ?? $u->name }}
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-3 text-xs text-ink-muted">Penugasan tim event dikelola oleh admin.</p>
                        @endif
                    @endif
                </x-card>
            @endunless

            {{-- Catatan staf (utas) — internal, tidak terlihat sekolah --}}
            @unless ($sf)
                <x-card>
                    <x-slot name="title">Catatan</x-slot>
                    <x-slot name="subtitle">Catatan internal antar staf untuk order ini. Tidak terlihat oleh sekolah.</x-slot>

                    @if ($catatanMsg)
                        <p class="mb-3 text-sm font-medium text-status-success">{{ $catatanMsg }}</p>
                    @endif

                    @if ($this->catatan->isEmpty())
                        <p class="text-sm text-ink-muted">Belum ada catatan.</p>
                    @else
                        <ul class="mb-4 space-y-3">
                            @foreach ($this->catatan as $c)
                                @php $milikSendiri = $c->user_id === auth('web')->id(); @endphp
                                <li wire:key="catatan-{{ $c->id }}" class="rounded-xl border border-line bg-page/50 p-3">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="text-sm font-medium text-ink">{{ $c->namaPenulis() }}</span>
                                        <span class="text-xs text-ink-muted">{{ $c->created_at->translatedFormat('d M Y H:i') }}</span>
                                        @if ($milikSendiri || auth('web')->user()?->hasRole('super_admin'))
                                            <x-confirm action="hapusCatatan" :arg="$c->id" variant="ghost" size="sm"
                                                       confirm-variant="danger" confirm-label="Ya, hapus"
                                                       class="ml-auto"
                                                       title="Hapus catatan" message="Catatan ini akan dihapus permanen. Lanjutkan?">Hapus</x-confirm>
                                        @endif
                                    </div>
                                    <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $c->isi }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="space-y-2">
                        <textarea wire:model="catatanBaru" rows="3" placeholder="Tulis catatan untuk order ini…"
                                  class="block w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-ink placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"></textarea>
                        @error('catatanBaru')<p class="text-xs text-status-danger">{{ $message }}</p>@enderror
                        <x-button wire:click="tambahCatatan" variant="secondary" size="sm">
                            <span wire:loading.remove wire:target="tambahCatatan">Tambah catatan</span>
                            <span wire:loading wire:target="tambahCatatan">Menyimpan…</span>
                        </x-button>
                    </div>
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
