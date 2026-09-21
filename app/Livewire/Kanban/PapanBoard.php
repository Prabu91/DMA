<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\Aktivitas;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Komentar;
use App\Models\Kanban\Label;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\Kanban\OtomasiOrder;
use App\Support\Kanban\Posisi;
use App\Support\Kanban\Warna;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Satu board: list & kartu yang bisa diseret seperti Trello.
 *
 * Seret memakai wire:sort bawaan Livewire (SortableJS). Detail kartu dibuka
 * sebagai komponen anak lewat ?kartu=ID, sehingga tautan kartu bisa dibagikan.
 */
#[Layout('layouts.kanban')]
class PapanBoard extends Component
{
    public const TAMPILAN = [
        'papan' => 'Papan',
        'tabel' => 'Tabel',
        'kalender' => 'Kalender',
    ];

    public const URUT_TABEL = [
        'list' => 'List',
        'judul' => 'Judul',
        'tenggat' => 'Tenggat',
        'dibuat' => 'Dibuat',
    ];

    public Board $board;

    #[Url(as: 'kartu')]
    public ?int $kartuId = null;

    // Penyaring (seperti tombol Filter di Trello).
    #[Url(as: 'q')]
    public string $cari = '';

    #[Url(as: 'label')]
    public array $saringLabel = [];

    #[Url(as: 'orang')]
    public array $saringAnggota = [];

    #[Url(as: 'tenggat')]
    public string $saringTenggat = '';

    /** Tampilan board: papan (kanban), tabel, atau kalender. */
    #[Url(as: 'tampilan')]
    public string $tampilan = 'papan';

    /** Bulan yang sedang dilihat di kalender (format Y-m). */
    #[Url(as: 'bulan')]
    public ?string $bulan = null;

    public string $urutTabel = 'list';

    public string $arahTabel = 'asc';

    // Isian.
    public string $namaBoard = '';

    public string $namaKolomBaru = '';

    public ?int $tambahKartuDi = null;

    public string $judulKartuBaru = '';

    public ?string $pesan = null;

    /** Sidik jari isi board; dipakai agar pemeriksaan berkala tidak menggambar ulang tanpa perlu. */
    public ?string $cap = null;

    // Menu board.
    public string $namaLabelBaru = '';

    public string $warnaLabelBaru = 'hijau';

    public ?int $anggotaBaru = null;

    public string $namaSalinanBoard = '';

    public bool $salinDenganKartu = true;

    /** Otomasi board Order: pemicu => id list tujuan (kosong = tidak dipindahkan). */
    public array $otomasi = [];

    public function mount(Board $board): void
    {
        abort_unless(Akses::bolehLihat(auth()->user(), $board), 403);

        $this->board = $board;
        $this->namaBoard = $board->nama;

        if (! array_key_exists($this->tampilan, self::TAMPILAN)) {
            $this->tampilan = 'papan';
        }
        $this->namaSalinanBoard = mb_substr($board->nama.' (salinan)', 0, 120);

        if ($board->isOrder()) {
            $tersimpan = OtomasiOrder::aturan();
            foreach (array_keys(OtomasiOrder::PEMICU) as $pemicu) {
                $this->otomasi[$pemicu] = (string) ($tersimpan[$pemicu] ?? '');
            }
        }
    }

    // ---------------- Data ----------------

    #[Computed]
    public function bolehUbah(): bool
    {
        return Akses::bolehUbah(auth()->user(), $this->board);
    }

    #[Computed]
    public function bolehKelola(): bool
    {
        return Akses::bolehKelola(auth()->user(), $this->board);
    }

    #[Computed]
    public function sayaAnggota(): bool
    {
        return Akses::anggota(auth()->user(), $this->board);
    }

    #[Computed]
    public function sayaBintang(): bool
    {
        return (bool) $this->board->anggota()->whereKey(auth()->id())->first()?->pivot?->berbintang;
    }

    #[Computed]
    public function anggotaBoard(): Collection
    {
        return $this->board->anggota()->wherePivot('peran', '!=', 'pengamat')->orderByRaw('coalesce(nama, name)')->get(['users.id', 'nama', 'name']);
    }

    #[Computed]
    public function labelBoard(): Collection
    {
        return $this->board->label()->get();
    }

    /** Orang yang bisa ditambahkan sebagai anggota board. */
    #[Computed]
    public function calonAnggota(): Collection
    {
        return User::role(Akses::PERAN_STAF)
            ->whereNotIn('id', $this->anggotaBoard->pluck('id'))
            ->orderByRaw('coalesce(nama, name)')
            ->get(['id', 'nama', 'name']);
    }

    /** Kartu aktif board ini sesudah penyaring — dipakai papan, tabel, dan kalender. */
    private function kartuTersaring()
    {
        return Kartu::query()
            ->where('board_id', $this->board->id)
            ->whereNull('diarsipkan_at')
            ->with(['label', 'anggota:id,nama,name', 'coverLampiran', 'order:id,booking_code,status,order_induk_id'])
            ->withCount([
                'komentar',
                'lampiran',
                'checklistItem',
                'checklistItem as checklist_selesai_count' => fn ($q) => $q->whereNotNull('selesai_at'),
            ])
            ->when(trim($this->cari) !== '', fn ($q) => $q->where('judul', 'ilike', '%'.trim($this->cari).'%'))
            ->when($this->saringLabel, fn ($q) => $q->whereHas('label', fn ($l) => $l->whereIn('kanban_label.id', $this->saringLabel)))
            ->when($this->saringAnggota, fn ($q) => $q->whereHas('anggota', fn ($a) => $a->whereIn('users.id', $this->saringAnggota)))
            ->when($this->saringTenggat === 'tanpa', fn ($q) => $q->whereNull('tenggat_pada'))
            ->when($this->saringTenggat === 'lewat', fn ($q) => $q->whereNull('tenggat_selesai_at')->where('tenggat_pada', '<', now()))
            ->when($this->saringTenggat === 'segera', fn ($q) => $q->whereNull('tenggat_selesai_at')->whereBetween('tenggat_pada', [now(), now()->addDay()]))
            ->when($this->saringTenggat === 'selesai', fn ($q) => $q->whereNotNull('tenggat_selesai_at'));
    }

    #[Computed]
    public function kolom(): Collection
    {
        $kolom = $this->board->kolom()->get();

        $kartu = $this->kartuTersaring()
            ->whereIn('kolom_id', $kolom->pluck('id'))
            ->orderBy('posisi')
            ->get()
            ->groupBy('kolom_id');

        return $kolom->each(fn (Kolom $k) => $k->setRelation('kartu', $kartu->get($k->id, collect())));
    }

    /** Baris tampilan tabel, terurut sesuai pilihan kepala kolom. */
    #[Computed]
    public function baris(): Collection
    {
        $arah = $this->arahTabel === 'desc' ? 'desc' : 'asc';

        return $this->kartuTersaring()
            ->with('kolom:id,nama,posisi')
            ->when($this->urutTabel === 'judul', fn ($q) => $q->orderBy('judul', $arah))
            ->when($this->urutTabel === 'dibuat', fn ($q) => $q->orderBy('created_at', $arah))
            ->when($this->urutTabel === 'tenggat', fn ($q) => $q->orderByRaw('tenggat_pada is null')->orderBy('tenggat_pada', $arah))
            ->when($this->urutTabel === 'list', fn ($q) => $q->orderBy(
                Kolom::select('posisi')->whereColumn('kanban_kolom.id', 'kanban_kartu.kolom_id'), $arah
            )->orderBy('posisi'))
            ->limit(500)
            ->get();
    }

    /** Bulan yang sedang dilihat kalender. */
    #[Computed]
    public function bulanAktif(): Carbon
    {
        try {
            return $this->bulan ? Carbon::createFromFormat('Y-m', $this->bulan)->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /**
     * Kartu bertenggat pada bulan aktif, dikelompokkan per tanggal (Y-m-d)
     * supaya gampang ditaruh di kotak kalender.
     */
    #[Computed]
    public function kalender(): Collection
    {
        $mulai = $this->bulanAktif->copy()->startOfWeek();
        $selesai = $this->bulanAktif->copy()->endOfMonth()->endOfWeek();

        return $this->kartuTersaring()
            ->with('kolom:id,nama')
            ->whereNotNull('tenggat_pada')
            ->whereBetween('tenggat_pada', [$mulai, $selesai])
            ->orderBy('tenggat_pada')
            ->get()
            ->groupBy(fn (Kartu $k) => $k->tenggat_pada->format('Y-m-d'));
    }

    /** Kartu bertenggat di luar bulan aktif tidak hilang: ditunjukkan jumlahnya. */
    #[Computed]
    public function tanpaTenggat(): int
    {
        return (clone $this->kartuTersaring())->whereNull('tenggat_pada')->count();
    }

    #[Computed]
    public function adaSaringan(): bool
    {
        return trim($this->cari) !== '' || $this->saringLabel || $this->saringAnggota || $this->saringTenggat !== '';
    }

    #[Computed]
    public function arsip(): array
    {
        return [
            'kartu' => Kartu::where('board_id', $this->board->id)->whereNotNull('diarsipkan_at')->latest('diarsipkan_at')->limit(50)->get(['id', 'judul', 'diarsipkan_at']),
            'kolom' => Kolom::where('board_id', $this->board->id)->whereNotNull('diarsipkan_at')->latest('diarsipkan_at')->get(['id', 'nama', 'diarsipkan_at']),
        ];
    }

    #[Computed]
    public function aktivitas(): Collection
    {
        return Aktivitas::where('board_id', $this->board->id)
            ->with(['pelaku:id,nama,name', 'kartu:id,judul'])
            ->latest('created_at')->latest('id')->limit(40)->get();
    }

    /**
     * Sidik jari isi board: berubah begitu ada kartu, list, komentar, atau
     * aktivitas baru. Query-nya ringan supaya aman dipanggil tiap beberapa detik.
     */
    private function capBoard(): string
    {
        $kartu = Kartu::where('board_id', $this->board->id)
            ->selectRaw('count(*) as jml, max(updated_at) as terakhir')->first();
        $kolom = Kolom::where('board_id', $this->board->id)
            ->selectRaw('count(*) as jml, max(updated_at) as terakhir')->first();
        $lain = Aktivitas::where('board_id', $this->board->id)->max('id');
        $komentar = Komentar::whereIn('kartu_id', Kartu::where('board_id', $this->board->id)->select('id'))->max('id');

        return implode('|', [
            $kartu->jml, $kartu->terakhir, $kolom->jml, $kolom->terakhir, $lain, $komentar, $this->board->updated_at,
        ]);
    }

    /**
     * Dipanggil berkala dari papan. Bila tidak ada perubahan, render dilewati
     * sehingga yang bolak-balik hanya permintaan kecil tanpa HTML.
     */
    public function cek(): void
    {
        if ($this->capBoard() === $this->cap) {
            $this->skipRender();

            return;
        }

        $this->segarkan();
    }

    private function segarkan(): void
    {
        unset($this->templat, $this->kolom, $this->baris, $this->kalender, $this->tanpaTenggat, $this->bulanAktif, $this->arsip, $this->aktivitas, $this->labelBoard, $this->anggotaBoard, $this->calonAnggota, $this->sayaAnggota, $this->sayaBintang, $this->bolehUbah, $this->bolehKelola);
    }

    private function wajibUbah(): void
    {
        abort_unless($this->bolehUbah, 403);
    }

    private function wajibKelola(): void
    {
        abort_unless($this->bolehKelola, 403);
    }

    private function kolomMilikBoard(int $id): Kolom
    {
        return Kolom::where('board_id', $this->board->id)->findOrFail($id);
    }

    private function kartuMilikBoard(int $id): Kartu
    {
        return Kartu::where('board_id', $this->board->id)->findOrFail($id);
    }

    // ---------------- Seret ----------------

    /** wire:sort pada deretan list. */
    public function urutKolom(int|string $kolomId, int $posisi): void
    {
        $this->wajibUbah();
        app(Tata::class)->pindahKolom($this->kolomMilikBoard((int) $kolomId), $posisi, auth()->user());
        $this->segarkan();
    }

    /** wire:sort pada daftar kartu; $kolomId = list tempat kartu dijatuhkan. */
    public function urutKartu(int|string $kartuId, int $posisi, int|string $kolomId): void
    {
        $this->wajibUbah();
        app(Tata::class)->pindahKartu(
            $this->kartuMilikBoard((int) $kartuId),
            $this->kolomMilikBoard((int) $kolomId),
            $posisi,
            auth()->user(),
        );
        $this->segarkan();
    }

    // ---------------- List ----------------

    public function tambahKolom(): void
    {
        $this->wajibUbah();
        $this->validate(['namaKolomBaru' => ['required', 'string', 'max:120']], ['namaKolomBaru.required' => 'Beri nama list.']);

        app(Tata::class)->tambahKolom($this->board, $this->namaKolomBaru, auth()->user());
        $this->reset('namaKolomBaru');
        $this->segarkan();
    }

    public function ubahNamaKolom(int $kolomId, string $nama): void
    {
        $this->wajibUbah();
        $nama = trim($nama);
        if ($nama === '') {
            return;
        }

        $this->kolomMilikBoard($kolomId)->update(['nama' => mb_substr($nama, 0, 120)]);
        $this->segarkan();
    }

    public function arsipkanKolom(int $kolomId): void
    {
        $this->wajibUbah();
        $kolom = $this->kolomMilikBoard($kolomId);
        $kolom->update(['diarsipkan_at' => now()]);
        $this->board->catat('kolom_diarsipkan', $kolom->nama);
        $this->segarkan();
    }

    public function pulihkanKolom(int $kolomId): void
    {
        $this->wajibUbah();
        $kolom = $this->kolomMilikBoard($kolomId);
        $kolom->update([
            'diarsipkan_at' => null,
            'posisi' => (float) Kolom::where('board_id', $this->board->id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK,
        ]);
        $this->board->catat('kolom_dipulihkan', $kolom->nama);
        $this->segarkan();
    }

    // ---------------- Kartu ----------------

    public function mulaiTambahKartu(int $kolomId): void
    {
        $this->wajibUbah();
        $this->tambahKartuDi = $kolomId;
        $this->judulKartuBaru = '';
        $this->resetErrorBag('judulKartuBaru');
    }

    public function batalTambahKartu(): void
    {
        $this->tambahKartuDi = null;
        $this->judulKartuBaru = '';
    }

    /** Simpan kartu lalu biarkan isian tetap terbuka untuk kartu berikutnya (seperti Trello). */
    public function tambahKartu(): void
    {
        $this->wajibUbah();
        $this->validate(['judulKartuBaru' => ['required', 'string', 'max:255']], ['judulKartuBaru.required' => 'Tulis judul kartu.']);

        app(Tata::class)->tambahKartu($this->kolomMilikBoard((int) $this->tambahKartuDi), $this->judulKartuBaru, auth()->user());
        $this->judulKartuBaru = '';
        $this->segarkan();
    }

    public function bukaKartu(int $kartuId): void
    {
        $this->kartuId = $this->kartuMilikBoard($kartuId)->id;
    }

    #[On('buka-kartu')]
    public function bukaKartuDariAnak(int $kartuId): void
    {
        $this->kartuId = $this->kartuMilikBoard($kartuId)->id;
        $this->segarkan();
    }

    #[On('kartu-ditutup')]
    public function tutupKartu(): void
    {
        $this->kartuId = null;
        $this->segarkan();
    }

    #[On('kartu-berubah')]
    public function kartuBerubah(): void
    {
        $this->segarkan();
    }

    public function pulihkanKartu(int $kartuId): void
    {
        $this->wajibUbah();
        $kartu = $this->kartuMilikBoard($kartuId);
        $kolom = Kolom::find($kartu->kolom_id);
        if ($kolom?->diarsipkan_at) {
            $this->pesan = 'List kartu ini masih diarsipkan — pulihkan list-nya dulu.';

            return;
        }

        $kartu->update([
            'diarsipkan_at' => null,
            'posisi' => (float) Kartu::where('kolom_id', $kartu->kolom_id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK,
        ]);
        $this->board->catat('kartu_dipulihkan', null, $kartu);
        $this->segarkan();
    }

    /** Kartu templat di board ini — cetakan untuk kartu baru. */
    #[Computed]
    public function templat(): Collection
    {
        return Kartu::where('board_id', $this->board->id)
            ->where('templat', true)->whereNull('diarsipkan_at')
            ->orderBy('judul')->get(['id', 'judul']);
    }

    /** Buat kartu baru dari kartu templat (padanan "Create card from template"). */
    public function dariTemplat(int $templatId, int $kolomId): void
    {
        $this->wajibUbah();
        $templat = Kartu::where('board_id', $this->board->id)->where('templat', true)->findOrFail($templatId);

        $kartu = app(Tata::class)->salinKartu(
            $templat,
            $this->kolomMilikBoard($kolomId),
            $templat->judul,
            ['label', 'anggota', 'checklist', 'lampiran'],
            auth()->user(),
        );
        $kartu->update(['templat' => false]);

        $this->tambahKartuDi = null;
        $this->kartuId = $kartu->id;
        $this->segarkan();
    }

    public function salinKolom(int $kolomId): void
    {
        $this->wajibUbah();
        $kolom = $this->kolomMilikBoard($kolomId);

        app(Tata::class)->salinKolom($kolom, mb_substr($kolom->nama.' (salinan)', 0, 120), auth()->user());
        $this->segarkan();
    }

    // ---------------- Board ----------------

    public function gabung(): void
    {
        $user = auth()->user();
        abort_unless(Akses::bolehLihat($user, $this->board) && $this->board->diarsipkan_at === null, 403);

        $this->board->anggota()->syncWithoutDetaching([$user->id => ['peran' => 'anggota']]);
        $this->board->anggota()->updateExistingPivot($user->id, ['peran' => 'anggota']);
        $this->board->catat('anggota_gabung', $user->nama ?? $user->name);
        $this->board->unsetRelation('anggota');
        $this->segarkan();
    }

    public function bintang(): void
    {
        $user = auth()->user();
        $baris = $this->board->anggota()->whereKey($user->id)->first();
        if ($baris) {
            $this->board->anggota()->updateExistingPivot($user->id, ['berbintang' => ! $baris->pivot->berbintang]);
        } else {
            $this->board->anggota()->attach($user->id, ['peran' => 'pengamat', 'berbintang' => true]);
        }
        $this->segarkan();
    }

    public function simpanNamaBoard(): void
    {
        $this->wajibKelola();
        $this->validate(['namaBoard' => ['required', 'string', 'max:120']]);
        $this->board->update(['nama' => trim($this->namaBoard)]);
    }

    public function ubahWarna(string $warna): void
    {
        $this->wajibKelola();
        abort_unless(array_key_exists($warna, Warna::BOARD), 422);
        $this->board->update(['warna' => $warna]);
    }

    public function ubahVisibilitas(string $visibilitas): void
    {
        $this->wajibKelola();
        abort_if($this->board->isOrder(), 422);
        abort_unless(array_key_exists($visibilitas, Board::VISIBILITAS), 422);
        $this->board->update(['visibilitas' => $visibilitas]);
    }

    /** Salin board ini jadi board baru (list selalu ikut, kartu opsional). */
    public function salinBoard()
    {
        abort_unless(Akses::bolehLihat(auth()->user(), $this->board), 403);
        $this->validate(['namaSalinanBoard' => ['required', 'string', 'max:120']], ['namaSalinanBoard.required' => 'Beri nama board baru.']);

        $baru = app(Tata::class)->salinBoard($this->board, $this->namaSalinanBoard, $this->salinDenganKartu, auth()->user());

        return $this->redirect(route('kanban.board', $baru), navigate: true);
    }

    public function arsipkanBoard()
    {
        $this->wajibKelola();
        abort_if($this->board->isOrder(), 422);
        $this->board->update(['diarsipkan_at' => now()]);
        $this->board->catat('board_diarsipkan');

        return $this->redirect(route('kanban.beranda'), navigate: true);
    }

    public function tambahAnggota(): void
    {
        $this->wajibKelola();
        $this->validate(['anggotaBaru' => ['required', Rule::exists('users', 'id')]]);

        $this->board->anggota()->syncWithoutDetaching([$this->anggotaBaru => ['peran' => 'anggota']]);
        $this->board->anggota()->updateExistingPivot($this->anggotaBaru, ['peran' => 'anggota']);
        $user = User::find($this->anggotaBaru);
        $this->board->catat('anggota_ditambah', $user?->nama ?? $user?->name);
        $this->reset('anggotaBaru');
        $this->segarkan();
    }

    public function keluarkanAnggota(int $userId): void
    {
        $this->wajibKelola();
        abort_if((int) $userId === (int) $this->board->dibuat_oleh, 422);

        $this->board->anggota()->detach($userId);
        $this->segarkan();
    }

    public function tambahLabel(): void
    {
        $this->wajibKelola();
        $this->validate([
            'namaLabelBaru' => ['nullable', 'string', 'max:60'],
            'warnaLabelBaru' => ['required', Rule::in(array_keys(Warna::LABEL))],
        ]);

        Label::create(['board_id' => $this->board->id, 'nama' => trim($this->namaLabelBaru) ?: null, 'warna' => $this->warnaLabelBaru]);
        $this->reset('namaLabelBaru');
        $this->segarkan();
    }

    public function ubahLabel(int $labelId, string $nama): void
    {
        $this->wajibKelola();
        Label::where('board_id', $this->board->id)->findOrFail($labelId)->update(['nama' => mb_substr(trim($nama), 0, 60) ?: null]);
        $this->segarkan();
    }

    public function hapusLabel(int $labelId): void
    {
        $this->wajibKelola();
        Label::where('board_id', $this->board->id)->findOrFail($labelId)->delete();
        $this->saringLabel = array_values(array_diff($this->saringLabel, [$labelId]));
        $this->segarkan();
    }

    /** Simpan otomasi board Order (hanya admin pusat). */
    public function simpanOtomasi(): void
    {
        abort_unless($this->board->isOrder() && Akses::admin(auth()->user()), 403);

        $idList = $this->kolom->pluck('id')->map(fn ($id) => (string) $id)->all();
        foreach ($this->otomasi as $pemicu => $nilai) {
            abort_unless($nilai === '' || in_array((string) $nilai, $idList, true), 422);
        }

        OtomasiOrder::simpan($this->otomasi);
        $this->pesan = 'Otomasi board Order disimpan.';
    }

    public function gantiTampilan(string $tampilan): void
    {
        $this->tampilan = array_key_exists($tampilan, self::TAMPILAN) ? $tampilan : 'papan';
        $this->segarkan();
    }

    /** Klik kepala kolom tabel: urutkan, klik lagi untuk membalik arah. */
    public function urutkan(string $kolom): void
    {
        if (! array_key_exists($kolom, self::URUT_TABEL)) {
            return;
        }

        $this->arahTabel = $this->urutTabel === $kolom && $this->arahTabel === 'asc' ? 'desc' : 'asc';
        $this->urutTabel = $kolom;
        unset($this->baris);
    }

    public function geserBulan(int $langkah): void
    {
        $this->bulan = $this->bulanAktif->copy()->addMonths($langkah)->format('Y-m');
        unset($this->bulanAktif, $this->kalender);
    }

    public function bulanIni(): void
    {
        $this->bulan = now()->format('Y-m');
        unset($this->bulanAktif, $this->kalender);
    }

    public function bersihkanSaringan(): void
    {
        $this->reset(['cari', 'saringLabel', 'saringAnggota', 'saringTenggat']);
        $this->segarkan();
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, ['cari', 'saringTenggat'], true) || str_starts_with($properti, 'saring')) {
            unset($this->kolom, $this->baris, $this->kalender, $this->tanpaTenggat);
        }
    }

    public function render()
    {
        // Disetel saat menggambar supaya cap selalu mewakili yang dilihat pengguna.
        $this->cap = $this->capBoard();

        return view('livewire.kanban.papan-board')->title($this->board->nama);
    }
}
