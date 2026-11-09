<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusVerifikasi;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuickWinResource;
use App\Models\QuickWin;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuickWinController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $tahun = now()->year;
        $quickWins = QuickWin::query()
            ->where('tahun', $tahun)
            ->with([
                'rencanaAksi.dinas',
                'rencanaAksi.buktiDukung' => fn ($query) => $query
                    ->where('status_verifikasi', StatusVerifikasi::Diterima)
                    ->whereIn('tipe_file', config('bukti.mime_groups.gambar', []))
                    ->latest('created_at'),
            ])
            ->whereHas('rencanaAksi.buktiDukung', fn ($query) => $query
                ->where('status_verifikasi', StatusVerifikasi::Diterima)
                ->whereIn('tipe_file', config('bukti.mime_groups.gambar', [])))
            ->orderBy('id')
            ->get();

        return QuickWinResource::collection($quickWins)->additional(['meta' => ['tahun' => $tahun]]);
    }
}
