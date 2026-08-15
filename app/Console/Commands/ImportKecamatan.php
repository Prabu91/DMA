<?php

namespace App\Console\Commands;

use App\Models\Kecamatan;
use App\Models\Kota;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Impor data kecamatan riil dari public API wilayah Indonesia (emsifa),
 * dipetakan ke kota DMA lewat config/wilayah.php.
 *
 * Idempotent: memakai updateOrCreate pada (kota_id, nama) + simpan kode BPS.
 * Aman diulang; tak menghapus kecamatan yang sudah dipakai sekolah/marketing.
 */
class ImportKecamatan extends Command
{
    protected $signature = 'kecamatan:import
        {--kota=* : Batasi impor ke nama kota tertentu (boleh berulang)}';

    protected $description = 'Impor kecamatan dari public API wilayah (emsifa) ke kota DMA';

    public function handle(): int
    {
        $base = rtrim((string) config('wilayah.emsifa.base'), '/');
        $timeout = (int) config('wilayah.emsifa.timeout', 20);
        $peta = (array) config('wilayah.regencies', []);
        $filter = array_map('strtolower', (array) $this->option('kota'));

        if ($peta === []) {
            $this->error('config/wilayah.php: peta regencies kosong.');

            return self::FAILURE;
        }

        $totalBaru = 0;
        $totalUpdate = 0;

        foreach ($peta as $namaKota => $regencies) {
            if ($filter !== [] && ! in_array(strtolower($namaKota), $filter, true)) {
                continue;
            }

            $kota = Kota::where('nama', $namaKota)->first();
            if (! $kota) {
                $this->warn("Kota '{$namaKota}' tidak ada di DB — dilewati (jalankan KotaSeeder dulu).");

                continue;
            }

            $importKota = 0;

            foreach ((array) $regencies as $regencyId) {
                $url = "{$base}/districts/{$regencyId}.json";

                try {
                    $response = Http::timeout($timeout)->acceptJson()->get($url);
                } catch (\Throwable $e) {
                    $this->error("Gagal ambil {$url}: {$e->getMessage()}");

                    continue;
                }

                if ($response->failed() || ! is_array($response->json())) {
                    $this->error("Respon tidak valid dari {$url} (HTTP {$response->status()}).");

                    continue;
                }

                foreach ($response->json() as $district) {
                    $nama = $this->rapikan($district['name'] ?? '');
                    $kode = (string) ($district['id'] ?? '');
                    if ($nama === '') {
                        continue;
                    }

                    $kec = Kecamatan::firstOrNew(['kota_id' => $kota->id, 'nama' => $nama]);
                    $baru = ! $kec->exists;
                    $kec->kode = $kode ?: $kec->kode;
                    $kec->save();

                    $baru ? $totalBaru++ : $totalUpdate++;
                    $importKota++;
                }
            }

            $this->line("  {$namaKota}: {$importKota} kecamatan diproses.");
        }

        $this->info("Selesai. {$totalBaru} baru, {$totalUpdate} diperbarui.");

        return self::SUCCESS;
    }

    /**
     * Rapikan nama kecamatan dari API (mis. "CIBEUNYING KALER" → "Cibeunying Kaler").
     */
    private function rapikan(string $nama): string
    {
        return Str::title(trim($nama));
    }
}
