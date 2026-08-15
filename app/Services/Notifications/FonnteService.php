<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim pesan WhatsApp via Fonnte (gateway unofficial).
 *
 * Desain: NON-FATAL. Bila token belum diset, nomor kosong, atau API gagal —
 * method tidak melempar exception; cukup return false + log. Alur aplikasi
 * (mis. pembuatan OTP) tetap berjalan dan OTP masih tampil di portal sekolah.
 */
class FonnteService
{
    /**
     * Kirim pesan WhatsApp ke satu nomor.
     *
     * @return bool true bila Fonnte menerima permintaan (accepted), false bila
     *              dilewati (token/nomor kosong) atau gagal.
     */
    public function send(?string $phone, string $message): bool
    {
        $token = (string) config('services.fonnte.token', '');
        $target = $this->normalize($phone);

        if ($token === '' || $target === null) {
            // Sengaja diam-diam dilewati: fitur WA belum dikonfigurasi / nomor kosong.
            return false;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $token])
                ->timeout((int) config('services.fonnte.timeout', 10))
                ->asForm()
                ->post((string) config('services.fonnte.endpoint'), [
                    'target' => $target,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

            if ($response->failed() || $response->json('status') === false) {
                Log::warning('Fonnte gagal mengirim WA', [
                    'target' => $target,
                    'http_status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Fonnte error: '.$e->getMessage(), ['target' => $target]);

            return false;
        }
    }

    /**
     * Normalisasi nomor ke format internasional tanpa "+": 08xx → 628xx.
     * Mengembalikan null bila tidak ada digit yang berarti.
     */
    public function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '620')) {
            $digits = '62'.substr($digits, 3);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        // Nomor terlalu pendek untuk valid → anggap tak ada.
        return strlen($digits) >= 9 ? $digits : null;
    }
}
