<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Batas ukuran file bukti yang benar-benar berlaku: yang terkecil antara
 * pengaturan aplikasi (config/bukti.php) dan batas PHP server
 * (upload_max_filesize / post_max_size). Tanpa ini aplikasi bisa menjanjikan
 * 10 MB padahal PHP menolak file di atas 2 MB tanpa pesan yang jelas.
 */
class BatasUnggah
{
    public static function kilobyte(): int
    {
        $batasAplikasi = (int) config('bukti.max_size_kb');
        $batasServer = UploadedFile::getMaxFilesize();

        if (! is_finite($batasServer) || $batasServer <= 0) {
            return $batasAplikasi;
        }

        return min($batasAplikasi, (int) floor($batasServer / 1024));
    }

    /**
     * Teks untuk manusia, mis. "10 MB" atau "1,5 MB".
     */
    public static function label(): string
    {
        $megabyte = self::kilobyte() / 1024;

        return rtrim(rtrim(number_format($megabyte, 1, ',', '.'), '0'), ',').' MB';
    }
}
