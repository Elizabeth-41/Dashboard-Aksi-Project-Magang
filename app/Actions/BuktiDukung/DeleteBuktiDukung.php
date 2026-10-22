<?php

namespace App\Actions\BuktiDukung;

use App\Models\BuktiDukung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus entri bukti lalu file fisiknya. File dihapus setelah transaksi
 * commit agar tidak hilang bila penghapusan baris gagal.
 */
class DeleteBuktiDukung
{
    public function handle(BuktiDukung $buktiDukung): void
    {
        $path = $buktiDukung->path_file;

        DB::transaction(function () use ($buktiDukung) {
            $buktiDukung->delete();
        });

        Storage::disk(config('bukti.disk'))->delete($path);
    }
}
