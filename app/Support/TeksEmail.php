<?php

namespace App\Support;

/**
 * Email notifikasi dirender dari Markdown. HTML sudah di-escape Blade, tetapi
 * sintaks Markdown tidak: teks isian pengguna seperti "[klik](https://…)"
 * akan menjadi tautan yang bisa diklik di email resmi (phishing). Semua nilai
 * dinamis yang disisipkan ke baris email harus melewati helper ini.
 */
class TeksEmail
{
    /**
     * Tanda baca yang bermakna di CommonMark (+ tabel); di-escape dengan
     * garis miring terbalik sehingga tampil apa adanya.
     */
    private const TANDA_MARKDOWN = '\\`*_{}[]()#+-.!|<>~';

    public static function aman(?string $teks): string
    {
        if ($teks === null || $teks === '') {
            return '';
        }

        // Baris baru bisa membuka blok Markdown baru (judul, daftar, tabel).
        $satuBaris = preg_replace('/\s+/u', ' ', $teks);

        return addcslashes($satuBaris, self::TANDA_MARKDOWN);
    }
}
