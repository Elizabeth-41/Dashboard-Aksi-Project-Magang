<?php

namespace Database\Seeders\Concerns;

use Generator;
use RuntimeException;

trait ReadsCsv
{
    /**
     * Baca CSV berdasarkan path relatif dari root proyek sebagai baris
     * asosiatif (header => nilai).
     *
     * @return Generator<int, array<string, string>>
     */
    protected function readCsv(string $filename): Generator
    {
        $path = base_path($filename);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("File seed tidak ditemukan: {$path}");
        }

        try {
            $header = fgetcsv($handle, escape: '');

            if ($header === false) {
                throw new RuntimeException("File seed kosong: {$path}");
            }

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                yield array_combine($header, $row);
            }
        } finally {
            fclose($handle);
        }
    }
}
