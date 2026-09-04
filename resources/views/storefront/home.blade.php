<x-storefront-layout>
    {{-- ══ HERO (navy) ══ --}}
    <section class="relative overflow-hidden bg-navy-900">
        <div class="pointer-events-none absolute -right-10 -top-8 h-72 w-72 rounded-full border-2 border-white/[0.06]"></div>
        <div class="pointer-events-none absolute right-20 top-16 h-44 w-44 rounded-full border-2 border-brand/15"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
            <div class="max-w-2xl">
                <div class="mb-4 text-xs font-extrabold tracking-[0.18em] text-brand">DOKUMENTASI SEKOLAH — SEJAK 2018</div>
                <h1 class="text-4xl font-extrabold leading-[0.98] tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Satu hari.<br>Seribu <span class="text-brand">senyuman.</span><br>Selamanya.
                </h1>
                <p class="mt-5 max-w-md text-sm leading-relaxed text-white/65 sm:text-base">
                    Booking paket foto wisuda, manasik, hingga pas foto untuk seluruh angkatan dalam satu reservasi.
                    Gratis paket sekolah bila kuota siswa tercapai.
                </p>

                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="{{ route('storefront.katalog.index') }}"
                       class="inline-flex min-h-[48px] items-center gap-2 rounded-lg bg-brand px-5 text-sm font-extrabold text-white transition-colors hover:bg-brand-hover">
                        Mulai booking <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('sekolah.daftar') }}"
                       class="inline-flex min-h-[48px] items-center rounded-lg border-[1.5px] border-white/35 px-5 text-sm font-extrabold text-white transition-colors hover:bg-white/10">
                        Daftar sekolah
                    </a>
                </div>

                <dl class="mt-9 flex flex-wrap gap-8">
                    @foreach ([['240+', 'SEKOLAH TERDAFTAR'], ['58K', 'SISWA DIABADIKAN'], ['9', 'KOTA LAYANAN']] as [$angka, $label])
                        <div>
                            <dt class="text-2xl font-extrabold text-white">{{ $angka }}</dt>
                            <dd class="text-[11px] tracking-wide text-white/50">{{ $label }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>

    {{-- ══ KATEGORI POPULER (tab bergambar ala Jonas) ══ --}}
    @if ($kategoriList->isNotEmpty())
        <section id="katalog" x-data="{ tab: {{ $kategoriList->first()->id }} }" class="scroll-mt-20 bg-card">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <div class="mb-5 flex items-end justify-between gap-3">
                    <div>
                        <div class="text-xs font-extrabold tracking-[0.16em] text-brand">KATEGORI POPULER</div>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">Pilih kategori paket</h2>
                    </div>
                    <a href="{{ route('storefront.katalog.index') }}" class="hidden shrink-0 text-sm font-bold text-brand hover:text-brand-hover sm:inline">Lihat semua →</a>
                </div>

                {{-- Tab kategori (thumbnail) --}}
                <div class="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($kategoriList as $kategori)
                        @php $thumb = optional($kategori->produk->first())->foto; @endphp
                        <button type="button" @click="tab = {{ $kategori->id }}"
                                :class="tab === {{ $kategori->id }} ? 'border-brand ring-2 ring-brand/25' : 'border-line hover:border-brand/40'"
                                class="flex w-24 shrink-0 flex-col items-center gap-2 rounded-xl border bg-card p-2.5 text-center transition">
                            <span class="grid h-16 w-full place-items-center overflow-hidden rounded-lg bg-page">
                                @if ($thumb)
                                    <img src="{{ asset('storage/'.$thumb) }}" alt="" class="max-h-full max-w-full object-contain">
                                @else
                                    <svg class="h-7 w-7 text-ink-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                                @endif
                            </span>
                            <span class="line-clamp-2 text-[11px] font-bold leading-tight text-ink">{{ $kategori->nama }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Produk kategori terpilih --}}
                @foreach ($kategoriList as $kategori)
                    <div x-show="tab === {{ $kategori->id }}" x-cloak class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($kategori->produk as $produk)
                            <a href="{{ route('storefront.katalog.detail', ['tipe' => 'produk', 'id' => $produk->id]) }}"
                               class="flex flex-col overflow-hidden rounded-xl border border-line bg-card transition-colors hover:border-brand/40">
                                <div class="flex aspect-square w-full items-center justify-center overflow-hidden bg-page">
                                    @if ($produk->foto)
                                        <img src="{{ asset('storage/'.$produk->foto) }}" alt="" class="max-h-full max-w-full object-contain">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-ink-muted">
                                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path('product') }}" /></svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-sm font-semibold text-ink">{{ $produk->nama }}</span>
                                        @if ($produk->frame)<span class="rounded bg-page px-1.5 py-0.5 text-[9px] font-bold text-ink-muted">{{ $produk->frame }}</span>@endif
                                    </div>
                                    <div class="mt-1 text-sm font-extrabold text-navy">Rp{{ number_format($produk->harga, 0, ',', '.') }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                <a href="{{ route('storefront.katalog.index') }}"
                   class="mt-6 inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-navy-900 px-5 text-sm font-extrabold text-white transition-colors hover:bg-navy">
                    Lihat semua paket <span aria-hidden="true">→</span>
                </a>
            </div>
        </section>
    @endif

    {{-- ══ PAKET POPULER (carousel Prev/Next ala Jonas) ══ --}}
    @if ($paketUnggulan->isNotEmpty())
        <section class="border-t border-line bg-page" x-data="{ scroll(dir) { this.$refs.track.scrollBy({ left: dir * 280, behavior: 'smooth' }); } }">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <div class="mb-5 flex items-end justify-between gap-3">
                    <div>
                        <div class="text-xs font-extrabold tracking-[0.16em] text-brand">JANGAN LEWATKAN</div>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">Paket populer</h2>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="scroll(-1)" aria-label="Sebelumnya" class="grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-ink transition-colors hover:border-brand/40 hover:text-brand">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                        </button>
                        <button type="button" @click="scroll(1)" aria-label="Berikutnya" class="grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-ink transition-colors hover:border-brand/40 hover:text-brand">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                        </button>
                    </div>
                </div>

                <div x-ref="track" class="-mx-4 flex snap-x scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:scroll-px-0 sm:px-0 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($paketUnggulan as $paket)
                        <a href="{{ route('storefront.katalog.detail', ['tipe' => 'paket', 'id' => $paket->id]) }}"
                           class="flex w-60 shrink-0 snap-start flex-col overflow-hidden rounded-xl border border-line bg-card transition-colors hover:border-brand/40">
                            <div class="relative grid h-28 place-items-center bg-gradient-to-br from-navy to-navy-hover">
                                <svg class="h-9 w-9 text-white/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                <span class="absolute left-2.5 top-2.5 rounded bg-brand px-2 py-0.5 text-[9px] font-extrabold tracking-wide text-white">PAKET</span>
                            </div>
                            <div class="flex flex-1 flex-col p-3.5">
                                <div class="text-sm font-extrabold text-ink">{{ $paket->nama }}</div>
                                <div class="mt-0.5 line-clamp-2 text-xs text-ink-muted">{{ $paket->deskripsi ?: $paket->produk_count.' produk' }}</div>
                                <div class="mt-3 flex items-baseline justify-between">
                                    <span class="text-base font-extrabold text-navy">Rp{{ number_format($paket->harga, 0, ',', '.') }}</span>
                                    <span class="text-[10px] font-extrabold text-brand">BOOKING →</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══ CARA PESAN ══ --}}
    <section id="cara-pesan" class="scroll-mt-20 border-t border-line bg-card">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <h2 class="text-2xl font-extrabold tracking-tight text-ink">Cara pesan</h2>
            <p class="mt-1 text-sm text-ink-muted">Empat langkah dari telusuri hingga pesanan masuk.</p>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $langkah = [
                        ['Telusuri katalog', 'Pilih paket atau produk satuan sesuai kebutuhan sekolah.'],
                        ['Pilih desain & ukuran', 'Tentukan desain tahun ajaran aktif dan opsi ukuran.'],
                        ['Masuk & checkout', 'Login atau daftar saat checkout — keranjang tetap tersimpan.'],
                        ['Pesanan diproses', 'Tim marketing cabang menindaklanjuti pesanan Anda.'],
                    ];
                @endphp
                @foreach ($langkah as $i => [$judul, $isi])
                    <div class="flex flex-col rounded-xl border border-line bg-page p-4">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-navy-900 text-sm font-extrabold text-white">{{ $i + 1 }}</span>
                        <div class="mt-3 text-sm font-extrabold text-ink">{{ $judul }}</div>
                        <div class="mt-1 text-xs leading-relaxed text-ink-muted">{{ $isi }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-storefront-layout>
