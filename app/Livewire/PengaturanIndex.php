<?php

namespace App\Livewire;

use App\Models\Order;
use App\Support\FolderKerja;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Saklar aplikasi yang boleh diubah admin sendiri, tanpa deploy ulang.
 * Sengaja dibatasi admin pusat karena efeknya berlaku untuk semua cabang.
 */
#[Layout('layouts.app')]
class PengaturanIndex extends Component
{
    public bool $hargaPublikDisembunyikan = false;

    public ?string $success = null;

    public ?string $error = null;

    // Pola folder kerja editor (lihat App\Support\FolderKerja).
    public string $folderRoot = '';

    public string $folderTemplatSekolah = '';

    public string $folderTemplatItem = '';

    /** @var array<string, string> [grup kategori => nama folder jalur] */
    public array $folderJalur = [];

    /** Token API mentah — hanya ada di memori, ditampilkan sekali setelah dibuat. */
    public ?string $tokenBaru = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        $this->hargaPublikDisembunyikan = Pengaturan::bool(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN);
        $this->isiFormFolder();
    }

    private function isiFormFolder(): void
    {
        $this->folderRoot = FolderKerja::root();
        $this->folderTemplatSekolah = FolderKerja::templatSekolah();
        $this->folderTemplatItem = FolderKerja::templatItem();
        foreach (array_keys(FolderKerja::BAWAAN_JALUR) as $grup) {
            $this->folderJalur[$grup] = FolderKerja::jalur($grup);
        }
    }

    /**
     * Simpan pola folder kerja. Nilai yang sama dengan bawaan tidak disimpan,
     * supaya perbaikan bawaan di versi berikutnya tetap terbawa.
     */
    public function simpanFolder(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        $this->validate([
            'folderRoot' => ['required', 'string', 'max:200'],
            'folderTemplatSekolah' => ['required', 'string', 'max:300'],
            'folderTemplatItem' => ['required', 'string', 'max:300', 'regex:/\{folder_sekolah\}/'],
            'folderJalur.*' => ['required', 'string', 'max:100'],
        ], [
            'folderTemplatItem.regex' => 'Templat item wajib memuat {folder_sekolah}.',
            'folderJalur.*.required' => 'Nama folder jalur wajib diisi.',
        ]);

        foreach ([
            'folderTemplatSekolah' => FolderKerja::PENANDA_SEKOLAH,
            'folderTemplatItem' => FolderKerja::PENANDA_ITEM,
        ] as $field => $dikenal) {
            if ($asing = FolderKerja::penandaTakDikenal($this->{$field}, $dikenal)) {
                $this->addError($field, 'Penanda tidak dikenal: '.implode(', ', $asing));

                return;
            }
        }

        $simpan = function (string $kunci, string $nilai, string $bawaan) {
            trim($nilai) === $bawaan ? Pengaturan::hapus($kunci) : Pengaturan::set($kunci, trim($nilai));
        };

        $simpan(FolderKerja::KUNCI_ROOT, $this->folderRoot, FolderKerja::BAWAAN_ROOT);
        $simpan(FolderKerja::KUNCI_TEMPLAT_SEKOLAH, $this->folderTemplatSekolah, FolderKerja::BAWAAN_TEMPLAT_SEKOLAH);
        $simpan(FolderKerja::KUNCI_TEMPLAT_ITEM, $this->folderTemplatItem, FolderKerja::BAWAAN_TEMPLAT_ITEM);
        foreach (FolderKerja::BAWAAN_JALUR as $grup => $bawaan) {
            $simpan(FolderKerja::PREFIKS_JALUR.$grup, (string) ($this->folderJalur[$grup] ?? ''), $bawaan);
        }

        $this->isiFormFolder();
        unset($this->contohFolder);
        $this->success = 'Pola folder kerja disimpan.';
    }

    public function folderKeBawaan(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        Pengaturan::hapus(FolderKerja::KUNCI_ROOT);
        Pengaturan::hapus(FolderKerja::KUNCI_TEMPLAT_SEKOLAH);
        Pengaturan::hapus(FolderKerja::KUNCI_TEMPLAT_ITEM);
        foreach (array_keys(FolderKerja::BAWAAN_JALUR) as $grup) {
            Pengaturan::hapus(FolderKerja::PREFIKS_JALUR.$grup);
        }

        $this->resetErrorBag();
        $this->isiFormFolder();
        unset($this->contohFolder);
        $this->success = 'Pola folder kerja dikembalikan ke bawaan.';
    }

    /** Contoh hasil dari order terbaru yang punya item, memakai pola yang tersimpan. */
    #[Computed]
    public function contohFolder(): array
    {
        $order = Order::whereHas('items')->whereNotNull('tanggal_event')->latest('id')->first();

        return $order ? array_slice(FolderKerja::perItem($order), 0, 3) : [];
    }

    /** Token API sudah pernah dibuat? (isinya sendiri tak bisa dibaca lagi) */
    #[Computed]
    public function adaTokenApi(): bool
    {
        return Pengaturan::teks(Pengaturan::API_TOKEN_HASH) !== null;
    }

    /**
     * Buat token baru. Yang disimpan hanya hash-nya; token mentah ditampilkan
     * sekali di layar ini dan tidak bisa dilihat lagi setelah halaman berpindah.
     * Membuat token baru otomatis membatalkan token lama.
     */
    public function buatTokenApi(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        $this->tokenBaru = Str::random(64);
        Pengaturan::set(Pengaturan::API_TOKEN_HASH, Hash::make($this->tokenBaru));
        unset($this->adaTokenApi);

        $this->success = 'Token API dibuat. Salin sekarang — token ini tidak bisa dilihat lagi.';
    }

    public function cabutTokenApi(): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        Pengaturan::hapus(Pengaturan::API_TOKEN_HASH);
        $this->tokenBaru = null;
        unset($this->adaTokenApi);

        $this->success = 'Token API dicabut. API report kini tertutup.';
    }

    public function updatedHargaPublikDisembunyikan(bool $nilai): void
    {
        abort_unless(auth()->user()?->isAdminSales(), 403);

        Pengaturan::set(Pengaturan::HARGA_PUBLIK_DISEMBUNYIKAN, $nilai);

        $this->success = $nilai
            ? 'Harga kini disembunyikan dari pengunjung yang belum masuk.'
            : 'Harga kini terlihat untuk semua pengunjung.';
    }

    public function render()
    {
        return view('livewire.pengaturan-index');
    }
}
