<?php

namespace Tests\Feature;

use App\Livewire\Kanban\DetailKartu;
use App\Livewire\Kanban\Lonceng;
use App\Models\Cabang;
use App\Models\Kanban\Board;
use App\Models\Kanban\Kartu;
use App\Models\User;
use App\Notifications\KanbanKabar;
use App\Services\Kanban\Kabar;
use App\Services\Kanban\Tata;
use App\Support\Kanban\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kabar kanban: lonceng, email, sebutan @nama, ikuti kartu, pengingat tenggat.
 */
class KanbanKabarTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $bdg;

    private User $faris;

    private User $shanty;

    private Board $board;

    private Kartu $kartu;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Akses::PERAN_STAF as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bdg = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        $this->faris = $this->staf('admin_sales', 'Faris Fadhillah');
        $this->shanty = $this->staf('marketing', 'Shanty');

        $tata = app(Tata::class);
        $this->board = $tata->buatBoard('5. Editing', 'biru', 'workspace', $this->faris);
        $this->board->anggota()->attach($this->shanty->id, ['peran' => 'anggota']);
        $this->kartu = $tata->tambahKartu($tata->tambahKolom($this->board, 'To do', $this->faris), 'SD Harapan', $this->faris);
    }

    private function staf(string $role, string $nama): User
    {
        $u = User::factory()->create(['nama' => $nama, 'cabang_id' => $this->bdg->id]);
        $u->assignRole($role);

        return $u;
    }

    private function kabar(): Kabar
    {
        return app(Kabar::class);
    }

    public function test_pembuat_kartu_otomatis_mengikuti(): void
    {
        $this->assertTrue($this->kabar()->mengikuti($this->kartu, $this->faris));
        $this->assertFalse($this->kabar()->mengikuti($this->kartu, $this->shanty));
    }

    public function test_ikuti_dan_berhenti_ikuti_dari_detail_kartu(): void
    {
        $detail = Livewire::actingAs($this->shanty)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->assertSee('Follow')
            ->call('toggleIkut');

        $this->assertTrue($this->kabar()->mengikuti($this->kartu, $this->shanty));
        $detail->assertSee('Unfollow')->call('toggleIkut');
        $this->assertFalse($this->kabar()->mengikuti($this->kartu, $this->shanty));
    }

    public function test_komentar_mengabari_pengikut_lewat_lonceng_dan_email(): void
    {
        Notification::fake();
        $this->kabar()->ikut($this->kartu, $this->shanty);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', 'Tolong dicek ya')->call('kirimKomentar');

        Notification::assertSentTo($this->shanty, KanbanKabar::class, function (KanbanKabar $k) {
            return $k->jenis === KanbanKabar::KOMENTAR
                && in_array('mail', $k->via($this->shanty), true)
                && str_contains($k->kalimat(), 'Faris Fadhillah');
        });
        // Penulis komentar tidak mengabari dirinya sendiri.
        Notification::assertNotSentTo($this->faris, KanbanKabar::class);
    }

    public function test_sebutan_nama_mengabari_orang_yang_disebut_dan_membuatnya_mengikuti(): void
    {
        Notification::fake();

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', 'Cek ini @Shanty ya')->call('kirimKomentar');

        Notification::assertSentTo($this->shanty, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::SEBUT);
        $this->assertTrue($this->kabar()->mengikuti($this->kartu, $this->shanty));
    }

    public function test_orang_yang_disebut_tidak_dikabari_dua_kali(): void
    {
        Notification::fake();
        $this->kabar()->ikut($this->kartu, $this->shanty);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', '@Shanty tolong ya')->call('kirimKomentar');

        Notification::assertSentToTimes($this->shanty, KanbanKabar::class, 1);
    }

    public function test_pencocokan_sebutan(): void
    {
        $rina = $this->staf('marketing', 'Rina Wati');
        $this->board->anggota()->attach($rina->id, ['peran' => 'anggota']);
        $cocok = fn (string $teks) => $this->kabar()->sebutan($teks, $this->board)->pluck('nama')->all();

        $this->assertSame(['Shanty'], $cocok('halo @Shanty'));
        $this->assertSame(['Shanty'], $cocok('halo @shanty, tolong'));
        $this->assertSame(['Rina Wati'], $cocok('@RinaWati cek'));
        $this->assertSame([], $cocok('email saya a@shantyku.com'), 'alamat email bukan sebutan');
        $this->assertSame([], $cocok('@Shantya siapa itu'), 'nama harus cocok penuh');
        $this->assertSame([], $cocok('tanpa sebutan'));
    }

    public function test_sebutan_di_luar_anggota_board_diabaikan(): void
    {
        Notification::fake();
        $luar = $this->staf('editor', 'Dodi');

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', 'halo @Dodi')->call('kirimKomentar');

        Notification::assertNotSentTo($luar, KanbanKabar::class);
    }

    public function test_sebutan_di_deskripsi_dan_penugasan_mengabari(): void
    {
        Notification::fake();

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('deskripsi', 'Nanti dikerjakan @Shanty')->call('simpanDeskripsi')
            ->call('toggleAnggota', $this->shanty->id);

        Notification::assertSentTo($this->shanty, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::SEBUT);
        Notification::assertSentTo($this->shanty, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::DITUGASKAN);
    }

    public function test_arsip_kartu_mengabari_pengikut(): void
    {
        Notification::fake();
        $this->kabar()->ikut($this->kartu, $this->shanty);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])->call('arsipkan');

        Notification::assertSentTo($this->shanty, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::KARTU_DIARSIPKAN);
    }

    public function test_lonceng_menampilkan_kabar_dan_bisa_ditandai_dibaca(): void
    {
        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->call('toggleAnggota', $this->shanty->id);

        $lonceng = Livewire::actingAs($this->shanty)->test(Lonceng::class)
            ->assertSee('assigned you')
            ->assertSee('SD Harapan');
        $this->assertSame(1, $this->shanty->unreadNotifications()->count());

        $lonceng->call('bacaSemua')->assertSee('SD Harapan');
        $this->assertSame(0, $this->shanty->fresh()->unreadNotifications()->count());

        $lonceng->set('hanyaBelumDibaca', true)->assertSee('All notifications read.');
    }

    public function test_lonceng_hanya_menampilkan_kabar_sendiri(): void
    {
        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->call('toggleAnggota', $this->shanty->id);

        Livewire::actingAs($this->faris)->test(Lonceng::class)->assertSee('No notifications yet.');
    }

    public function test_email_terkirim_dengan_tautan_kartu(): void
    {
        Mail::fake();
        config(['kanban.domain' => 'app.8mataair.com']);
        $this->kabar()->ikut($this->kartu, $this->shanty);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', 'Sudah siap')->call('kirimKomentar');

        Mail::assertSent(Mailable::class, 0); // notifikasi memakai kanal mail, bukan Mailable
        $this->assertSame(1, $this->shanty->unreadNotifications()->count());
        $data = $this->shanty->unreadNotifications()->first()->data;
        $this->assertSame('https://app.8mataair.com/kanban/b/'.$this->board->id.'?kartu='.$this->kartu->id, $data['tautan']);
        $this->assertSame('Sudah siap', $data['cuplikan']);
    }

    public function test_gagal_kirim_email_tidak_menghilangkan_lonceng(): void
    {
        $this->kabar()->ikut($this->kartu, $this->shanty);
        // Mailer rusak: pengiriman email melempar, lonceng harus tetap tersimpan.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'tidak-ada.invalid', 'mail.mailers.smtp.timeout' => 1]);

        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('komentarBaru', 'Tetap masuk')->call('kirimKomentar')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->shanty->unreadNotifications()->count());
    }

    public function test_perintah_pengingat_tenggat(): void
    {
        Notification::fake();
        $this->kabar()->ikut($this->kartu, $this->shanty);
        $this->kartu->update(['tenggat_pada' => now()->addHours(3)]);
        app(Tata::class)->tambahKartu($this->kartu->kolom, 'Nanti', $this->faris)->update(['tenggat_pada' => now()->addDays(5)]);

        $this->artisan('kanban:ingatkan-tenggat')->expectsOutput('Pengingat tenggat: 1 kartu, 2 penerima.')->assertSuccessful();
        Notification::assertSentTo($this->shanty, fn (KanbanKabar $k) => $k->jenis === KanbanKabar::TENGGAT);

        // Tidak diingatkan dua kali.
        $this->artisan('kanban:ingatkan-tenggat')->expectsOutput('Pengingat tenggat: 0 kartu, 0 penerima.');

        // Tenggat digeser: boleh diingatkan lagi.
        Livewire::actingAs($this->faris)->test(DetailKartu::class, ['kartuId' => $this->kartu->id])
            ->set('tenggat', now()->addHours(5)->format('Y-m-d\TH:i'))->call('simpanTanggal');
        $this->assertNull($this->kartu->fresh()->diingatkan_at);
    }

    public function test_tenggat_yang_sudah_ditandai_selesai_tidak_diingatkan(): void
    {
        $this->kartu->update(['tenggat_pada' => now()->addHour(), 'tenggat_selesai_at' => now()]);

        $this->artisan('kanban:ingatkan-tenggat')->expectsOutput('Pengingat tenggat: 0 kartu, 0 penerima.');
    }
}
