<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkunSekolahFase5Test extends TestCase
{
    use RefreshDatabase;

    private function sekolah(?int $cabangId = null, bool $verified = true): Sekolah
    {
        $sekolah = Sekolah::create([
            'id_sekolah' => Sekolah::generateIdSekolah(),
            'nama' => 'SD Uji',
            'email' => 'uji'.uniqid().'@contoh.sch.id',
            'cabang_id' => $cabangId,
        ]);

        if ($verified) {
            $sekolah->forceFill(['email_verified_at' => now()])->save();
        }

        return $sekolah;
    }

    public function test_halaman_profil_tampil(): void
    {
        $cabang = Cabang::create(['nama' => 'Jaksel', 'kode_area' => 'JKS']);
        $sekolah = $this->sekolah($cabang->id);

        $this->actingAs($sekolah, 'sekolah')
            ->get(route('sekolah.profile.edit'))
            ->assertOk()
            ->assertSee('Profil sekolah')
            ->assertSee($sekolah->id_sekolah)
            ->assertSee('Jaksel');
    }

    public function test_profil_tampilkan_cabang_belum_ditetapkan(): void
    {
        $this->actingAs($this->sekolah(null), 'sekolah')
            ->get(route('sekolah.profile.edit'))
            ->assertOk()
            ->assertSee('Belum ditetapkan');
    }

    public function test_sekolah_bisa_perbarui_profil(): void
    {
        $sekolah = $this->sekolah();

        $this->actingAs($sekolah, 'sekolah')
            ->put(route('sekolah.profile.update'), [
                'nama' => 'SD Baru Jaya',
                'pic_sekolah' => 'Bu Ani',
                'no_telp_pic' => '08123456789',
                'email_guru' => 'guru@contoh.sch.id', // diabaikan — dipaksa = email akun
                'alamat' => 'Jl. Merdeka 1',
                'maps_link' => 'https://maps.google.com/xyz',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $sekolah->refresh();
        $this->assertSame('SD Baru Jaya', $sekolah->nama);
        $this->assertSame('Bu Ani', $sekolah->pic_sekolah);
        // Email guru disamakan dengan email login akun (bukan nilai yang dikirim).
        $this->assertSame($sekolah->email, $sekolah->email_guru);
        $this->assertNotSame('guru@contoh.sch.id', $sekolah->email_guru);
    }

    public function test_update_profil_validasi_nama_wajib(): void
    {
        $this->actingAs($this->sekolah(), 'sekolah')
            ->put(route('sekolah.profile.update'), ['nama' => ''])
            ->assertSessionHasErrors('nama');
    }

    public function test_profil_boleh_diakses_walau_belum_verifikasi(): void
    {
        $this->actingAs($this->sekolah(null, verified: false), 'sekolah')
            ->get(route('sekolah.profile.edit'))
            ->assertOk();
    }

    public function test_tamu_tidak_bisa_akses_profil(): void
    {
        $this->get(route('sekolah.profile.edit'))
            ->assertRedirect(route('sekolah.masuk'));
    }
}
