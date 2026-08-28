<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     | Fonnte — WhatsApp gateway (unofficial) untuk OTP & notifikasi konfirmasi.
     | Set FONNTE_TOKEN di .env (device token dari dashboard Fonnte). Bila kosong,
     | pengiriman WA dilewati diam-diam (fallback: OTP tetap tampil di portal sekolah).
     */
    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
        'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'),
        'timeout' => (int) env('FONNTE_TIMEOUT', 10),
        // Saklar pesan KONFIRMASI (H-7/H-2/Hari-H). OTP tidak terpengaruh ini.
        // Nyalakan nanti dgn FONNTE_KIRIM_KONFIRMASI=true saat siap.
        'kirim_konfirmasi' => (bool) env('FONNTE_KIRIM_KONFIRMASI', false),
    ],

];
