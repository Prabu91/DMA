<?php

namespace App\Livewire\Katalog;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Support\Facades\Storage;
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

    public ?string $gaya = null;

    public ?string $deskripsi = null;

    public int $harga = 0;

    public string $satuan = 'qty';

    public string $status = 'aktif';

    public $foto = null;              // file upload sementara

    public ?string $fotoExisting = null;

    // Repeater bersarang
    public array $opsi = [];          // tipe_opsi, nilai_opsi, harga_override, is_wajib

    public array $bonus = [];         // bonus_produk_id, qty

    public function mount(?Produk $produk = null): void
    {
        if ($produk && $produk->exists) {
            $this->authorize('update', $produk);
            $this->produkId = $produk->id;
            $this->kategori_id = $produk->kategori_id;
            $this->nama = $produk->nama;
            $this->gaya = $produk->gaya;
            $this->deskripsi = $produk->deskripsi;
            $this->harga = (int) $produk->harga;
            $this->satuan = $produk->satuan ?: 'qty';
            $this->status = $produk->status ?: 'aktif';
            $this->fotoExisting = $produk->foto;

            $this->opsi = $produk->opsi->map(fn ($o) => [
                'tipe_opsi' => $o->tipe_opsi,
                'nilai_opsi' => $o->nilai_opsi,
                'harga_override' => $o->harga_override,
                'is_wajib' => (bool) $o->is_wajib,
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
    public function gayaOptions(): array
    {
        return array_combine(Produk::GAYA, Produk::GAYA);
    }

    #[Computed]
    public function statusOptions(): array
    {
        return Produk::STATUS;
    }

    #[Computed]
    public function satuanOptions(): array
    {
        return Produk::SATUAN;
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

    public function addOpsi(): void
    {
        $this->opsi[] = ['tipe_opsi' => 'ukuran', 'nilai_opsi' => '', 'harga_override' => null, 'is_wajib' => false];
    }

    public function removeOpsi(int $i): void
    {
        unset($this->opsi[$i]);
        $this->opsi = array_values($this->opsi);
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
            'gaya' => ['nullable', 'in:'.implode(',', Produk::GAYA)],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'harga' => ['required', 'integer', 'min:0'],
            'satuan' => ['required', 'in:'.implode(',', array_keys(Produk::SATUAN))],
            'status' => ['required', 'in:'.implode(',', array_keys(Produk::STATUS))],
            'foto' => ['nullable', 'image', 'max:2048'],

            'opsi' => ['array'],
            'opsi.*.tipe_opsi' => ['required', 'string', 'max:50'],
            'opsi.*.nilai_opsi' => ['required', 'string', 'max:100'],
            'opsi.*.harga_override' => ['nullable', 'integer', 'min:0'],
            'opsi.*.is_wajib' => ['boolean'],

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
            'gaya' => $this->gaya,
            'deskripsi' => $this->deskripsi,
            'harga' => $this->harga,
            'satuan' => $this->satuan,
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

        // Sync opsi & bonus: hapus-lalu-buat-ulang (aman, tak ada FK ke id-nya).
        $produk->opsi()->delete();
        foreach ($this->opsi as $o) {
            $produk->opsi()->create([
                'tipe_opsi' => $o['tipe_opsi'] ?: 'ukuran',
                'nilai_opsi' => $o['nilai_opsi'],
                'harga_override' => $o['harga_override'] !== '' ? $o['harga_override'] : null,
                'is_wajib' => (bool) ($o['is_wajib'] ?? false),
            ]);
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

    public function render()
    {
        return view('livewire.katalog.produk-form');
    }
}
