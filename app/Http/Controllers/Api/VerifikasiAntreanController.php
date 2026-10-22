<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuktiDukungResource;
use App\Models\BuktiDukung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Antrean bukti "menunggu" untuk ditinjau Admin Pusat. Terlama lebih dulu.
 */
class VerifikasiAntreanController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filter = $request->validate([
            'dinas' => ['nullable', 'string', Rule::exists('dinas', 'slug')],
            'tahun' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $cakupan = $request->user()->cakupanDinasIds();

        $antrean = BuktiDukung::query()
            ->menunggu()
            ->with(['rencanaAksi.dinas', 'uploader'])
            ->whereHas('rencanaAksi', function (Builder $rencana) use ($cakupan, $filter) {
                $rencana
                    ->when($cakupan !== null, fn (Builder $q) => $q->whereIn('dinas_id', $cakupan))
                    ->when($filter['dinas'] ?? null, fn (Builder $q, string $slug) => $q->milikUnit($slug))
                    ->when($filter['tahun'] ?? null, fn (Builder $q, int $tahun) => $q->tahun($tahun));
            })
            ->oldest()
            ->orderBy('id')
            ->paginate((int) ($filter['per_page'] ?? 25))
            ->withQueryString();

        return BuktiDukungResource::collection($antrean);
    }
}
