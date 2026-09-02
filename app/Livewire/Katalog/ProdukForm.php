<?php

namespace App\Livewire\Katalog;

use App\Models\Desain;
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

    // Blok Desain (khusus produk yang sudah tersimpan) — kelola pivot desain↔produk.
    public $desainFoto = null;
    public string $desainKode = '';
    public ?string $desainOrientasi = null;
    public string $desainTahun = '';
    public array $desainUkuran = [];   // ukuran dicentang utk desain baru ([] = semua)
    public ?int $tempelDesainId = null; // desain existing utk ditempel
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
        return \App\Models\Frame::where('status', 'aktif')->orderBy('nama')->pluck('nama', 'nama')->all();
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

        session()->flash('success', $this->produkId ? 'Produk diperbarui.' : 'Produk ditambahkan.');

        return $this->redirectRoute('app.produk.index', navigate: true);
    }

    // ========================= BLOK DESAIN (pivot desain↔produk) =========================

    private function tahunAjaranDefault(): string
    {
        $y = (int) now()->year;

        return now()->month >= 7 ? $y.'/'.($y + 1) : ($y - 1).'/'.$y;
    }

    /** Kategori produk ini memakai desain? */
    #[Computed]
    public function pakaiDesain(): bool
    {
        return (bool) (Kategori::find($this->kategori_id)?->pakai_desain);
    }

    /** Nilai opsi ukuran dari FORM (reaktif) → jadi checkbox untuk desain. */
    #[Computed]
    public function ukuranOpsiForm(): array
    {
        return collect($this->variants)
            ->filter(fn ($v) => ($v['tipe'] ?? '') === 'ukuran')
            ->flatMap(fn ($v) => collect($v['values'] ?? [])->pluck('nilai'))
            ->filter(fn ($x) => $x !== null && $x !== '')
            ->unique()->values()->all();
    }

    /** Desain yang sudah ditempel ke produk ini (+ ukuran pivot). */
    #[Computed]
    public function attachedDesigns()
    {
        if (! $this->produkId) {
            return collect();
        }

        return Produk::find($this->produkId)->desains()->orderBy('kode')->get();
    }

    /** Desain existing (kategori sama) yang belum ditempel. */
    #[Computed]
    public function availableDesigns(): array
    {
        if (! $this->produkId || ! $this->kategori_id) {
            return [];
        }

        $attached = $this->attachedDesigns->pluck('id')->all();

        return Desain::where('kategori_id', $this->kategori_id)
            ->whereNotIn('id', $attached)
            ->orderBy('kode')
            ->pluck('kode', 'id')
            ->all();
    }

    private function refreshDesigns(): void
    {
        unset($this->attachedDesigns, $this->availableDesigns);
    }

    /** Buat desain baru + tempel ke produk ini dengan ukuran terpilih. */
    public function tambahDesainBaru(): void
    {
        abort_unless($this->produkId, 422);
        $this->authorize('update', Produk::findOrFail($this->produkId));

        $this->validate([
            'desainKode' => ['required', 'string', 'max:100', Rule::unique('desain', 'kode')],
            'desainOrientasi' => ['nullable', 'in:'.implode(',', array_keys(Desain::ORIENTASI))],
            'desainTahun' => ['required', 'string', 'max:20'],
            'desainFoto' => ['nullable', 'image', 'max:2048'],
            'desainUkuran' => ['array'],
            'desainUkuran.*' => ['string', Rule::in($this->ukuranOpsiForm)],
        ]);

        $path = $this->desainFoto ? $this->desainFoto->store('desain', 'public') : null;

        $desain = Desain::create([
            'kategori_id' => $this->kategori_id,
            'kode' => $this->desainKode,
            'orientasi' => $this->desainOrientasi,
            'tahun_ajaran' => $this->desainTahun,
            'status' => 'aktif',
            'foto_preview' => $path,
        ]);
        $desain->products()->attach($this->produkId, ['ukuran' => $this->desainUkuran ?: null]);

        $this->reset(['desainKode', 'desainOrientasi', 'desainFoto', 'desainUkuran']);
        $this->desainTahun = $this->tahunAjaranDefault();
        $this->refreshDesigns();
        $this->desainMsg = 'Desain ditambahkan ke produk.';
    }

    /** Tempel desain yang sudah ada ke produk ini. */
    public function tempelDesain(): void
    {
        abort_unless($this->produkId, 422);
        $this->authorize('update', Produk::findOrFail($this->produkId));
        if (! $this->tempelDesainId) {
            return;
        }

        Produk::findOrFail($this->produkId)->desains()->syncWithoutDetaching([
            $this->tempelDesainId => ['ukuran' => null],
        ]);
        $this->tempelDesainId = null;
        $this->refreshDesigns();
        $this->desainMsg = 'Desain ditempel ke produk.';
    }

    /** Lepas desain dari produk (tidak menghapus asetnya). */
    public function lepasDesain(int $desainId): void
    {
        abort_unless($this->produkId, 422);
        $this->authorize('update', Produk::findOrFail($this->produkId));
        Produk::findOrFail($this->produkId)->desains()->detach($desainId);
        $this->refreshDesigns();
        $this->desainMsg = 'Desain dilepas dari produk.';
    }

    /** Set ukuran berlaku untuk sebuah desain yang ditempel ([] = semua ukuran). */
    public function setUkuranDesain(int $desainId, array $ukuran): void
    {
        abort_unless($this->produkId, 422);
        $this->authorize('update', Produk::findOrFail($this->produkId));
        $valid = array_values(array_intersect($ukuran, $this->ukuranOpsiForm));
        Produk::findOrFail($this->produkId)->desains()->updateExistingPivot($desainId, [
            'ukuran' => $valid ?: null,
        ]);
        $this->refreshDesigns();
    }

    public function render()
    {
        return view('livewire.katalog.produk-form');
    }
}
