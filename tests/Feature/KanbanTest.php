<?php

namespace Tests\Feature;

use App\Livewire\Kanban\Beranda;
use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\PapanBoard;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Checklist;
use App\Models\Kanban\ChecklistItem;
use App\Models\Kanban\Kartu;
use App\Models\Kanban\Kolom;
use App\Models\Kanban\Komentar;
use App\Models\Kanban\Lampiran;
use App\Models\User;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use App\Support\Kanban\Posisi;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kanban ala Trello di subdomain: board bebas, list & kartu yang bisa
 * diseret, detail kartu (checklist, komentar, lampiran), dan hak akses.
 */
class KanbanTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://app.8mataair.com';

    private Cabang $bdg;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config(['kanban.domain' => 'app.8mataair.com', 'app.url' => 'https://8mataair.com']);
        $this->bdg = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
    }

    private function staf(string $role = 'editor', string $nama = 'Staf'): User
    {
        $u = User::factory()->create(['nama' => $nama, 'cabang_id' => $this->bdg->id]);
        $u->assignRole($role);

        return $u;
    }

    private function tata(): Tata
    {
        return app(Tata::class);
    }

    /** Board bebas milik $pemilik dengan list "To do" & "Selesai". */
    private function boardBebas(User $pemilik, string $visibilitas = 'workspace'): Board
    {
        $board = $this->tata()->buatBoard('5. Editing', 'biru', $visibilitas, $pemilik);
        $this->tata()->tambahKolom($board, 'To do', $pemilik);
        $this->tata()->tambahKolom($board, 'Selesai', $pemilik);

        return $board->fresh();
    }

    private function judulKartu(Kolom $kolom): array
    {
        return Kartu::where('kolom_id', $kolom->id)->whereNull('diarsipkan_at')->orderBy('posisi')->pluck('judul')->all();
    }

    /** Potongan HTML judul kartu di papan (bukan di panel aktivitas). */
    private static function kartu(string $judul): string
    {
        return 'text-sm text-ink">'.$judul.'</span>';
    }

    // ---------------- Subdomain ----------------

    public function test_akar_subdomain_diarahkan_ke_kanban_lalu_login(): void
    {
        $this->get(self::HOST.'/')->assertRedirect(route('kanban.beranda'));
        $this->get(self::HOST.'/kanban')->assertRedirect();
        $this->assertStringContainsString('/login', $this->get(self::HOST.'/kanban')->headers->get('Location'));
    }

    public function test_halaman_lain_di_subdomain_dialihkan_ke_domain_utama(): void
    {
        $this->get(self::HOST.'/app/dashboard?x=1')->assertRedirect('https://8mataair.com/app/dashboard?x=1');
        $this->get(self::HOST.'/katalog')->assertRedirect('https://8mataair.com/katalog');
    }

    public function test_domain_utama_tidak_terpengaruh(): void
    {
        $this->get('http://localhost/login')->assertOk();
        $this->get('http://localhost/')->assertStatus(200);
    }

    public function test_login_di_subdomain_langsung_ke_kanban(): void
    {
        $user = $this->staf();

        $this->get(self::HOST.'/login')->assertOk();
        $this->post(self::HOST.'/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/kanban');
    }

    public function test_login_di_domain_utama_tetap_ke_dashboard(): void
    {
        $user = $this->staf();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('app.dashboard', absolute: false));
    }

    public function test_staf_yang_sudah_login_membuka_login_subdomain_diarahkan_ke_kanban(): void
    {
        $this->actingAs($this->staf())->get(self::HOST.'/login')->assertRedirect(self::HOST.'/kanban');
    }

    public function test_beranda_dan_board_bisa_dibuka_staf(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);

        $this->actingAs($user)->get(self::HOST.'/kanban')->assertOk()->assertSee('5. Editing')->assertSee('Order');
        $this->actingAs($user)->get(self::HOST.'/kanban/b/'.$board->id)->assertOk()->assertSee('To do');
    }

    public function test_pengguna_sekolah_tidak_boleh_membuka_kanban(): void
    {
        $sekolah = User::factory()->create();

        $this->actingAs($sekolah)->get(self::HOST.'/kanban')->assertForbidden();
    }

    // ---------------- Beranda ----------------

    public function test_buat_board_dengan_label_bawaan_dan_pembuat_sebagai_admin(): void
    {
        $user = $this->staf();

        Livewire::actingAs($user)->test(Beranda::class)
            ->call('bukaFormBuat')
            ->set('nama', 'Desain')
            ->set('warna', 'hijau')
            ->set('visibilitas', 'privat')
            ->call('buat')
            ->assertHasNoErrors()
            ->assertRedirect();

        $board = Board::where('nama', 'Desain')->firstOrFail();
        $this->assertSame('privat', $board->visibilitas);
        $this->assertSame(6, $board->label()->count());
        $this->assertSame('admin', $board->anggota()->first()->pivot->peran);
        $this->assertTrue(Akses::bolehKelola($user, $board));
    }

    public function test_board_privat_tersembunyi_dari_staf_lain(): void
    {
        $pemilik = $this->staf();
        $lain = $this->staf('tim_event', 'Lain');
        $board = $this->boardBebas($pemilik, 'privat');

        Livewire::actingAs($lain)->test(Beranda::class)->assertDontSee('5. Editing');
        $this->actingAs($lain)->get(route('kanban.board', $board))->assertForbidden();

        // Admin pusat tetap bisa melihat.
        $this->actingAs($this->staf('operasional', 'Ops'))->get(route('kanban.board', $board))->assertOk();
    }

    public function test_bintang_tanpa_bergabung_tidak_menjadikan_anggota(): void
    {
        $pemilik = $this->staf();
        $lain = $this->staf('tim_event', 'Lain');
        $board = $this->boardBebas($pemilik);

        Livewire::actingAs($lain)->test(Beranda::class)->call('bintang', $board->id)->assertSee('Berbintang');

        $this->assertFalse(Akses::anggota($lain, $board));
        $this->assertFalse(Akses::bolehUbah($lain, $board));
    }

    // ---------------- Akses ----------------

    public function test_hak_akses_board(): void
    {
        $pemilik = $this->staf();
        $lain = $this->staf('marketing', 'Lain');
        $admin = $this->staf('admin_sales', 'Admin');
        $board = $this->boardBebas($pemilik);
        $order = Board::order();

        $this->assertTrue(Akses::bolehLihat($lain, $board));
        $this->assertFalse(Akses::bolehUbah($lain, $board));
        $this->assertTrue(Akses::bolehUbah($admin, $board));
        $this->assertTrue(Akses::bolehKelola($admin, $board));

        // Board order: semua staf boleh mengubah, tidak ada yang mengelola kecuali admin.
        $this->assertTrue(Akses::bolehUbah($lain, $order));
        $this->assertFalse(Akses::bolehKelola($pemilik, $order));

        $board->update(['diarsipkan_at' => now()]);
        $this->assertFalse(Akses::bolehUbah($pemilik, $board->fresh()));
    }

    public function test_bukan_anggota_tidak_bisa_menyeret_sebelum_gabung(): void
    {
        $pemilik = $this->staf();
        $lain = $this->staf('tim_event', 'Lain');
        $board = $this->boardBebas($pemilik);
        [$todo] = $board->kolom()->get();
        $kartu = $this->tata()->tambahKartu($todo, 'A', $pemilik);

        Livewire::actingAs($lain)->test(PapanBoard::class, ['board' => $board])
            ->assertSee('Gabung board')
            ->call('urutKartu', $kartu->id, 0, $todo->id)
            ->assertForbidden();

        Livewire::actingAs($lain)->test(PapanBoard::class, ['board' => $board])
            ->call('gabung')
            ->assertDontSee('Gabung board')
            ->call('tambahKolom')
            ->assertHasErrors('namaKolomBaru');

        $this->assertTrue(Akses::bolehUbah($lain, $board->fresh()));
    }

    // ---------------- Posisi & seret ----------------

    public function test_posisi_di_antara_tetangga(): void
    {
        $this->assertSame(Posisi::JARAK, Posisi::untukIndeks([], 0));
        $this->assertSame(50.0, Posisi::untukIndeks([100.0], 0));
        $this->assertSame(150.0, Posisi::untukIndeks([100.0, 200.0], 1));
        $this->assertSame(200.0 + Posisi::JARAK, Posisi::untukIndeks([100.0, 200.0], 5));
        $this->assertNull(Posisi::untukIndeks([1.0, 1.0000000001], 1));
    }

    public function test_seret_kartu_dalam_list_dan_antar_list(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);
        [$todo, $selesai] = $board->kolom()->get();
        foreach (['A', 'B', 'C'] as $j) {
            $this->tata()->tambahKartu($todo, $j, $user);
        }
        $c = Kartu::where('judul', 'C')->first();
        $a = Kartu::where('judul', 'A')->first();

        $papan = Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board]);

        $papan->call('urutKartu', $c->id, 0, $todo->id);
        $this->assertSame(['C', 'A', 'B'], $this->judulKartu($todo));

        $papan->call('urutKartu', $a->id, 0, $selesai->id);
        $this->assertSame(['C', 'B'], $this->judulKartu($todo));
        $this->assertSame(['A'], $this->judulKartu($selesai));
        $this->assertDatabaseHas('kanban_aktivitas', ['kartu_id' => $a->id, 'aksi' => 'kartu_pindah']);
    }

    public function test_seret_kartu_ke_list_board_lain_ditolak(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);
        $lain = $this->boardBebas($user);
        $kartu = $this->tata()->tambahKartu($board->kolom()->first(), 'A', $user);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])
            ->call('urutKartu', $kartu->id, 0, $lain->kolom()->first()->id);
    }

    public function test_urutan_dinomori_ulang_bila_celah_habis(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);
        $todo = $board->kolom()->first();
        $a = $this->tata()->tambahKartu($todo, 'A', $user, ['posisi' => 1.0]);
        $b = $this->tata()->tambahKartu($todo, 'B', $user, ['posisi' => 1.0000000001]);
        $c = $this->tata()->tambahKartu($todo, 'C', $user);

        $this->tata()->pindahKartu($c, $todo, 1, $user);

        $this->assertSame(['A', 'C', 'B'], $this->judulKartu($todo));
    }

    public function test_seret_list(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);
        [, $selesai] = $board->kolom()->get();

        Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])
            ->call('urutKolom', $selesai->id, 0);

        $this->assertSame(['Selesai', 'To do'], $board->kolom()->pluck('nama')->all());
    }

    // ---------------- List & kartu dari papan ----------------

    public function test_tambah_list_kartu_ubah_nama_dan_arsip_list(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);

        $papan = Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])
            ->set('namaKolomBaru', 'Revisi')->call('tambahKolom')->assertHasNoErrors();
        $revisi = Kolom::where('nama', 'Revisi')->firstOrFail();

        $papan->call('mulaiTambahKartu', $revisi->id)
            ->set('judulKartuBaru', 'TK Miftahul')->call('tambahKartu')
            ->assertSet('tambahKartuDi', $revisi->id)
            ->assertSeeHtml(self::kartu('TK Miftahul'));

        $papan->call('ubahNamaKolom', $revisi->id, 'Revisi klien')->assertSee('Revisi klien');

        $papan->call('arsipkanKolom', $revisi->id)->assertDontSeeHtml(self::kartu('TK Miftahul'));
        $papan->call('pulihkanKolom', $revisi->id)->assertSeeHtml(self::kartu('TK Miftahul'));
    }

    public function test_saring_kartu(): void
    {
        $user = $this->staf();
        $board = $this->boardBebas($user);
        $todo = $board->kolom()->first();
        $a = $this->tata()->tambahKartu($todo, 'SD Harapan', $user);
        $this->tata()->tambahKartu($todo, 'TK Pelita', $user, ['tenggat_pada' => now()->subDay()]);
        $label = $board->label()->first();
        $a->label()->attach($label->id);

        $papan = Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board]);
        $papan->set('cari', 'harapan')->assertSeeHtml(self::kartu('SD Harapan'))->assertDontSeeHtml(self::kartu('TK Pelita'));
        $papan->set('cari', '')->set('saringTenggat', 'lewat')->assertSeeHtml(self::kartu('TK Pelita'))->assertDontSeeHtml(self::kartu('SD Harapan'));
        $papan->set('saringTenggat', '')->set('saringLabel', [$label->id])->assertSeeHtml(self::kartu('SD Harapan'))->assertDontSeeHtml(self::kartu('TK Pelita'));
        $papan->call('bersihkanSaringan')->assertSeeHtml(self::kartu('SD Harapan'))->assertSeeHtml(self::kartu('TK Pelita'));
    }

    public function test_kelola_board_label_anggota_dan_arsip(): void
    {
        $pemilik = $this->staf();
        $calon = $this->staf('tim_event', 'Budi');
        $board = $this->boardBebas($pemilik);

        Livewire::actingAs($pemilik)->test(PapanBoard::class, ['board' => $board])
            ->call('ubahWarna', 'ungu')
            ->set('namaLabelBaru', 'Urgent')->set('warnaLabelBaru', 'merah')->call('tambahLabel')
            ->set('anggotaBaru', $calon->id)->call('tambahAnggota')
            ->assertHasNoErrors();

        $this->assertSame('ungu', $board->fresh()->warna);
        $this->assertDatabaseHas('kanban_label', ['board_id' => $board->id, 'nama' => 'Urgent']);
        $this->assertTrue(Akses::anggota($calon, $board));

        // Anggota biasa tidak bisa mengelola.
        Livewire::actingAs($calon)->test(PapanBoard::class, ['board' => $board])
            ->call('ubahWarna', 'hijau')->assertForbidden();

        Livewire::actingAs($pemilik)->test(PapanBoard::class, ['board' => $board])
            ->call('arsipkanBoard')->assertRedirect(route('kanban.beranda'));
        $this->assertNotNull($board->fresh()->diarsipkan_at);
    }

    public function test_board_order_tidak_bisa_diarsipkan(): void
    {
        $admin = $this->staf('super_admin', 'Admin');

        Livewire::actingAs($admin)->test(PapanBoard::class, ['board' => Board::order()])
            ->call('arsipkanBoard')->assertStatus(422);
    }

    // ---------------- Detail kartu ----------------

    private function kartuUji(?User $user = null): array
    {
        $user ??= $this->staf();
        $board = $this->boardBebas($user);
        $kartu = $this->tata()->tambahKartu($board->kolom()->first(), 'SD Harapan', $user);

        return [$user, $board, $kartu];
    }

    public function test_papan_membuka_detail_kartu_lewat_url(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();

        Livewire::actingAs($user)->withQueryParams(['kartu' => $kartu->id])
            ->test(PapanBoard::class, ['board' => $board])
            ->assertSee('Tulis komentar');

        Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])
            ->call('bukaKartu', $kartu->id)->assertSet('kartuId', $kartu->id)
            ->call('tutupKartu')->assertSet('kartuId', null);
    }

    public function test_ubah_judul_deskripsi_label_anggota_dan_tanggal(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();
        $label = $board->label()->first();

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('judul', 'SD Harapan Bangsa')->call('simpanJudul')
            ->set('deskripsi', 'Cek foto kelas 6')->call('simpanDeskripsi')
            ->call('toggleLabel', $label->id)
            ->call('toggleAnggota', $user->id)
            ->set('tenggat', '2026-10-01T10:00')->call('simpanTanggal')
            ->call('toggleTenggatSelesai')
            ->assertDispatched('kartu-berubah')
            ->assertHasNoErrors();

        $kartu->refresh();
        $this->assertSame('SD Harapan Bangsa', $kartu->judul);
        $this->assertSame('Cek foto kelas 6', $kartu->deskripsi);
        $this->assertTrue($kartu->label->contains($label));
        $this->assertTrue($kartu->anggota->contains($user));
        $this->assertSame('2026-10-01 10:00', $kartu->tenggat_pada->format('Y-m-d H:i'));
        $this->assertSame('selesai', $kartu->keadaanTenggat());

        // Menggeser tenggat membatalkan tanda selesai.
        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('tenggat', '2026-10-02T10:00')->call('simpanTanggal');
        $this->assertNull($kartu->fresh()->tenggat_selesai_at);
    }

    public function test_anggota_kartu_harus_anggota_board(): void
    {
        [$user, , $kartu] = $this->kartuUji();
        $luar = $this->staf('tim_event', 'Luar');

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('toggleAnggota', $luar->id)->assertForbidden();
    }

    public function test_label_board_lain_ditolak(): void
    {
        [$user, , $kartu] = $this->kartuUji();
        $lain = $this->boardBebas($user);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('toggleLabel', $lain->label()->first()->id);
    }

    public function test_checklist_dan_lencana_papan(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();

        $detail = Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('judulChecklist', 'QC')->call('tambahChecklist');
        $cl = Checklist::where('kartu_id', $kartu->id)->firstOrFail();

        $detail->set("itemBaru.{$cl->id}", 'Foto kelas')->call('tambahItem', $cl->id)
            ->set("itemBaru.{$cl->id}", 'Foto guru')->call('tambahItem', $cl->id);
        $item = ChecklistItem::where('teks', 'Foto kelas')->firstOrFail();
        $detail->call('toggleItem', $item->id)->assertSee('50%');

        $this->assertSame($user->id, $item->fresh()->selesai_oleh);
        Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])->assertSee('1/2');

        $detail->call('ubahItem', $item->id, 'Foto kelas 6')->call('hapusChecklist', $cl->id);
        $this->assertSame(0, ChecklistItem::count());
    }

    public function test_komentar_hanya_bisa_diubah_penulisnya(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();
        $rekan = $this->staf('tim_event', 'Rekan');
        $board->anggota()->attach($rekan->id, ['peran' => 'anggota']);

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('kirimKomentar')->assertHasErrors('komentarBaru')
            ->set('komentarBaru', 'Sudah dicek')->call('kirimKomentar')->assertSee('Sudah dicek');
        $komentar = Komentar::firstOrFail();

        Livewire::actingAs($rekan)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('hapusKomentar', $komentar->id)->assertForbidden();

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('mulaiUbahKomentar', $komentar->id)
            ->set('isiKomentar', 'Sudah dicek ulang')->call('simpanKomentar')
            ->assertSee('Sudah dicek ulang')->assertSee('(diubah)');

        // Admin pusat boleh menghapus komentar siapa pun.
        Livewire::actingAs($this->staf('operasional', 'Ops'))->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('hapusKomentar', $komentar->id);
        $this->assertSame(0, Komentar::count());
    }

    public function test_lampiran_sampul_unduh_dan_hapus(): void
    {
        Storage::fake('local');
        [$user, $board, $kartu] = $this->kartuUji();

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('berkas', [UploadedFile::fake()->image('foto.jpg', 40, 40), UploadedFile::fake()->create('daftar.pdf', 10, 'application/pdf')])
            ->assertHasNoErrors();

        $foto = Lampiran::where('nama', 'foto.jpg')->firstOrFail();
        $pdf = Lampiran::where('nama', 'daftar.pdf')->firstOrFail();
        Storage::disk('local')->assertExists($foto->path);
        $this->assertSame($foto->id, $kartu->fresh()->cover_lampiran_id, 'gambar pertama jadi sampul');

        $this->actingAs($user)->get(route('kanban.lampiran', $foto))->assertOk();
        $this->assertStringContainsString('attachment', $this->actingAs($user)->get(route('kanban.lampiran', ['lampiran' => $foto, 'unduh' => 1]))->headers->get('Content-Disposition'));

        // Board privat: staf lain tidak bisa mengunduh.
        $board->update(['visibilitas' => 'privat']);
        $this->actingAs($this->staf('tim_event', 'Lain'))->get(route('kanban.lampiran', $pdf))->assertForbidden();

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('jadikanSampul', $pdf->id)->assertStatus(422);

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('hapusLampiran', $foto->id);
        $this->assertNull($kartu->fresh()->cover_lampiran_id);
        Storage::disk('local')->assertMissing($foto->path);
    }

    public function test_lampiran_melebihi_batas_ditolak(): void
    {
        Storage::fake('local');
        config(['kanban.maks_lampiran_kb' => 100]);
        [$user, , $kartu] = $this->kartuUji();

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('berkas', [UploadedFile::fake()->create('besar.zip', 200)])
            ->assertHasErrors('berkas.0');
        $this->assertSame(0, Lampiran::count());
    }

    public function test_pindahkan_kartu_ke_board_lain_melepas_label(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();
        $kartu->label()->attach($board->label()->first()->id);
        $tujuan = $this->boardBebas($user);
        $selesai = $tujuan->kolom()->get()[1];

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('pindahBoard', $tujuan->id)
            ->assertSet('pindahKolom', $tujuan->kolom()->first()->id)
            ->set('pindahKolom', $selesai->id)
            ->call('pindahkan')
            ->assertHasNoErrors();

        $kartu->refresh();
        $this->assertSame($tujuan->id, $kartu->board_id);
        $this->assertSame($selesai->id, $kartu->kolom_id);
        $this->assertCount(0, $kartu->label);
    }

    public function test_tidak_bisa_memindah_ke_board_yang_tidak_boleh_diubah(): void
    {
        [$user, , $kartu] = $this->kartuUji();
        $asing = $this->boardBebas($this->staf('editor', 'Asing'));

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->set('pindahBoard', $asing->id)
            ->set('pindahKolom', $asing->kolom()->first()->id)
            ->call('pindahkan')
            ->assertForbidden();
    }

    public function test_arsip_pulihkan_dan_hapus_kartu(): void
    {
        [$user, $board, $kartu] = $this->kartuUji();

        $detail = Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id]);

        // Belum diarsipkan → tidak bisa dihapus.
        $detail->call('hapus')->assertStatus(422);

        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('arsipkan')->assertSee('Kartu ini diarsipkan.')
            ->call('toggleLabel', $board->label()->first()->id)->assertForbidden();

        Livewire::actingAs($user)->test(PapanBoard::class, ['board' => $board])
            ->assertDontSeeHtml(self::kartu('SD Harapan'))
            ->call('pulihkanKartu', $kartu->id);
        $this->assertNull($kartu->fresh()->diarsipkan_at);

        $kartu->update(['diarsipkan_at' => now()]);
        Livewire::actingAs($user)->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->call('hapus')->assertDispatched('kartu-ditutup');
        $this->assertDatabaseMissing('kanban_kartu', ['id' => $kartu->id]);
        $this->assertDatabaseHas('kanban_aktivitas', ['aksi' => 'kartu_dihapus', 'keterangan' => 'SD Harapan']);
    }

    public function test_kartu_board_privat_tidak_bisa_dibuka_orang_luar(): void
    {
        [, $board, $kartu] = $this->kartuUji();
        $board->update(['visibilitas' => 'privat']);

        Livewire::actingAs($this->staf('tim_event', 'Luar'))
            ->test(DetailKartu::class, ['kartuId' => $kartu->id])
            ->assertForbidden();
    }
}
