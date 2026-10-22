<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RencanaAksiResource;
use App\Models\Dinas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Kegiatan milik satu unit — publik, dengan filter tahun.
 */
class DinasRencanaAksiController extends Controller
{
    public function index(Request $request, Dinas $dinas): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $rencanaAksi = $dinas->rencanaAksi()
            ->when(isset($validated['tahun']), fn (Builder $q) => $q->tahun((int) $validated['tahun']))
            ->withCount(['buktiDukung', 'buktiDiterima'])
            ->orderBy('dimensi')
            ->orderBy('judul')
            ->orderBy('id')
            ->get();

        return RencanaAksiResource::collection($rencanaAksi);
    }
}
