<?php

namespace Tests\Feature;

use App\Livewire\Sekolah\SekolahIndex;
use App\Models\Cabang;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SekolahAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['super_admin', 'operasional', 'marketing'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function sekolah(?string $password = null): Sekolah
    {
        $cabang = Cabang::firstOrCreate(['nama' => 'DMA Jakarta'], ['kode_area' => 'JKT']);
        $sekolah = Sekolah::create([
            'id_sekolah' => 'SKL-JKT-0001',
            'nama' => 'SDN 1 Merdeka',
            'cabang_id' => $cabang->id,
        ]);
        if ($password) {
            $sekolah->password = $password;
            $sekolah->save();
        }

        return $sekolah;
    }

    public function test_sekolah_berpassword_bisa_login(): void
    {
        $this->sekolah('rahasia123');

        $this->post(route('sekolah.login.store'), [
            'id_sekolah' => 'SKL-JKT-0001',
            'password' => 'rahasia123',
        ])->assertRedirect(route('sekolah.beranda'));

        $this->assertTrue(Auth::guard('sekolah')->check());
    }

    public function test_password_salah_ditolak(): void
    {
        $this->sekolah('rahasia123');

        $this->from(route('sekolah.login'))
            ->post(route('sekolah.login.store'), [
                'id_sekolah' => 'SKL-JKT-0001',
                'password' => 'salah',
            ])
            ->assertRedirect(route('sekolah.login'))
            ->assertSessionHasErrors('id_sekolah');

        $this->assertFalse(Auth::guard('sekolah')->check());
    }

    public function test_sekolah_tanpa_password_tidak_bisa_login(): void
    {
        $this->sekolah(); // password null

        $this->post(route('sekolah.login.store'), [
            'id_sekolah' => 'SKL-JKT-0001',
            'password' => 'apapun',
        ])->assertSessionHasErrors('id_sekolah');

        $this->assertFalse(Auth::guard('sekolah')->check());
    }

    public function test_sekolah_tidak_bisa_akses_area_staf(): void
    {
        $this->sekolah('rahasia123');

        // Login lewat alur nyata (guard sekolah; default guard tetap web).
        $this->post(route('sekolah.login.store'), [
            'id_sekolah' => 'SKL-JKT-0001',
            'password' => 'rahasia123',
        ]);
        $this->assertTrue(Auth::guard('sekolah')->check());

        // Akses area staf → guard web kosong → diarahkan ke login staf.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_staf_tidak_bisa_akses_area_sekolah(): void
    {
        $staf = User::factory()->create();
        $staf->assignRole('marketing');

        $this->actingAs($staf) // guard web
            ->get(route('sekolah.beranda'))
            ->assertRedirect(route('sekolah.login'));
    }

    public function test_staf_reset_password_lalu_sekolah_bisa_login(): void
    {
        $cabang = Cabang::firstOrCreate(['nama' => 'DMA Jakarta'], ['kode_area' => 'JKT']);
        $sekolah = Sekolah::create(['id_sekolah' => 'SKL-JKT-0001', 'nama' => 'SD A', 'cabang_id' => $cabang->id]);

        $staf = User::factory()->create(['cabang_id' => $cabang->id]);
        $staf->assignRole('marketing');
        Livewire::actingAs($staf);

        Livewire::test(SekolahIndex::class)
            ->call('openResetPassword', $sekolah->id)
            ->set('newPassword', 'barusaja123')
            ->set('newPassword_confirmation', 'barusaja123')
            ->call('savePassword')
            ->assertHasNoErrors();

        $this->assertNotNull($sekolah->fresh()->password);

        // Sekolah kini bisa login.
        $this->post(route('sekolah.login.store'), [
            'id_sekolah' => 'SKL-JKT-0001',
            'password' => 'barusaja123',
        ])->assertRedirect(route('sekolah.beranda'));
    }

    public function test_sekolah_ganti_password_sendiri(): void
    {
        $sekolah = $this->sekolah('lama12345');

        $this->actingAs($sekolah, 'sekolah')
            ->put(route('sekolah.password.update'), [
                'current_password' => 'lama12345',
                'password' => 'baru12345',
                'password_confirmation' => 'baru12345',
            ])
            ->assertSessionHasNoErrors();

        // Password lama tak lagi berlaku, yang baru berlaku.
        $this->assertTrue(Auth::guard('sekolah')->validate([
            'id_sekolah' => 'SKL-JKT-0001', 'password' => 'baru12345',
        ]));
    }
}
