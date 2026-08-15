<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kota;
use App\Models\Sekolah;
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

    private function sekolahAkun(string $email, string $password): Sekolah
    {
        $sekolah = Sekolah::create(['id_sekolah' => Sekolah::generateIdSekolah(), 'nama' => 'SD Uji', 'email' => $email]);
        $sekolah->password = $password;
        $sekolah->save();

        return $sekolah;
    }

    // ---------- Registrasi mandiri ----------

    public function test_registrasi_membuat_akun_tanpa_wajib_verifikasi(): void
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
        ])->assertRedirect(route('storefront.katalog.index'))
            ->assertSessionHas('id_sekolah', 'SKL-000001');

        $sekolah = Sekolah::where('email', 'sdn@contoh.sch.id')->firstOrFail();
        $this->assertSame('SKL-000001', $sekolah->id_sekolah);        // kode akun global
        $this->assertSame($kota->cabang_id, $sekolah->cabang_id);      // cabang dari kota
        $this->assertSame('sdn@contoh.sch.id', $sekolah->email_guru);  // guru = email akun
        $this->assertSame('Bandung', $sekolah->kota);
        $this->assertTrue(Auth::guard('sekolah')->check());            // langsung login
        Notification::assertNothingSent();                             // verifikasi tak dipaksa (nanti via WA)
    }

    public function test_registrasi_menyimpan_kecamatan(): void
    {
        $kota = $this->kota('Bandung');
        $kec = \App\Models\Kecamatan::create(['nama' => 'Coblong', 'kota_id' => $kota->id]);

        $this->post(route('sekolah.daftar.store'), [
            'nama' => 'SDN Kecamatan',
            'kota_id' => (string) $kota->id,
            'kecamatan_id' => $kec->id,
            'email' => 'kec@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('storefront.katalog.index'));

        $sekolah = Sekolah::where('email', 'kec@contoh.sch.id')->firstOrFail();
        $this->assertSame($kec->id, $sekolah->kecamatan_id);
    }

    public function test_registrasi_kecamatan_beda_kota_diabaikan(): void
    {
        $kotaA = $this->kota('Bandung');
        $kotaB = $this->kota('Bogor');
        $kecB = \App\Models\Kecamatan::create(['nama' => 'Bogor Tengah', 'kota_id' => $kotaB->id]);

        // Pilih kota A tapi kecamatan milik kota B → kecamatan diabaikan (null).
        $this->post(route('sekolah.daftar.store'), [
            'nama' => 'SDN Salah Kecamatan',
            'kota_id' => (string) $kotaA->id,
            'kecamatan_id' => $kecB->id,
            'email' => 'salahkec@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('storefront.katalog.index'));

        $sekolah = Sekolah::where('email', 'salahkec@contoh.sch.id')->firstOrFail();
        $this->assertNull($sekolah->kecamatan_id);
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
        ])->assertRedirect(route('storefront.katalog.index'));

        $sekolah = Sekolah::where('email', 'pelosok@contoh.sch.id')->firstOrFail();
        $this->assertNull($sekolah->cabang_id);
        $this->assertSame('Kota Antah', $sekolah->kota);
    }

    public function test_registrasi_tolak_sekolah_duplikat(): void
    {
        $kota = $this->kota('Bandung');
        Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SDN Kembar',
            'pic_sekolah' => 'Pak Budi',
            'no_telp_pic' => '08123',
            'alamat' => 'Jl. Sama No. 1',
            'cabang_id' => $kota->cabang_id,
        ]);

        $this->from(route('sekolah.daftar'))->post(route('sekolah.daftar.store'), [
            'nama' => 'SDN Kembar',
            'pic_sekolah' => 'Pak Budi',
            'no_telp_pic' => '08123',
            'alamat' => 'Jl. Sama No. 1',
            'kota_id' => (string) $kota->id,
            'email' => 'kembar@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('nama');

        $this->assertNull(Sekolah::where('email', 'kembar@contoh.sch.id')->first());
    }

    public function test_duplikat_sekolah_field_kosong_tetap_tertangkap(): void
    {
        // Sekolah pertama dengan PIC/telp/alamat kosong (tersimpan '' atau null).
        $kota = $this->kota('Bandung');
        Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SDN Tanpa Detail',
            'alamat' => '', 'pic_sekolah' => '', 'no_telp_pic' => '',
            'cabang_id' => $kota->cabang_id,
        ]);

        // Registrasi kedua dengan nama sama & semua opsional kosong → harus ditolak.
        $this->from(route('sekolah.daftar'))->post(route('sekolah.daftar.store'), [
            'nama' => 'SDN Tanpa Detail',
            'kota_id' => (string) $kota->id,
            'email' => 'kosong@contoh.sch.id',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('nama');

        $this->assertNull(Sekolah::where('email', 'kosong@contoh.sch.id')->first());
    }

    // ---------- Login pakai ID sekolah ----------

    public function test_login_id_berhasil(): void
    {
        $sekolah = $this->sekolahAkun('login@contoh.sch.id', 'rahasia123');

        $this->post(route('sekolah.masuk.store'), [
            'id_sekolah' => $sekolah->id_sekolah,
            'password' => 'rahasia123',
        ])->assertRedirect(route('storefront.katalog.index'));

        $this->assertTrue(Auth::guard('sekolah')->check());
    }

    public function test_login_id_case_insensitive(): void
    {
        $sekolah = $this->sekolahAkun('lower@contoh.sch.id', 'rahasia123');

        $this->post(route('sekolah.masuk.store'), [
            'id_sekolah' => strtolower($sekolah->id_sekolah), // skl-000001
            'password' => 'rahasia123',
        ])->assertRedirect(route('storefront.katalog.index'));

        $this->assertTrue(Auth::guard('sekolah')->check());
    }

    public function test_login_id_salah_ditolak(): void
    {
        $sekolah = $this->sekolahAkun('login@contoh.sch.id', 'rahasia123');

        $this->from(route('sekolah.masuk'))
            ->post(route('sekolah.masuk.store'), ['id_sekolah' => $sekolah->id_sekolah, 'password' => 'salah'])
            ->assertSessionHasErrors('id_sekolah');

        $this->assertFalse(Auth::guard('sekolah')->check());
    }

    // ---------- Verifikasi email (opsional, tidak lagi diwajibkan) ----------

    public function test_sekolah_belum_verifikasi_tetap_bisa_akses_riwayat(): void
    {
        $sekolah = Sekolah::create(['id_sekolah' => Sekolah::generateIdSekolah(), 'nama' => 'SD Baru', 'email' => 'baru@contoh.sch.id']);

        $this->actingAs($sekolah, 'sekolah')
            ->get(route('sekolah.riwayat.index'))
            ->assertOk();
    }

    public function test_tautan_verifikasi_masih_bisa_memverifikasi_email(): void
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
        $sekolah = $this->sekolahAkun('iso@contoh.sch.id', 'rahasia123');
        $this->post(route('sekolah.masuk.store'), ['id_sekolah' => $sekolah->id_sekolah, 'password' => 'rahasia123']);
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
