<?php

namespace App\Livewire\Kanban;

use App\Models\Kanban\Aktivitas;
use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Komentar;
use App\Models\Kanban\Label;
use App\Models\Kanban\Lampiran;
use App\Models\Kanban\Reaksi;
use App\Models\User;
use App\Notifications\KanbanKabar;
use App\Services\Kanban\Kabar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Posisi;
use App\Support\Kanban\Warna;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Jendela detail kartu — padanan "card back" di Trello. */
class DetailKartu extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $kartuId;

    public string $judul = '';

    public string $deskripsi = '';

    public bool $ubahDeskripsi = false;

    public string $komentarBaru = '';

    public ?int $ubahKomentarId = null;

    public string $isiKomentar = '';

    public string $judulChecklist = 'Checklist';

    /** Isian item baru per checklist: [checklistId => teks]. */
    public array $itemBaru = [];

    public ?string $mulai = null;

    public ?string $tenggat = null;

    public array $berkas = [];

    public string $tautanUrl = '';

    public string $tautanNama = '';

    public ?int $pindahBoard = null;

    public ?int $pindahKolom = null;

    public int $pindahUrutan = 1;

    /** Sidik jari isi kartu, untuk pemeriksaan berkala tanpa gambar ulang. */
    public ?string $cap = null;

    public string $judulSalinan = '';

    public ?int $salinKolom = null;

    /** Yang ikut disalin: label, anggota, checklist, lampiran. */
    public array $bawaSalinan = ['label', 'anggota', 'checklist'];

    public string $namaLabelBaru = '';

    public string $warnaLabelBaru = 'hijau';

    public function mount(int $kartuId): void
    {
        $this->kartuId = $kartuId;
        $kartu = $this->kartu;
        abort_unless(Akses::bolehLihat(auth()->user(), $kartu->board), 403);

        $this->isiDariKartu();
    }

    private function isiDariKartu(): void
    {
        $k = $this->kartu;
        $this->judul = $k->judul;
        $this->deskripsi = (string) $k->deskripsi;
        $this->mulai = $k->mulai_pada?->format('Y-m-d');
        $this->tenggat = $k->tenggat_pada?->format('Y-m-d\TH:i');
        $this->pindahBoard = $k->board_id;
        $this->pindahKolom = $k->kolom_id;
        $this->judulSalinan = $k->judul;
        $this->salinKolom = $k->kolom_id;
    }

    // ---------------- Data ----------------

    #[Computed]
    public function kartu(): Kartu
    {
        return Kartu::with([
            'board', 'kolom', 'label', 'anggota:id,nama,name', 'coverLampiran',
            'checklist.item',
            'lampiran.pengunggah:id,nama,name',
            'order' => fn ($q) => $q->with(['sekolah:id,nama', 'marketing:id,nama,name', 'cabang:id,nama']),
        ])->findOrFail($this->kartuId);
    }

    #[Computed]
    public function bolehUbah(): bool
    {
        return Akses::bolehUbah(auth()->user(), $this->kartu->board) && $this->kartu->diarsipkan_at === null;
    }

    #[Computed]
    public function admin(): bool
    {
        return Akses::admin(auth()->user());
    }

    #[Computed]
    public function labelBoard(): Collection
    {
        return $this->kartu->board->label()->get();
    }

    /** Calon anggota kartu: anggota board, atau semua staf untuk board order. */
    #[Computed]
    public function calonAnggota(): Collection
    {
        $board = $this->kartu->board;

        return $board->isOrder()
            ? User::role(Akses::PERAN_STAF)->orderByRaw('coalesce(nama, name)')->get(['id', 'nama', 'name'])
            : $board->anggota()->wherePivot('peran', '!=', 'pengamat')->orderByRaw('coalesce(nama, name)')->get(['users.id', 'nama', 'name']);
    }

    /** Nama yang bisa disebut dengan @ di komentar (untuk pelengkap otomatis). */
    #[Computed]
    public function namaCalon(): array
    {
        return $this->kabar()->calon($this->kartu->board)
            ->map(fn ($u) => $u->nama ?? $u->name)
            ->filter()->values()->all();
    }

    /** Riwayat: komentar & aktivitas digabung, terbaru di atas. */
    #[Computed]
    public function riwayat(): Collection
    {
        $komentar = Komentar::where('kartu_id', $this->kartuId)->with(['penulis:id,nama,name', 'reaksi.pemberi:id,nama,name'])->get()
            ->map(fn ($k) => ['jenis' => 'komentar', 'waktu' => $k->created_at, 'data' => $k]);
        $aktivitas = Aktivitas::where('kartu_id', $this->kartuId)->with('pelaku:id,nama,name')->get()
            ->map(fn ($a) => ['jenis' => 'aktivitas', 'waktu' => $a->created_at, 'data' => $a]);

        return $komentar->concat($aktivitas)->sortByDesc(fn ($r) => [$r['waktu']?->getTimestamp(), $r['data']->id])->values()->take(60);
    }

    #[Computed]
    public function boardTujuan(): Collection
    {
        $user = auth()->user();

        return Board::aktif()->orderByRaw("case when jenis = 'order' then 0 else 1 end")->orderBy('nama')->get()
            ->filter(fn (Board $b) => Akses::bolehUbah($user, $b))->values();
    }

    #[Computed]
    public function kolomTujuan(): Collection
    {
        if (! $this->pindahBoard) {
            return collect();
        }

        return Kolom::where('board_id', $this->pindahBoard)->whereNull('diarsipkan_at')->orderBy('posisi')
            ->withCount(['kartu'])->get();
    }

    private function capKartu(): string
    {
        $k = $this->kartu;

        return implode('|', [
            $k->updated_at,
            $k->komentar()->max('id'),
            $k->lampiran()->max('id'),
            $k->checklistItem()->max('kanban_checklist_item.id'),
            $k->checklistItem()->whereNotNull('selesai_at')->count(),
            $k->aktivitas()->max('id'),
            Reaksi::whereIn('komentar_id', $k->komentar()->select('id'))->count(),
            $k->anggota()->count(),
            $k->label()->count(),
        ]);
    }

    /** Dipanggil berkala saat kartu terbuka; hanya menggambar ulang bila ada perubahan. */
    public function cek(): void
    {
        unset($this->kartu);

        if ($this->capKartu() === $this->cap) {
            $this->skipRender();

            return;
        }

        $this->segarkan(false);
    }

    private function segarkan(bool $papan = true): void
    {
        unset($this->kartu, $this->riwayat, $this->labelBoard, $this->bolehUbah);
        if ($papan) {
            $this->dispatch('kartu-berubah');
        }
    }

    private function wajibUbah(): Kartu
    {
        abort_unless($this->bolehUbah, 403);

        return $this->kartu;
    }

    private function catat(string $aksi, ?string $keterangan = null): void
    {
        $this->kartu->board->catat($aksi, $keterangan, $this->kartu);
    }

    private function kabar(): Kabar
    {
        return app(Kabar::class);
    }

    #[Computed]
    public function mengikuti(): bool
    {
        return $this->kabar()->mengikuti($this->kartu, auth()->id());
    }

    /** Ikuti / berhenti ikuti kartu — penentu siapa yang dapat lonceng & email. */
    public function toggleIkut(): void
    {
        $this->mengikuti
            ? $this->kabar()->berhentiIkut($this->kartu, auth()->id())
            : $this->kabar()->ikut($this->kartu, auth()->id());

        unset($this->mengikuti);
    }

    public function tutup(): void
    {
        $this->dispatch('kartu-ditutup');
    }

    // ---------------- Judul & deskripsi ----------------

    public function simpanJudul(): void
    {
        $kartu = $this->wajibUbah();
        $judul = mb_substr(trim($this->judul), 0, 255);
        if ($judul === '' || $judul === $kartu->judul) {
            $this->judul = $kartu->judul;

            return;
        }
        $kartu->update(['judul' => $judul]);
        $this->catat('kartu_diubah', 'judul');
        $this->segarkan();
    }

    public function simpanDeskripsi(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate(['deskripsi' => ['nullable', 'string', 'max:20000']]);
        $kartu->update(['deskripsi' => trim($this->deskripsi) ?: null]);
        if (trim($this->deskripsi) !== '') {
            $this->kabar()->deskripsi($kartu, $this->deskripsi, auth()->user());
        }
        $this->ubahDeskripsi = false;
        $this->segarkan();
    }

    public function batalDeskripsi(): void
    {
        $this->deskripsi = (string) $this->kartu->deskripsi;
        $this->ubahDeskripsi = false;
    }

    // ---------------- Label & anggota ----------------

    public function toggleLabel(int $labelId): void
    {
        $kartu = $this->wajibUbah();
        $label = Label::where('board_id', $kartu->board_id)->findOrFail($labelId);
        $kartu->label()->toggle([$label->id]);
        $this->segarkan();
    }

    public function buatLabel(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate([
            'namaLabelBaru' => ['nullable', 'string', 'max:60'],
            'warnaLabelBaru' => ['required', Rule::in(array_keys(Warna::LABEL))],
        ]);
        $label = Label::create(['board_id' => $kartu->board_id, 'nama' => trim($this->namaLabelBaru) ?: null, 'warna' => $this->warnaLabelBaru]);
        $kartu->label()->attach($label->id);
        $this->reset('namaLabelBaru');
        $this->segarkan();
    }

    public function toggleAnggota(int $userId): void
    {
        $kartu = $this->wajibUbah();
        abort_unless($this->calonAnggota->contains('id', $userId) || $kartu->anggota->contains('id', $userId), 403);

        $hasil = $kartu->anggota()->toggle([$userId]);
        $user = User::find($userId);
        $this->catat($hasil['attached'] ? 'anggota_kartu_ditambah' : 'anggota_kartu_dilepas', $user?->nama ?? $user?->name);
        if ($hasil['attached'] && $user) {
            $this->kabar()->ditugaskan($kartu, $user, auth()->user());
        }
        $this->segarkan();
    }

    // ---------------- Tanggal ----------------

    public function simpanTanggal(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate([
            'mulai' => ['nullable', 'date'],
            'tenggat' => ['nullable', 'date'],
        ]);

        $tenggatBaru = $this->tenggat ? Carbon::parse($this->tenggat) : null;
        $berubah = $tenggatBaru?->format('Y-m-d H:i') !== $kartu->tenggat_pada?->format('Y-m-d H:i');

        $kartu->update([
            'mulai_pada' => $this->mulai ?: null,
            'tenggat_pada' => $tenggatBaru,
            // Tenggat yang digeser dianggap belum selesai lagi, dan boleh diingatkan lagi.
            'tenggat_selesai_at' => $berubah ? null : $kartu->tenggat_selesai_at,
            'diingatkan_at' => $berubah ? null : $kartu->diingatkan_at,
        ]);
        if ($berubah) {
            $this->catat('tenggat_diubah', $tenggatBaru ? $tenggatBaru->translatedFormat('j M Y H:i') : 'dihapus');
        }
        $this->segarkan();
    }

    public function hapusTanggal(): void
    {
        $this->mulai = null;
        $this->tenggat = null;
        $this->simpanTanggal();
    }

    public function toggleTenggatSelesai(): void
    {
        $kartu = $this->wajibUbah();
        if (! $kartu->tenggat_pada) {
            return;
        }
        $selesai = $kartu->tenggat_selesai_at === null;
        $kartu->update(['tenggat_selesai_at' => $selesai ? now() : null]);
        if ($selesai) {
            $this->catat('tenggat_selesai');
        }
        $this->segarkan();
    }

    // ---------------- Checklist ----------------

    public function tambahChecklist(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate(['judulChecklist' => ['required', 'string', 'max:120']]);
        Checklist::create([
            'kartu_id' => $kartu->id,
            'judul' => trim($this->judulChecklist),
            'posisi' => (float) Checklist::where('kartu_id', $kartu->id)->max('posisi') + Posisi::JARAK,
        ]);
        $this->judulChecklist = 'Checklist';
        $this->segarkan();
    }

    private function checklistMilikKartu(int $id): Checklist
    {
        return Checklist::where('kartu_id', $this->kartuId)->findOrFail($id);
    }

    private function itemMilikKartu(int $id): ChecklistItem
    {
        return ChecklistItem::whereHas('checklist', fn ($q) => $q->where('kartu_id', $this->kartuId))->findOrFail($id);
    }

    public function ubahJudulChecklist(int $id, string $judul): void
    {
        $this->wajibUbah();
        $judul = mb_substr(trim($judul), 0, 120);
        if ($judul !== '') {
            $this->checklistMilikKartu($id)->update(['judul' => $judul]);
        }
        $this->segarkan(false);
    }

    public function hapusChecklist(int $id): void
    {
        $this->wajibUbah();
        $this->checklistMilikKartu($id)->delete();
        $this->segarkan();
    }

    public function tambahItem(int $checklistId): void
    {
        $this->wajibUbah();
        $checklist = $this->checklistMilikKartu($checklistId);
        $teks = mb_substr(trim((string) ($this->itemBaru[$checklistId] ?? '')), 0, 500);
        if ($teks === '') {
            return;
        }
        ChecklistItem::create([
            'checklist_id' => $checklist->id,
            'teks' => $teks,
            'posisi' => (float) ChecklistItem::where('checklist_id', $checklist->id)->max('posisi') + Posisi::JARAK,
        ]);
        $this->itemBaru[$checklistId] = '';
        $this->segarkan();
    }

    public function toggleItem(int $itemId): void
    {
        $this->wajibUbah();
        $item = $this->itemMilikKartu($itemId);
        $selesai = $item->selesai_at === null;
        $item->update(['selesai_at' => $selesai ? now() : null, 'selesai_oleh' => $selesai ? auth()->id() : null]);
        if ($selesai) {
            $this->catat('checklist_selesai', $item->teks);
        }
        $this->segarkan();
    }

    /** Tugaskan item ke seorang anggota (kosongkan untuk melepas). */
    public function tugaskanItem(int $itemId, ?int $userId): void
    {
        $this->wajibUbah();
        $item = $this->itemMilikKartu($itemId);

        if ($userId !== null) {
            abort_unless($this->calonAnggota->contains('id', $userId), 403);
        }

        $item->update(['user_id' => $userId]);

        if ($userId && $userId !== auth()->id()) {
            $this->kabar()->ditugaskanItem($this->kartu, User::findOrFail($userId), auth()->user(), $item->teks);
        }

        $this->segarkan();
    }

    /** Beri (atau hapus) tenggat pada satu item checklist. */
    public function tenggatItem(int $itemId, ?string $tenggat): void
    {
        $this->wajibUbah();
        $item = $this->itemMilikKartu($itemId);

        $item->update(['tenggat_pada' => $tenggat ? Carbon::parse($tenggat) : null]);
        $this->segarkan();
    }

    /**
     * Ubah item checklist menjadi kartu tersendiri di list yang sama
     * (padanan "Convert to card" Trello). Itemnya ikut terhapus.
     */
    public function itemJadiKartu(int $itemId): void
    {
        $kartu = $this->wajibUbah();
        $item = $this->itemMilikKartu($itemId);
        $kolom = Kolom::findOrFail($kartu->kolom_id);

        $baru = app(Tata::class)->tambahKartu($kolom, $item->teks, auth()->user(), array_filter([
            'tenggat_pada' => $item->tenggat_pada,
        ]));

        if ($item->user_id) {
            $baru->anggota()->attach($item->user_id);
        }

        $item->delete();
        $this->catat('item_jadi_kartu', $baru->judul);
        $this->segarkan();
    }

    public function ubahItem(int $itemId, string $teks): void
    {
        $this->wajibUbah();
        $teks = mb_substr(trim($teks), 0, 500);
        if ($teks !== '') {
            $this->itemMilikKartu($itemId)->update(['teks' => $teks]);
        }
        $this->segarkan(false);
    }

    public function hapusItem(int $itemId): void
    {
        $this->wajibUbah();
        $this->itemMilikKartu($itemId)->delete();
        $this->segarkan();
    }

    // ---------------- Komentar ----------------

    public function kirimKomentar(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate(['komentarBaru' => ['required', 'string', 'max:5000']], ['komentarBaru.required' => 'Tulis komentar dulu.']);
        $isi = trim($this->komentarBaru);
        Komentar::create(['kartu_id' => $kartu->id, 'user_id' => auth()->id(), 'isi' => $isi]);
        $this->kabar()->komentar($kartu, $isi, auth()->user());
        $this->reset('komentarBaru');
        $this->segarkan();
    }

    private function komentarMilikSaya(int $id, bool $bolehAdmin = false): Komentar
    {
        $komentar = Komentar::where('kartu_id', $this->kartuId)->findOrFail($id);
        abort_unless((int) $komentar->user_id === (int) auth()->id() || ($bolehAdmin && $this->admin), 403);

        return $komentar;
    }

    public function mulaiUbahKomentar(int $id): void
    {
        $komentar = $this->komentarMilikSaya($id);
        $this->ubahKomentarId = $komentar->id;
        $this->isiKomentar = $komentar->isi;
    }

    public function simpanKomentar(): void
    {
        $this->wajibUbah();
        $komentar = $this->komentarMilikSaya((int) $this->ubahKomentarId);
        $this->validate(['isiKomentar' => ['required', 'string', 'max:5000']]);
        $komentar->update(['isi' => trim($this->isiKomentar), 'diubah_at' => now()]);
        $this->reset(['ubahKomentarId', 'isiKomentar']);
        $this->segarkan(false);
    }

    /** Beri atau tarik reaksi emoji pada komentar. */
    public function toggleReaksi(int $komentarId, string $emoji): void
    {
        $this->wajibUbah();
        abort_unless(in_array($emoji, Reaksi::PILIHAN, true), 422);

        $komentar = Komentar::where('kartu_id', $this->kartuId)->findOrFail($komentarId);
        $punya = Reaksi::where(['komentar_id' => $komentar->id, 'user_id' => auth()->id(), 'emoji' => $emoji])->first();

        $punya
            ? $punya->delete()
            : Reaksi::create(['komentar_id' => $komentar->id, 'user_id' => auth()->id(), 'emoji' => $emoji, 'created_at' => now()]);

        $this->segarkan(false);
    }

    public function hapusKomentar(int $id): void
    {
        $this->wajibUbah();
        $this->komentarMilikSaya($id, bolehAdmin: true)->delete();
        $this->segarkan();
    }

    // ---------------- Lampiran & sampul ----------------

    public function updatedBerkas(): void
    {
        $kartu = $this->wajibUbah();
        $maks = (int) config('kanban.maks_lampiran_kb');
        $this->validate(
            ['berkas' => ['array', 'max:10'], 'berkas.*' => ['file', 'max:'.$maks]],
            ['berkas.*.max' => 'Ukuran berkas maksimal '.round($maks / 1024).' MB.', 'berkas.max' => 'Maksimal 10 berkas sekaligus.'],
        );

        foreach ($this->berkas as $file) {
            $path = $file->store('kanban/'.$kartu->board_id.'/'.$kartu->id, 'local');
            $lampiran = Lampiran::create([
                'kartu_id' => $kartu->id,
                'user_id' => auth()->id(),
                'nama' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
            ]);
            // Gambar pertama otomatis jadi sampul, seperti Trello.
            if (! $kartu->cover_lampiran_id && ! $kartu->cover_warna && $lampiran->isGambar()) {
                $kartu->update(['cover_lampiran_id' => $lampiran->id]);
            }
            $this->catat('lampiran', $lampiran->nama);
        }

        $this->reset('berkas');
        $this->segarkan();
    }

    /** Lampirkan tautan, mis. folder Google Drive (padanan "Attach a link"). */
    public function tambahTautan(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate([
            'tautanUrl' => ['required', 'url', 'max:2048', 'starts_with:http://,https://'],
            'tautanNama' => ['nullable', 'string', 'max:255'],
        ], [
            'tautanUrl.required' => 'Tempel tautannya dulu.',
            'tautanUrl.url' => 'Tautan tidak dikenali.',
            'tautanUrl.starts_with' => 'Tautan harus diawali http:// atau https://.',
        ]);

        $lampiran = Lampiran::create([
            'kartu_id' => $kartu->id,
            'user_id' => auth()->id(),
            'nama' => mb_substr(trim($this->tautanNama) ?: $this->tautanUrl, 0, 255),
            'url' => $this->tautanUrl,
        ]);

        $this->catat('lampiran', $lampiran->nama);
        $this->reset(['tautanUrl', 'tautanNama']);
        $this->segarkan();
    }

    /** Seret item checklist: boleh pindah urutan dan pindah checklist dalam kartu yang sama. */
    public function urutItem(int|string $itemId, int $posisi, int|string $checklistId): void
    {
        $this->wajibUbah();
        $item = $this->itemMilikKartu((int) $itemId);
        $checklist = $this->checklistMilikKartu((int) $checklistId);

        $lain = ChecklistItem::where('checklist_id', $checklist->id)
            ->whereKeyNot($item->id)->orderBy('posisi')->pluck('posisi')->all();

        $baru = Posisi::untukIndeks($lain, $posisi);
        if ($baru === null) {
            foreach (ChecklistItem::where('checklist_id', $checklist->id)->orderBy('posisi')->get() as $i => $lama) {
                $lama->update(['posisi' => ($i + 1) * Posisi::JARAK]);
            }
            $lain = ChecklistItem::where('checklist_id', $checklist->id)
                ->whereKeyNot($item->id)->orderBy('posisi')->pluck('posisi')->all();
            $baru = Posisi::untukIndeks($lain, $posisi);
        }

        $item->update(['checklist_id' => $checklist->id, 'posisi' => $baru]);
        $this->segarkan();
    }

    public function hapusLampiran(int $id): void
    {
        $kartu = $this->wajibUbah();
        $lampiran = Lampiran::where('kartu_id', $kartu->id)->findOrFail($id);
        abort_unless((int) $lampiran->user_id === (int) auth()->id() || $this->admin, 403);

        DB::transaction(function () use ($kartu, $lampiran) {
            if ((int) $kartu->cover_lampiran_id === $lampiran->id) {
                $kartu->update(['cover_lampiran_id' => null]);
            }
            $lampiran->delete();
        });
        if (! $lampiran->isTautan()) {
            Storage::disk('local')->delete($lampiran->path);
        }

        $this->segarkan();
    }

    public function jadikanSampul(?int $lampiranId): void
    {
        $kartu = $this->wajibUbah();
        if ($lampiranId !== null) {
            abort_unless(Lampiran::where('kartu_id', $kartu->id)->whereKey($lampiranId)->first()?->isGambar(), 422);
        }
        $kartu->update(['cover_lampiran_id' => $lampiranId, 'cover_warna' => null]);
        $this->segarkan();
    }

    public function warnaSampul(?string $warna): void
    {
        $kartu = $this->wajibUbah();
        abort_unless($warna === null || array_key_exists($warna, Warna::LABEL), 422);
        $kartu->update(['cover_warna' => $warna, 'cover_lampiran_id' => null]);
        $this->segarkan();
    }

    // ---------------- Pindah, arsip, hapus ----------------

    public function updatedPindahBoard(): void
    {
        unset($this->kolomTujuan);
        $this->pindahKolom = $this->kolomTujuan->first()?->id;
        $this->pindahUrutan = 1;
    }

    public function pindahkan(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate([
            'pindahBoard' => ['required', 'integer'],
            'pindahKolom' => ['required', 'integer'],
            'pindahUrutan' => ['required', 'integer', 'min:1'],
        ], ['pindahKolom.required' => 'Pilih list tujuan.']);

        $board = $this->boardTujuan->firstWhere('id', $this->pindahBoard);
        abort_unless($board, 403);
        $kolom = Kolom::where('board_id', $board->id)->whereNull('diarsipkan_at')->findOrFail($this->pindahKolom);

        app(Tata::class)->pindahKartu($kartu, $kolom, $this->pindahUrutan - 1, auth()->user());
        $this->kabar()->perubahan($kartu, KanbanKabar::KARTU_PINDAH, auth()->user(), 'ke list '.$kolom->nama);
        $this->segarkan();
    }

    /** Salin kartu ke list pilihan, lalu buka salinannya (seperti Trello). */
    public function salin(): void
    {
        $kartu = $this->wajibUbah();
        $this->validate([
            'judulSalinan' => ['required', 'string', 'max:255'],
            'salinKolom' => ['required', 'integer'],
        ], ['judulSalinan.required' => 'Beri judul salinan.', 'salinKolom.required' => 'Pilih list tujuan.']);

        $kolom = Kolom::whereNull('diarsipkan_at')->findOrFail($this->salinKolom);
        abort_unless(Akses::bolehUbah(auth()->user(), $kolom->board), 403);

        $salinan = app(Tata::class)->salinKartu($kartu, $kolom, $this->judulSalinan, $this->bawaSalinan, auth()->user());

        $this->dispatch('kartu-berubah');
        $this->dispatch('buka-kartu', kartuId: $salinan->id);
    }

    /** Tandai kartu sebagai templat (cetakan kartu baru). */
    public function toggleTemplat(): void
    {
        $kartu = $this->wajibUbah();
        abort_if($kartu->order_id !== null, 422);

        $kartu->update(['templat' => ! $kartu->templat]);
        $this->catat($kartu->templat ? 'kartu_jadi_templat' : 'kartu_bukan_templat');
        $this->segarkan();
    }

    public function arsipkan(): void
    {
        $kartu = $this->wajibUbah();
        $kartu->update(['diarsipkan_at' => now()]);
        $this->catat('kartu_diarsipkan');
        $this->kabar()->perubahan($kartu, KanbanKabar::KARTU_DIARSIPKAN, auth()->user());
        $this->segarkan();
    }

    public function pulihkan(): void
    {
        $kartu = $this->kartu;
        abort_unless(Akses::bolehUbah(auth()->user(), $kartu->board), 403);
        $kolom = Kolom::find($kartu->kolom_id);
        if ($kolom?->diarsipkan_at) {
            $this->addError('arsip', 'List kartu ini masih diarsipkan — pulihkan list-nya dulu.');

            return;
        }
        $kartu->update([
            'diarsipkan_at' => null,
            'posisi' => (float) Kartu::where('kolom_id', $kartu->kolom_id)->whereNull('diarsipkan_at')->max('posisi') + Posisi::JARAK,
        ]);
        $this->catat('kartu_dipulihkan');
        $this->segarkan();
    }

    /** Hapus permanen — hanya kartu yang sudah diarsipkan, dan bukan kartu order. */
    public function hapus(): void
    {
        $kartu = $this->kartu;
        abort_unless(Akses::bolehUbah(auth()->user(), $kartu->board), 403);
        abort_unless($kartu->diarsipkan_at !== null && $kartu->order_id === null, 422);

        $paths = $kartu->lampiran->reject->isTautan()->pluck('path')->all();
        $kartu->board->catat('kartu_dihapus', $kartu->judul);
        $kartu->delete();
        Storage::disk('local')->delete($paths);

        $this->dispatch('kartu-ditutup');
    }

    public function render()
    {
        $this->cap = $this->capKartu();

        return view('livewire.kanban.detail-kartu');
    }
}
