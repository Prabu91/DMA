<?php

namespace App\Livewire\Katalog;

use App\Models\Desain;
use App\Models\Frame;
use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ProdukForm extends Component
{
    use WithFileUploads;

    public ?int $produkId = null;

    // Field produk
    public ?int $kategori_id = null;

    public string $nama = '';

    public ?string $frame = null;

    public ?string $deskripsi = null;

    public int $harga = 0;

    public string $status = 'aktif';

    public $foto = null;              // file upload sementara

    public ?string $fotoExisting = null;

    // Repeater bersarang
    // Varian produk: [ ['tipe'=>'ukuran','is_wajib'=>bool,'values'=>[['nilai'=>'8R','harga_override'=>null], ...]], ... ]
    public array $variants = [];

    public array $bonus = [];         // bonus_produk_id, qty

    /**
     * Desain yang dipakai produk ini. Ditahan di memori (staging) dan baru ditulis
     * ke pivot saat save() — jadi blok desain tetap hidup walau produk belum tersimpan.
     * Item: ['id'=>?int,'kode'=>string,'foto'=>?string,'kategori'=>?string,'ukuran'=>string[]]
     * id null = desain baru yang akan dibuat saat produk disimpan.
     */
    public array $desains = [];

    public string $desainCari = '';   // pencarian pool desain (lintas kategori)

    // Form "desain baru" inline.
    public $desainFoto = null;

    public string $desainKode = '';

    public ?string $desainOrientasi = null;

    public string $desainTahun = '';

    public ?string $desainMsg = null;

    public function mount(?Produk $produk = null): void
    {
        $this->desainTahun = $this->tahunAjaranDefault();

        if ($produk && $produk->exists) {
            $this->authorize('update', $produk);
            $this->produkId = $produk->id;
            $this->kategori_id = $produk->kategori_id;
            $this->nama = $produk->nama;
            $this->frame = $produk->frame;
            $this->deskripsi = $produk->deskripsi;
            $this->harga = (int) $produk->harga;
            $this->status = $produk->status ?: 'aktif';
            $this->fotoExisting = $produk->foto;

            $this->variants = $produk->opsi
                ->groupBy('tipe_opsi')
                ->map(fn ($rows, $tipe) => [
                    'tipe' => $tipe,
                    'is_wajib' => (bool) $rows->contains(fn ($r) => $r->is_wajib),
                    'values' => $rows->map(fn ($r) => [
                        'nilai' => $r->nilai_opsi,
                        'harga_override' => $r->harga_override,
                    ])->values()->all(),
                ])->values()->all();

            $this->bonus = $produk->bonus->map(fn ($b) => [
                'bonus_produk_id' => $b->bonus_produk_id,
                'qty' => $b->qty,
            ])->values()->all();

            $this->desains = $produk->desains()->with('kategori:id,nama')->orderBy('kode')->get()
                ->map(fn ($d) => $this->rowDesain($d, self::normalUkuran($d->pivot->ukuran ?? null)))
                ->values()->all();
        } else {
            $this->authorize('create', Produk::class);
        }
    }

    #[Computed]
    public function kategoriOptions(): array
    {
        return Kategori::orderBy('nama')->pluck('nama', 'id')->all();
    }

    #[Computed]
    public function frameOptions(): array
    {
        return Frame::where('status', 'aktif')->orderBy('nama')->pluck('nama', 'nama')->all();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return Produk::STATUS;
    }

    #[Computed]
    public function produkBonusOptions(): array
    {
        return Produk::query()
            ->when($this->produkId, fn ($q) => $q->where('id', '!=', $this->produkId))
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }

    public function addVariant(): void
    {
        $this->variants[] = ['tipe' => 'ukuran', 'is_wajib' => false, 'values' => [['nilai' => '', 'harga_override' => null]]];
    }

    public function removeVariant(int $vi): void
    {
        unset($this->variants[$vi]);
        $this->variants = array_values($this->variants);
    }

    public function addValue(int $vi): void
    {
        $this->variants[$vi]['values'][] = ['nilai' => '', 'harga_override' => null];
    }

    public function removeValue(int $vi, int $ki): void
    {
        unset($this->variants[$vi]['values'][$ki]);
        $this->variants[$vi]['values'] = array_values($this->variants[$vi]['values']);
    }

    public function addBonus(): void
    {
        $this->bonus[] = ['bonus_produk_id' => null, 'qty' => 1];
    }

    public function removeBonus(int $i): void
    {
        unset($this->bonus[$i]);
        $this->bonus = array_values($this->bonus);
    }

    protected function rules(): array
    {
        return [
            'kategori_id' => ['required', 'exists:kategori,id'],
            'nama' => ['required', 'string', 'max:255'],
            'frame' => ['nullable', 'string', 'exists:frame,nama'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'harga' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:'.implode(',', array_keys(Produk::STATUS))],
            'foto' => ['nullable', 'image', 'max:2048'],

            'variants' => ['array'],
            'variants.*.tipe' => ['required', 'string', 'max:50'],
            'variants.*.is_wajib' => ['boolean'],
            'variants.*.values' => ['array', 'min:1'],
            'variants.*.values.*.nilai' => ['required', 'string', 'max:100'],
            'variants.*.values.*.harga_override' => ['nullable', 'integer', 'min:0'],

            'bonus' => ['array'],
            'bonus.*.bonus_produk_id' => ['required', 'exists:produk,id'],
            'bonus.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function save()
    {
        $this->validate();

        $data = [
            'kategori_id' => $this->kategori_id,
            'nama' => $this->nama,
            'frame' => $this->frame,
            'deskripsi' => $this->deskripsi,
            'harga' => $this->harga,
            'status' => $this->status,
        ];

        $produk = $this->produkId ? Produk::findOrFail($this->produkId) : new Produk;
        $this->authorize($this->produkId ? 'update' : 'create', $this->produkId ? $produk : Produk::class);

        // Foto: simpan yang baru, hapus yang lama.
        if ($this->foto) {
            if ($produk->foto) {
                Storage::disk('public')->delete($produk->foto);
            }
            $data['foto'] = $this->foto->store('produk', 'public');
        }

        $produk->fill($data)->save();

        // Sync opsi & bonus: hapus-lalu-buat-ulang. Varian (grup) diratakan → baris produk_opsi.
        $produk->opsi()->delete();
        foreach ($this->variants as $v) {
            foreach (($v['values'] ?? []) as $val) {
                if (($val['nilai'] ?? '') === '') {
                    continue;
                }
                $produk->opsi()->create([
                    'tipe_opsi' => $v['tipe'] ?: 'ukuran',
                    'nilai_opsi' => $val['nilai'],
                    'harga_override' => ($val['harga_override'] ?? '') !== '' ? $val['harga_override'] : null,
                    'is_wajib' => (bool) ($v['is_wajib'] ?? false),
                ]);
            }
        }

        $produk->bonus()->delete();
        foreach ($this->bonus as $b) {
            $produk->bonus()->create([
                'bonus_produk_id' => $b['bonus_produk_id'],
                'qty' => $b['qty'],
            ]);
        }

        $this->simpanDesain($produk);

        session()->flash('success', $this->produkId ? 'Produk diperbarui.' : 'Produk ditambahkan.');

        return $this->redirectRoute('app.produk.index', navigate: true);
    }

    // ========================= BLOK DESAIN (staging → pivot desain↔produk) =========================

    private function tahunAjaranDefault(): string
    {
        $y = (int) now()->year;

        return now()->month >= 7 ? $y.'/'.($y + 1) : ($y - 1).'/'.$y;
    }

    /** Pivot `ukuran` bisa array (hasil cast) atau string JSON (insert manual) → selalu array. */
    private static function normalUkuran($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }

    /** Bentuk baris staging dari model Desain. */
    private function rowDesain(Desain $d, array $ukuran = []): array
    {
        return [
            'id' => $d->id,
            'kode' => $d->kode,
            'foto' => $d->foto_preview,
            'kategori' => $d->kategori?->nama,
            'ukuran' => $ukuran,
        ];
    }

    /** Kategori produk ini memakai desain? */
    #[Computed]
    public function pakaiDesain(): bool
    {
        return (bool) (Kategori::find($this->kategori_id)?->pakai_desain);
    }

    /** Nilai opsi ukuran dari FORM (reaktif) → jadi chip ukuran untuk tiap desain. */
    #[Computed]
    public function ukuranOpsiForm(): array
    {
        return collect($this->variants)
            ->filter(fn ($v) => ($v['tipe'] ?? '') === 'ukuran')
            ->flatMap(fn ($v) => collect($v['values'] ?? [])->pluck('nilai'))
            ->filter(fn ($x) => $x !== null && $x !== '')
            ->unique()->values()->all();
    }

    /**
     * Pool desain untuk dipilih — LINTAS KATEGORI (desain adalah aset pakai-ulang);
     * hanya membuang yang sudah ada di daftar produk ini.
     */
    #[Computed]
    public function hasilCariDesain()
    {
        $dipakai = collect($this->desains)->pluck('id')->filter()->all();

        return Desain::query()
            ->where('status', 'aktif')
            ->when($this->desainCari !== '', fn ($q) => $q->where('kode', 'ilike', '%'.$this->desainCari.'%'))
            ->when($dipakai, fn ($q) => $q->whereNotIn('id', $dipakai))
            ->with('kategori:id,nama')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    /** Ambil desain yang sudah ada ke daftar produk ini. */
    public function pilihDesain(int $desainId): void
    {
        if (collect($this->desains)->contains(fn ($r) => ($r['id'] ?? null) === $desainId)) {
            return;
        }

        $d = Desain::with('kategori:id,nama')->find($desainId);
        if (! $d) {
            return;
        }

        $this->desains[] = $this->rowDesain($d);
        $this->desainCari = '';
        unset($this->hasilCariDesain);
        $this->desainMsg = $d->kode.' ditambahkan ke daftar.';
    }

    /** Keluarkan desain dari daftar produk ini (aset desainnya tidak dihapus). */
    public function hapusDesain(int $i): void
    {
        unset($this->desains[$i]);
        $this->desains = array_values($this->desains);
        unset($this->hasilCariDesain);
    }

    /** Nyalakan/matikan satu ukuran untuk desain ke-$i ([] = berlaku semua ukuran). */
    public function toggleUkuranDesain(int $i, string $ukuran): void
    {
        if (! isset($this->desains[$i])) {
            return;
        }

        $set = $this->desains[$i]['ukuran'] ?? [];
        $set = in_array($ukuran, $set, true)
            ? array_diff($set, [$ukuran])
            : array_merge($set, [$ukuran]);

        $this->desains[$i]['ukuran'] = array_values(array_intersect($set, $this->ukuranOpsiForm));
    }

    /** Siapkan desain baru (fotonya langsung disimpan; barisnya dibuat saat produk disimpan). */
    public function tambahDesainBaru(): void
    {
        $this->validate([
            'desainKode' => ['required', 'string', 'max:100', Rule::unique('desain', 'kode')],
            'desainOrientasi' => ['nullable', 'in:'.implode(',', array_keys(Desain::ORIENTASI))],
            'desainTahun' => ['required', 'string', 'max:20'],
            'desainFoto' => ['nullable', 'image', 'max:2048'],
        ]);

        // Bentrok dengan desain baru lain yang masih di daftar (belum masuk DB).
        if (collect($this->desains)->contains(fn ($r) => strcasecmp((string) ($r['kode'] ?? ''), $this->desainKode) === 0)) {
            $this->addError('desainKode', 'Kode desain sudah ada di daftar ini.');

            return;
        }

        $this->desains[] = [
            'id' => null,
            'kode' => $this->desainKode,
            'foto' => $this->desainFoto ? $this->desainFoto->store('desain', 'public') : null,
            'kategori' => Kategori::find($this->kategori_id)?->nama,
            'orientasi' => $this->desainOrientasi,
            'tahun' => $this->desainTahun,
            'ukuran' => [],
        ];

        $this->reset(['desainKode', 'desainOrientasi', 'desainFoto']);
        $this->desainTahun = $this->tahunAjaranDefault();
        unset($this->hasilCariDesain);
        $this->desainMsg = 'Desain baru disiapkan — dibuat saat produk disimpan.';
    }

    /** Tulis staging ke pivot: desain baru dibuat dulu, lalu sync beserta ukurannya. */
    private function simpanDesain(Produk $produk): void
    {
        if (! $this->pakaiDesain) {
            return;
        }

        $sync = [];
        foreach ($this->desains as $row) {
            $id = $row['id'] ?? null;

            if (! $id) {
                $id = Desain::create([
                    'kategori_id' => $this->kategori_id,
                    'kode' => $row['kode'],
                    'orientasi' => $row['orientasi'] ?? null,
                    'tahun_ajaran' => $row['tahun'] ?? $this->tahunAjaranDefault(),
                    'status' => 'aktif',
                    'foto_preview' => $row['foto'] ?? null,
                ])->id;
            }

            $sync[$id] = ['ukuran' => ($row['ukuran'] ?? []) ?: null];
        }

        $produk->desains()->sync($sync);
    }

    public function render()
    {
        return view('livewire.katalog.produk-form');
    }
}
