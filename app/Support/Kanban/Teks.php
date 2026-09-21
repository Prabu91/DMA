<?php

namespace App\Support\Kanban;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Format sederhana untuk deskripsi & komentar kartu (Markdown seperti Trello).
 *
 * HTML mentah dari pengguna selalu di-escape, jadi teks apa pun aman
 * ditampilkan. Sebutan @nama ikut ditandai supaya gampang terlihat.
 */
final class Teks
{
    /** Penanda format yang ditampilkan di bawah kotak isian. */
    public const BANTUAN = '**tebal**, *miring*, `kode`, - daftar, [tautan](https://…)';

    public static function html(?string $teks): HtmlString
    {
        if ($teks === null || trim($teks) === '') {
            return new HtmlString('');
        }

        $html = Str::markdown($teks, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            // Enter tunggal tetap jadi baris baru — orang menulis alamat & redaksi begitu.
            'renderer' => ['soft_break' => '<br>'],
        ]);

        // Tautan selalu dibuka di tab baru dan tidak membocorkan halaman asal.
        $html = str_replace('<a href=', '<a target="_blank" rel="noopener noreferrer" href=', $html);

        return new HtmlString(self::sorotSebutan($html));
    }

    /**
     * Sorot @nama, tapi hanya pada teks di antara tag — tidak pada isi
     * atribut seperti href, supaya HTML-nya tidak rusak.
     */
    private static function sorotSebutan(string $html): string
    {
        return preg_replace_callback('/>([^<]+)</u', function (array $cocok) {
            $teks = preg_replace(
                '/(?<![\w@])@([\p{L}\p{N}]{2,40})/u',
                '<span class="rounded bg-brand/20 px-1 font-medium text-ink">@$1</span>',
                $cocok[1],
            );

            return '>'.$teks.'<';
        }, $html) ?? $html;
    }
}
