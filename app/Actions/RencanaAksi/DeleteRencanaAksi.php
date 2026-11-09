<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteRencanaAksi
{
    public function handle(RencanaAksi $rencanaAksi): void
    {
        DB::transaction(function () use ($rencanaAksi): void {
            $disk = Storage::disk(config('bukti.disk'));

            foreach ($rencanaAksi->buktiDukung()->get(['id', 'path_file']) as $bukti) {
                if ($disk->exists($bukti->path_file)) {
                    $disk->delete($bukti->path_file);
                }
            }

            $rencanaAksi->delete();
        });
    }
}
