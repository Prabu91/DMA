<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Alat uji koneksi Fonnte (WhatsApp).
 *
 *   php artisan fonnte:test                         → cek status device
 *   php artisan fonnte:test 08123456789             → kirim pesan uji
 *   php artisan fonnte:test 08123 --message="Halo"  → pesan kustom
 *   php artisan fonnte:test --token=XXXX            → override token (default: config)
 *
 * Menampilkan respons MENTAH Fonnte agar mudah didiagnosis (mis. "device
 * disconnected" = token benar tapi device belum scan QR).
 */
class FonnteTest extends Command
{
    protected $signature = 'fonnte:test
        {target? : Nomor tujuan (kosong = hanya cek status device)}
        {--message=Tes koneksi WhatsApp DMA. Abaikan pesan ini.}
        {--token= : Override device token (default dari config services.fonnte.token)}';

    protected $description = 'Uji koneksi & pengiriman WhatsApp via Fonnte';

    public function handle(): int
    {
        $token = (string) ($this->option('token') ?: config('services.fonnte.token'));

        if ($token === '') {
            $this->error('Token kosong. Beri --token=XXXX atau set FONNTE_TOKEN di .env.');

            return self::FAILURE;
        }

        $target = $this->argument('target');

        try {
            if (! $target) {
                $this->info('Mengecek status device Fonnte…');
                $resp = Http::withHeaders(['Authorization' => $token])
                    ->asForm()->timeout(20)->post('https://api.fonnte.com/device', []);
            } else {
                $this->info("Mengirim pesan uji ke {$target}…");
                $resp = Http::withHeaders(['Authorization' => $token])
                    ->asForm()->timeout(20)->post((string) config('services.fonnte.endpoint', 'https://api.fonnte.com/send'), [
                        'target' => $target,
                        'message' => (string) $this->option('message'),
                        'countryCode' => '62',
                    ]);
            }
        } catch (\Throwable $e) {
            $this->error('Gagal menghubungi Fonnte: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('HTTP status : '.$resp->status());
        $this->line('Respons     : '.json_encode($resp->json() ?? $resp->body(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
