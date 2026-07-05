<?php

namespace App\Support;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Pembuat QR berformat SVG (murni PHP, tanpa imagick).
 */
class Qr
{
    public static function svg(string $text, int $size = 180): string
    {
        return (string) QrCode::format('svg')->size($size)->margin(1)->errorCorrection('M')->generate($text);
    }

    /** Data URI untuk disematkan di <img> (mis. pada PDF/dompdf). */
    public static function dataUri(string $text, int $size = 180): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($text, $size));
    }
}
