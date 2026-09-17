<div>
    <x-breadcrumb :items="[
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Pengaturan'],
    ]" />

    <x-toast :success="$success" :error="$error" />

    <div class="mb-6">
        <h1 class="text-lg font-medium text-ink">Pengaturan</h1>
        <p class="text-sm text-ink-muted">Saklar yang berlaku untuk semua cabang.</p>
    </div>

    <x-card>
        <x-slot name="title">Katalog publik</x-slot>

        <x-toggle wire:model.live="hargaPublikDisembunyikan" align="start"
                  label="Sembunyikan harga dari pengunjung"
                  hint="Bila menyala, pengunjung yang belum masuk melihat ajakan masuk sebagai ganti angka harga di katalog dan keranjang. Staf dan sekolah yang sudah masuk tetap melihat harga seperti biasa." />
    </x-card>

    <x-card class="mt-6">
        <x-slot name="title">API report order</x-slot>
        <x-slot name="subtitle">Untuk web report eksternal membaca detail order &amp; nominal omset secara langsung.</x-slot>

        @if ($tokenBaru)
            <div class="mb-4 rounded-xl border border-status-success/25 bg-status-success/10 p-3">
                <p class="text-sm font-medium text-ink">Token baru — salin sekarang, tidak bisa dilihat lagi:</p>
                <code class="mt-2 block break-all rounded-lg border border-line bg-card px-3 py-2 font-mono text-xs text-ink">{{ $tokenBaru }}</code>
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <x-confirm action="buatTokenApi" variant="secondary" size="sm"
                       title="{{ $this->adaTokenApi ? 'Ganti token API' : 'Buat token API' }}"
                       message="{{ $this->adaTokenApi ? 'Token lama langsung berhenti berlaku dan web report harus dipasangi token baru. Lanjutkan?' : 'Token akan ditampilkan sekali saja. Lanjutkan?' }}"
                       confirm-label="Ya, buat">{{ $this->adaTokenApi ? 'Ganti token' : 'Buat token' }}</x-confirm>

            @if ($this->adaTokenApi)
                <x-confirm action="cabutTokenApi" variant="ghost" size="sm" confirm-variant="danger" confirm-label="Ya, cabut"
                           title="Cabut token API" message="API report akan tertutup sampai token baru dibuat. Lanjutkan?">Cabut token</x-confirm>
                <span class="text-xs text-ink-muted">Token aktif. Isinya tidak disimpan, jadi tidak bisa ditampilkan ulang.</span>
            @else
                <span class="text-xs text-ink-muted">Belum ada token — API report tertutup.</span>
            @endif
        </div>

        <div class="mt-4 rounded-xl border border-line bg-page/50 p-3">
            <p class="text-xs font-medium text-ink">Cara pakai (server ke server)</p>
            <pre class="mt-2 overflow-x-auto text-xs leading-relaxed text-ink-muted">curl -H "Authorization: Bearer &lt;token&gt;"   "{{ url('/api/v1/report-order/ringkasan') }}?dari=2026-01-01&amp;sampai=2026-12-31"</pre>
            <ul class="mt-3 space-y-1 text-xs text-ink-muted">
                <li><code class="text-ink">/api/v1/report-order/ringkasan</code> — total baris, qty &amp; nominal saja (ringan, untuk omset realtime).</li>
                <li><code class="text-ink">/api/v1/report-order</code> — baris detail per item order, berhalaman.</li>
                <li>Filter: <code class="text-ink">q</code>, <code class="text-ink">cabang_id</code>, <code class="text-ink">produk_id</code>, <code class="text-ink">jenis</code> (berbayar/free), <code class="text-ink">dari</code>, <code class="text-ink">sampai</code>, <code class="text-ink">per_page</code>.</li>
            </ul>
        </div>
    </x-card>

    <x-card class="mt-6">
        <x-slot name="title">Folder kerja editor</x-slot>
        <x-slot name="subtitle">Pola path folder foto di server kantor yang ditampilkan di tiap order. Ubah bila susunan folder kantor berbeda.</x-slot>

        <form wire:submit="simpanFolder" class="space-y-4">
            <x-input label="Folder server" wire:model="folderRoot" :error="$errors->first('folderRoot')" hint="Dipakai lewat penanda {root}." />
            <x-input label="Templat folder sekolah" wire:model="folderTemplatSekolah" :error="$errors->first('folderTemplatSekolah')" />
            <x-input label="Templat folder item" wire:model="folderTemplatItem" :error="$errors->first('folderTemplatItem')" />

            <div>
                <span class="block text-sm font-medium text-ink">Folder jalur per grup kategori</span>
                <p class="mt-0.5 text-xs text-ink-muted">Dipakai lewat penanda {jalur}. Hanya folder Reguler yang diketahui dari contoh DMA — cek yang lain ke tim editor.</p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Models\Kategori::GRUP as $grup => $label)
                        <x-input :label="$label" wire:model="folderJalur.{{ $grup }}" :error="$errors->first('folderJalur.'.$grup)" />
                    @endforeach
                </div>
            </div>

            <details class="rounded-xl border border-line bg-page/50 p-3">
                <summary class="cursor-pointer text-xs font-medium text-ink">Penanda yang bisa dipakai</summary>
                <div class="mt-2 grid gap-3 text-xs sm:grid-cols-2">
                    <ul class="space-y-1">
                        <li class="font-medium text-ink">Templat folder sekolah</li>
                        @foreach (\App\Support\FolderKerja::PENANDA_SEKOLAH as $penanda => $arti)
                            <li class="text-ink-muted"><code class="text-ink">{{ $penanda }}</code> — {{ $arti }}</li>
                        @endforeach
                    </ul>
                    <ul class="space-y-1">
                        <li class="font-medium text-ink">Templat folder item</li>
                        @foreach (\App\Support\FolderKerja::PENANDA_ITEM as $penanda => $arti)
                            <li class="text-ink-muted"><code class="text-ink">{{ $penanda }}</code> — {{ $arti }}</li>
                        @endforeach
                    </ul>
                </div>
            </details>

            @if ($this->contohFolder)
                <div class="rounded-xl border border-line bg-page/50 p-3">
                    <p class="text-xs font-medium text-ink">Contoh dari order terbaru (pola yang tersimpan)</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($this->contohFolder as $path)
                            <li><code class="block break-all text-xs text-ink-muted">{{ $path }}</code></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <x-button type="submit" size="sm">
                    <span wire:loading.remove wire:target="simpanFolder">Simpan pola folder</span>
                    <span wire:loading wire:target="simpanFolder">Menyimpan…</span>
                </x-button>
                <x-confirm action="folderKeBawaan" variant="ghost" size="sm" confirm-label="Ya, kembalikan"
                           title="Kembalikan pola bawaan" message="Semua pola folder kembali ke bawaan aplikasi. Lanjutkan?">Kembalikan ke bawaan</x-confirm>
            </div>
        </form>
    </x-card>
</div>
