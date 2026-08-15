<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Kecamatan;
use App\Models\Kota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * #3: impor kecamatan dari public API wilayah (emsifa) via kecamatan:import.
 */
class ImportKecamatanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wilayah.emsifa.base', 'https://wilayah.test/api');
        config()->set('wilayah.regencies', [
            'Bandung' => ['3273'],
            'Bogor' => ['3271', '3201'],
        ]);

        $cabang = Cabang::create(['nama' => 'Bandung', 'kode_area' => 'BDG']);
        Kota::create(['nama' => 'Bandung', 'cabang_id' => $cabang->id]);
        Kota::create(['nama' => 'Bogor', 'cabang_id' => $cabang->id]);
    }

    public function test_impor_kecamatan_dari_api_dan_simpan_kode(): void
    {
        Http::fake([
            'https://wilayah.test/api/districts/3273.json' => Http::response([
                ['id' => '3273010', 'regency_id' => '3273', 'name' => 'COBLONG'],
                ['id' => '3273020', 'regency_id' => '3273', 'name' => 'SUKAJADI'],
            ], 200),
            'https://wilayah.test/api/districts/3271.json' => Http::response([
                ['id' => '3271010', 'regency_id' => '3271', 'name' => 'BOGOR TENGAH'],
            ], 200),
            'https://wilayah.test/api/districts/3201.json' => Http::response([
                ['id' => '3201010', 'regency_id' => '3201', 'name' => 'CIBINONG'],
            ], 200),
        ]);

        $this->artisan('kecamatan:import')->assertSuccessful();

        $bandung = Kota::where('nama', 'Bandung')->first();
        $this->assertDatabaseHas('kecamatan', [
            'kota_id' => $bandung->id, 'nama' => 'Coblong', 'kode' => '3273010',
        ]);
        $this->assertSame(2, Kecamatan::where('kota_id', $bandung->id)->count());
        $this->assertSame(2, Kecamatan::whereHas('kota', fn ($q) => $q->where('nama', 'Bogor'))->count());
    }

    public function test_impor_idempoten_tidak_menggandakan(): void
    {
        Http::fake([
            '*' => Http::response([
                ['id' => '3273010', 'regency_id' => '3273', 'name' => 'COBLONG'],
            ], 200),
        ]);

        $this->artisan('kecamatan:import', ['--kota' => ['Bandung']])->assertSuccessful();
        $this->artisan('kecamatan:import', ['--kota' => ['Bandung']])->assertSuccessful();

        $this->assertSame(1, Kecamatan::where('nama', 'Coblong')->count());
    }

    public function test_filter_kota_membatasi_impor(): void
    {
        Http::fake(['*' => Http::response([['id' => '3273010', 'name' => 'COBLONG']], 200)]);

        $this->artisan('kecamatan:import', ['--kota' => ['Bandung']])->assertSuccessful();

        // Bogor tak diimpor karena filter hanya Bandung.
        $this->assertSame(0, Kecamatan::whereHas('kota', fn ($q) => $q->where('nama', 'Bogor'))->count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '3271'));
    }
}
