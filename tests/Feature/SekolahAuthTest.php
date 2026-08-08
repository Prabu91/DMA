<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kota;
use App\Models\Sekolah;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SekolahAuthTest extends TestCase
{
    use RefreshDatabase;

    private function kota(string $nama = 'Bandung'): Kota
    {
        $cabang = Cabang::create(['nama' => $nama, 'kode_area' => strtoupper(substr($nama, 0, 3))]);

        return Kota::create(['nama' => $nama, 'cabang_id' => $cabang->id]);
    }

    private function sekolahTerverifikasi(string $email, string $password): Sekolah
    {
        $sekolah = Sekolah::create(['id_sekolah' => Sekolah::generateIdSekolah(), 'nama' => 'SD Uji', 'email' => $email]);
        $sekolah->password = $password;
        $sekolah->save();
        $sekolah->markEmailAsVerified();

        return $sekolah;
    }

    // ---------- Registrasi mandiri ----------

    public function test_registrasi_membuat_akun_dan_kirim_verifikasi(): void
    {
        Notification::fake();
        $kota = $this->kota('Bandung');

        $this->post(route('sekolah.daftar.store'), [
            'nama' => 'SDN Merdeka',
            'pic_sekolah' => 'Bu Ani',
            'kota_id' => (string) $kota->id,
            'email' => 'sdn@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('sekolah.verification.notice'));

        $sekolah = Sekolah::where('email', 'sdn@contoh.sch.id')->firstOrFail();
        $this->assertSame('SKL-000001', $sekolah->id_sekolah);        // kode akun global
        $this->assertSame($kota->cabang_id, $sekolah->cabang_id);      // cabang dari kota
        $this->assertSame('sdn@contoh.sch.id', $sekolah->email_guru);  // guru = email akun
        $this->assertSame('Bandung', $sekolah->kota);
        $this->assertFalse($sekolah->hasVerifiedEmail());
        $this->assertTrue(Auth::guard('sekolah')->check());
        Notification::assertSentTo($sekolah, VerifyEmail::class);
    }

    public function test_registrasi_kota_lainnya_cabang_null(): void
    {
        $this->post(route('sekolah.daftar.store'), [
            'nama' => 'SD Pelosok',
            'kota_id' => 'lainnya',
            'kota_lain' => 'Kota Antah',
            'email' => 'pelosok@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('sekolah.verification.notice'));

        $sekolah = Sekolah::where('email', 'pelosok@contoh.sch.id')->firstOrFail();
        $this->assertNull($sekolah->cabang_id);
        $this->assertSame('Kota Antah', $sekolah->kota);
    }

    // ---------- Login email ----------

    public function test_login_email_berhasil(): void
    {
        $this->sekolahTerverifikasi('login@contoh.sch.id', 'rahasia123');

        $this->post(route('sekolah.masuk.store'), [
            'email' => 'login@contoh.sch.id',
            'password' => 'rahasia123',
        ])->assertRedirect(route('storefront.katalog.index'));

        $this->assertTrue(Auth::guard('sekolah')->check());
    }

    public function test_login_email_salah_ditolak(): void
    {
        $this->sekolahTerverifikasi('login@contoh.sch.id', 'rahasia123');

        $this->from(route('sekolah.masuk'))
            ->post(route('sekolah.masuk.store'), ['email' => 'login@contoh.sch.id', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('sekolah')->check());
    }

    // ---------- Verifikasi email ----------

    public function test_sekolah_belum_verifikasi_diarahkan_ke_notice(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => Sekolah::generateIdSekolah(), 'nama' => 'SD Baru', 'email' => 'baru@contoh.sch.id']);

        $this->actingAs($sekolah, 'sekolah')
            ->get(route('sekolah.riwayat.index'))
            ->assertRedirect(route('sekolah.verification.notice'));
    }

    public function test_tautan_verifikasi_memverifikasi_email(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => Sekolah::generateIdSekolah(), 'nama' => 'SD Baru', 'email' => 'baru@contoh.sch.id']);

        $url = URL::temporarySignedRoute('sekolah.verification.verify', now()->addMinutes(60), [
            'id' => $sekolah->id,
            'hash' => sha1($sekolah->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(route('sekolah.riwayat.index'));
        $this->assertTrue($sekolah->fresh()->hasVerifiedEmail());
    }

    // ---------- Isolasi ----------

    public function test_sekolah_tidak_bisa_akses_area_staf(): void
    {
        $this->sekolahTerverifikasi('iso@contoh.sch.id', 'rahasia123');
        $this->post(route('sekolah.masuk.store'), ['email' => 'iso@contoh.sch.id', 'password' => 'rahasia123']);
        $this->assertTrue(Auth::guard('sekolah')->check());

        $this->get('/app/dashboard')->assertRedirect(route('login'));
    }

    public function test_staf_tidak_bisa_akses_portal_sekolah(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user) // guard web
            ->get(route('sekolah.riwayat.index'))
            ->assertRedirect(route('sekolah.masuk'));
    }
}
