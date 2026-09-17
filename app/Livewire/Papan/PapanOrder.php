<?php

namespace App\Livewire\Papan;

use App\Models\Kategori;
use App\Models\Order;
use App\Models\User;
use App\Services\PapanDitolak;
use App\Services\PapanOrder as AturanPapan;
use App\Support\OrderStatus;
use App\Support\TahapOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Papan order (kanban) — pengganti board Trello tahap C sampai P.
 *
 * Satu papan, dua cara mengelompokkan: per tahap (gambaran seluruh order)
 * atau per penanggung jawab di satu tahap (pengganti list per orang di tiap
 * board Trello). Kartu = order; datanya tidak diketik ulang.
 */
#[Layout('layouts.app')]
class PapanOrder extends Component
{
    /** Kartu per kolom yang ditampilkan; sisanya dihitung saja. */
    public const BATAS_PER_KOLOM = 40;

    /** Kartu di "Selesai" hanya yang selesai dalam rentang ini. */
    public const HARI_SELESAI_TAMPIL = 14;

    /** Kolom "Menuju event": order yang event-nya dalam rentang ini. */
    public const HARI_MENUJU_EVENT = 7;

    #[Url]
    public string $mode = 'tahap';          // tahap | orang

    #[Url(as: 'di')]
    public string $tahapDipilih = 'E';

    #[Url]
    public string $jalur = '';

    #[Url(as: 'saya')]
    public bool $tugasSaya = false;

    #[Url(as: 'tahan')]
    public bool $tertahanSaja = false;

    #[Url(as: 'q')]
    public string $cari = '';

    // Lembar kartu ("Pindah ke").
    public ?int $kartuId = null;

    public string $tujuanTahap = '';

    public string $tujuanPj = '';

    public string $alasanTahan = '';

    public ?string $tenggatBaru = null;

    public ?string $pesan = null;

    public ?string $galat = null;

    public function mount(): void
    {
        $user = auth()->user();

        // Editor & marketing langsung melihat tahapnya sendiri, per orang.
        if (! request()->has('mode')) {
            if ($user->hasRole('editor')) {
                [$this->mode, $this->tahapDipilih] = ['orang', 'E'];
            } elseif ($user->hasRole('marketing')) {
                [$this->mode, $this->tahapDipilih] = ['orang', 'F'];
            }
        }

        if (! TahapOrder::ada($this->tahapDipilih)) {
            $this->tahapDipilih = 'E';
        }
        if (! in_array($this->mode, ['tahap', 'orang'], true)) {
            $this->mode = 'tahap';
        }
    }

    private function aturan(): AturanPapan
    {
        return app(AturanPapan::class);
    }

    /**
     * Order yang boleh dilihat pengguna, tanpa filter tampilan. CabangScope
     * membatasi cabang; marketing (non-pusat) hanya melihat order miliknya,
     * sama seperti daftar order.
     */
    private function terlihat(): Builder
    {
        $user = auth()->user();

        return Order::query()
            ->where('status', '!=', OrderStatus::BATAL)
            ->when($user->hasRole('marketing') && ! $user->seesAllCabang(),
                fn ($q) => $q->where('marketing_id', $user->id));
    }

    /** Order terlihat + filter yang dipilih di papan. */
    private function tersaring(): Builder
    {
        return $this->terlihat()
            ->when($this->jalur !== '', fn ($q) => $q->whereHas('items.produk.kategori', fn ($k) => $k->where('grup', $this->jalur)))
            ->when($this->tugasSaya, fn ($q) => $q->where('tahap_pj_id', auth()->id()))
            ->when($this->tertahanSaja, fn ($q) => $q->whereNotNull('tertahan_alasan'))
            ->when(trim($this->cari) !== '', function ($q) {
                $term = '%'.trim($this->cari).'%';
                $q->where(fn ($w) => $w->where('booking_code', 'ilike', $term)
                    ->orWhereHas('sekolah', fn ($s) => $s->where('nama', 'ilike', $term)));
            });
    }

    private function denganIsiKartu(Builder $q): Builder
    {
        return $q->with([
            'sekolah:id,nama',
            'marketing:id,nama,name',
            'pjPapan:id,nama,name',
            'items:id,order_id,produk_id,qc_event_at,qc_admin_at',
            'items.produk:id,kategori_id',
            'items.produk.kategori:id,grup',
        ]);
    }

    /** Semua kartu yang sudah di papan (tahap C–P) sesuai filter. */
    #[Computed]
    public function kartuPapan(): Collection
    {
        $q = $this->tersaring()
            ->whereNotNull('tahap')
            ->where(fn ($w) => $w->where('tahap', '!=', TahapOrder::SELESAI)
                ->orWhere('tahap_masuk_at', '>=', now()->subDays(self::HARI_SELESAI_TAMPIL)))
            ->orderBy('tahap_masuk_at')
            ->orderBy('id');

        return $this->denganIsiKartu($q)->get();
    }

    /** Order yang belum sampai papan, event-nya dekat atau sudah lewat. */
    #[Computed]
    public function kartuMenujuEvent(): Collection
    {
        $q = $this->tersaring()
            ->whereNull('tahap')
            ->whereNotNull('tanggal_event')
            ->whereDate('tanggal_event', '<=', now()->addDays(self::HARI_MENUJU_EVENT))
            ->orderBy('tanggal_event');

        return $this->denganIsiKartu($q)->limit(self::BATAS_PER_KOLOM + 1)->get();
    }

    /**
     * Kolom yang ditampilkan.
     *
     * @return array<int, array{kunci:string, judul:string, ket:string, total:int, kartu:array, bisaDrop:bool, inisial:?string}>
     */
    #[Computed]
    public function kolom(): array
    {
        $user = auth()->user();
        $semua = $this->kartuPapan;

        if ($this->mode === 'tahap') {
            $kolom = [];
            $menuju = $this->kartuMenujuEvent;
            $kolom[] = $this->susunKolom('ab', 'Menuju event', 'Belum Hari-H · '.self::HARI_MENUJU_EVENT.' hari ke depan', $menuju, false, null, 'A–B');

            foreach (TahapOrder::DAFTAR as $huruf => $t) {
                $isi = $semua->where('tahap', $huruf);
                $bisaDrop = $this->aturan()->pengelola($user)
                    || ($user->hasRole('editor') && in_array($huruf, ['E', 'F'], true))
                    || ($user->hasRole('marketing') && in_array($huruf, ['E', 'F', 'G'], true));
                $ket = $t['ket'].($t['hari'] ? ' · H+'.$t['hari'] : '');
                $kolom[] = $this->susunKolom('tahap:'.$huruf, $t['nama'], $ket, $isi, $bisaDrop, null, $huruf);
            }

            return $kolom;
        }

        // Per penanggung jawab di satu tahap.
        $t = $this->tahapDipilih;
        $diTahap = $semua->where('tahap', $t);
        $pengelola = $this->aturan()->pengelola($user);
        $bisaAmbil = $pengelola || ($user->hasRole('editor') && $t === 'E');

        $kolom = [$this->susunKolom('pj:0', 'Belum ditugaskan', 'Siap diambil',
            $diTahap->whereNull('tahap_pj_id')->whereNull('tertahan_alasan'), $bisaAmbil)];

        foreach ($this->kandidatPj($t, $diTahap) as $u) {
            $nama = $u->nama ?? $u->name;
            // Selain pengelola, orang hanya bisa menjatuhkan kartu ke kolomnya sendiri.
            $dropIni = $pengelola || ($bisaAmbil && $u->id === $user->id);
            $kolom[] = $this->susunKolom('pj:'.$u->id, $nama, $u->id === $user->id ? 'Anda' : 'Penanggung jawab',
                $diTahap->where('tahap_pj_id', $u->id)->whereNull('tertahan_alasan'), $dropIni, $this->inisial($nama));
        }

        $kolom[] = $this->susunKolom('tertahan', 'Tertahan', 'Menunggu sesuatu',
            $diTahap->whereNotNull('tertahan_alasan'), $bisaAmbil);

        return $kolom;
    }

    /**
     * Orang yang layak memegang kartu di tahap ini: pemegang peran tahap itu,
     * ditambah siapa pun yang sedang memegang kartu di sana.
     */
    private function kandidatPj(string $tahap, Collection $diTahap): Collection
    {
        $peran = TahapOrder::DAFTAR[$tahap]['peran'] ?? [];
        $pemegang = $diTahap->pluck('tahap_pj_id')->filter()->unique()->all();

        return User::query()
            ->where(fn ($q) => $q->when($peran, fn ($w) => $w->role($peran))
                ->orWhereIn('id', $pemegang ?: [0]))
            ->when(auth()->user()->hasRole('marketing') && ! auth()->user()->seesAllCabang(),
                fn ($q) => $q->whereKey(auth()->id()))
            ->orderByRaw('coalesce(nama, name)')
            ->get(['id', 'nama', 'name']);
    }

    private function susunKolom(string $kunci, string $judul, string $ket, Collection $isi, bool $bisaDrop, ?string $inisial = null, ?string $huruf = null): array
    {
        $user = auth()->user();

        return [
            'kunci' => $kunci,
            'judul' => $judul,
            'huruf' => $huruf,
            'ket' => $ket,
            'inisial' => $inisial,
            'total' => $isi->count(),
            'bisaDrop' => $bisaDrop,
            'kartu' => $isi->take(self::BATAS_PER_KOLOM)->map(fn (Order $o) => $this->kartu($o, $user))->values()->all(),
        ];
    }

    private function kartu(Order $o, User $user): array
    {
        $sisa = $o->tahap ? $o->sisaHariTenggat() : null;
        $jalur = $o->items
            ->map(fn ($i) => $i->produk?->kategori?->grup ?? 'reguler')
            ->unique()
            ->map(fn ($g) => match ($g) {
                'yb' => 'Yearbook',
                'ob' => 'Openbooth',
                'souvenir' => 'Souvenir',
                default => 'Reguler',
            })
            ->values()->all();

        // Sebelum Hari-H yang relevan QC tim event; sesudahnya QC admin.
        $peranQc = $o->tahap ? 'admin' : 'event';
        $qc = $o->qcProgress($peranQc);
        $pj = $o->pjPapan?->nama ?? $o->pjPapan?->name;

        return [
            'id' => $o->id,
            'url' => route('app.order.show', $o->id),
            'kode' => $o->booking_code ?? 'Order #'.$o->id,
            'sekolah' => $o->sekolah?->nama ?? '—',
            'event' => $o->tanggal_event?->translatedFormat('d M') ?? '—',
            'marketing' => $o->marketing?->nama ?? $o->marketing?->name ?? 'Tanpa marketing',
            'tahap' => $o->tahap,
            'pj' => $pj,
            'inisialPj' => $pj ? $this->inisial($pj) : null,
            'jalur' => $jalur,
            'susulan' => $o->isSusulan(),
            'tertahan' => $o->tertahan_alasan,
            'qc' => $qc['total'] ? $qc['done'].'/'.$qc['total'] : null,
            'qcLengkap' => $qc['done'] === $qc['total'],
            'qcLabel' => $peranQc === 'admin' ? 'QC admin' : 'QC event',
            'tenggat' => match (true) {
                $sisa === null => null,
                $sisa < 0 => 'Lewat '.abs($sisa).' hari',
                $sisa === 0 => 'Tenggat hari ini',
                default => 'Tenggat '.$o->tenggatPapan()->translatedFormat('d M'),
            },
            'tenggatKeadaan' => match (true) {
                $sisa === null => null,
                $sisa < 0 => 'lewat',
                $sisa === 0 => 'hari',
                default => 'aman',
            },
            'bisaKelola' => $this->aturan()->bolehKelola($user, $o),
        ];
    }

    /** "Shanty Dewiansyah" → "SD", "Abeng" → "AB". */
    private function inisial(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY);

        $huruf = count($kata) > 1
            ? mb_substr($kata[0], 0, 1).mb_substr($kata[1], 0, 1)
            : mb_substr($kata[0] ?? '?', 0, 2);

        return mb_strtoupper($huruf);
    }

    /** Ringkasan di atas papan. */
    #[Computed]
    public function ringkasan(): array
    {
        $semua = $this->kartuPapan->where('tahap', '!=', TahapOrder::SELESAI);

        return [
            'total' => $semua->count(),
            'lewat' => $semua->filter(fn (Order $o) => ($o->sisaHariTenggat() ?? 0) < 0)->count(),
            'tertahan' => $semua->whereNotNull('tertahan_alasan')->count(),
        ];
    }

    #[Computed]
    public function jalurPilihan(): array
    {
        return Kategori::GRUP;
    }

    // ---------------- Aksi ----------------

    /** Hasil seret: kartu dijatuhkan ke kolom $kunci. */
    public function pindah(int $orderId, string $kunci): void
    {
        $this->reset(['pesan', 'galat']);

        if ($kunci === 'tertahan') {
            // Tertahan butuh alasan — buka lembar kartu untuk mengisinya.
            $this->bukaKartu($orderId);
            $this->alasanTahan = '';

            return;
        }

        $this->jalankan($orderId, function (Order $o, User $u) use ($kunci) {
            if (str_starts_with($kunci, 'tahap:')) {
                $this->aturan()->pindahTahap($o, substr($kunci, 6), $u);

                return 'Kartu dipindah ke '.TahapOrder::label(substr($kunci, 6)).'.';
            }

            if (str_starts_with($kunci, 'pj:')) {
                $pj = (int) substr($kunci, 3) ?: null;
                if ($o->tahap !== $this->tahapDipilih) {
                    throw new PapanDitolak('Kartu ini tidak berada di tahap yang sedang ditampilkan.');
                }
                if ($o->tertahan_alasan !== null) {
                    $this->aturan()->lanjutkan($o, $u);
                }
                $this->aturan()->tugaskan($o, $pj, $u);

                return $pj ? 'Kartu ditugaskan.' : 'Kartu dilepas ke "Belum ditugaskan".';
            }

            throw new PapanDitolak('Kolom tujuan tidak dikenal.');
        });
    }

    public function bukaKartu(int $orderId): void
    {
        $o = $this->terlihat()->findOrFail($orderId);

        $this->reset(['galat']);
        $this->kartuId = $o->id;
        $this->tujuanTahap = (string) $o->tahap;
        $this->tujuanPj = (string) ($o->tahap_pj_id ?? '');
        $this->alasanTahan = (string) $o->tertahan_alasan;
        $this->tenggatBaru = $o->tenggat_manual?->toDateString();
    }

    public function tutupKartu(): void
    {
        $this->reset(['kartuId', 'tujuanTahap', 'tujuanPj', 'alasanTahan', 'tenggatBaru', 'galat']);
    }

    #[Computed]
    public function kartuTerbuka(): ?Order
    {
        return $this->kartuId
            ? $this->terlihat()->with(['sekolah:id,nama', 'pjPapan:id,nama,name', 'marketing:id,nama,name'])->find($this->kartuId)
            : null;
    }

    /** Pilihan untuk lembar kartu yang sedang terbuka. */
    #[Computed]
    public function pilihanKartu(): array
    {
        $o = $this->kartuTerbuka;
        $user = auth()->user();
        if (! $o || ! $this->aturan()->bolehKelola($user, $o)) {
            return ['tahap' => [], 'pj' => [], 'pengelola' => false];
        }

        $tahap = collect($this->aturan()->tahapTujuan($user, $o))
            ->mapWithKeys(fn ($h) => [$h => TahapOrder::label($h)])->all();

        $pengelola = $this->aturan()->pengelola($user);
        $pj = $pengelola
            ? $this->kandidatPj($o->tahap, collect([$o]))->mapWithKeys(fn ($u) => [$u->id => $u->nama ?? $u->name])->all()
            : [$user->id => ($user->nama ?? $user->name).' (saya)'];

        return ['tahap' => $tahap, 'pj' => $pj, 'pengelola' => $pengelola];
    }

    public function simpanTahap(): void
    {
        $tujuan = $this->tujuanTahap;
        $this->jalankan($this->kartuId, function (Order $o, User $u) use ($tujuan) {
            $this->aturan()->pindahTahap($o, $tujuan, $u);

            return 'Kartu dipindah ke '.TahapOrder::label($tujuan).'.';
        }, tutup: true);
    }

    public function simpanPj(): void
    {
        $pj = $this->tujuanPj !== '' ? (int) $this->tujuanPj : null;
        $this->jalankan($this->kartuId, function (Order $o, User $u) use ($pj) {
            $this->aturan()->tugaskan($o, $pj, $u);

            return $pj ? 'Penanggung jawab diperbarui.' : 'Kartu dilepas.';
        }, tutup: true);
    }

    public function simpanTahan(): void
    {
        $alasan = $this->alasanTahan;
        $this->jalankan($this->kartuId, function (Order $o, User $u) use ($alasan) {
            $this->aturan()->tahan($o, $alasan, $u);

            return 'Kartu ditandai tertahan.';
        }, tutup: true);
    }

    public function lanjutkan(): void
    {
        $this->jalankan($this->kartuId, function (Order $o, User $u) {
            $this->aturan()->lanjutkan($o, $u);

            return 'Tanda tertahan dilepas.';
        }, tutup: true);
    }

    public function simpanTenggat(): void
    {
        $this->validate(['tenggatBaru' => ['nullable', 'date']]);
        $tanggal = $this->tenggatBaru ?: null;
        $this->jalankan($this->kartuId, function (Order $o, User $u) use ($tanggal) {
            $this->aturan()->aturTenggat($o, $tanggal, $u);

            return $tanggal ? 'Tenggat ditetapkan.' : 'Tenggat kembali otomatis.';
        }, tutup: true);
    }

    /** Jalankan satu aksi kartu; aturan yang dilanggar tampil sebagai pesan. */
    private function jalankan(?int $orderId, callable $aksi, bool $tutup = false): void
    {
        $this->reset(['pesan', 'galat']);
        $o = $orderId ? $this->terlihat()->find($orderId) : null;

        if (! $o) {
            $this->galat = 'Kartu tidak ditemukan.';

            return;
        }

        try {
            $this->pesan = $aksi($o, auth()->user());
            if ($tutup) {
                $this->tutupKartu();
            }
        } catch (PapanDitolak $e) {
            $this->galat = $e->getMessage();
        }

        unset($this->kartuPapan, $this->kartuMenujuEvent, $this->kolom, $this->ringkasan, $this->kartuTerbuka, $this->pilihanKartu);
    }

    public function render()
    {
        return view('livewire.papan.papan-order');
    }
}
