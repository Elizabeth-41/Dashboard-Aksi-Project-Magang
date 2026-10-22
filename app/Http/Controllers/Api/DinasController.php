<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKegiatan;
use App\Enums\StatusVerifikasi;
use App\Http\Controllers\Controller;
use App\Http\Resources\DinasResource;
use App\Models\Dinas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Daftar unit/OPD beserta progres capaian — publik, dengan filter tahun.
 */
class DinasController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tahun = $this->tahunFilter($request);

        $dinas = $this->withProgres(Dinas::query(), $tahun)
            ->orderBy('nama')
            ->orderBy('id')
            ->get();

        return DinasResource::collection($dinas)->additional(['meta' => ['tahun' => $this->tahunMeta($tahun)]]);
    }

    public function show(Request $request, Dinas $dinas): DinasResource
    {
        $tahun = $this->tahunFilter($request);

        $dinas = $this->withProgres(Dinas::query()->whereKey($dinas->id), $tahun)
            ->firstOrFail();

        return DinasResource::make($dinas)->additional(['meta' => ['tahun' => $this->tahunMeta($tahun)]]);
    }

    /**
     * @return array<int, int>
     */
    protected function tahunFilter(Request $request): array
    {
        if ($request->filled('tahun') && ! is_array($request->input('tahun'))) {
            $request->merge(['tahun' => [$request->input('tahun')]]);
        }

        $validated = $request->validate([
            'tahun' => ['nullable', 'array'],
            'tahun.*' => ['integer', 'min:2000', 'max:2100', 'distinct'],
        ]);

        return array_values(array_map('intval', $validated['tahun'] ?? []));
    }

    /**
     * Preserve the established scalar metadata shape for a single selected year.
     *
     * @param  array<int, int>  $tahun
     * @return int|array<int, int>|null
     */
    protected function tahunMeta(array $tahun): int|array|null
    {
        return count($tahun) === 1 ? $tahun[0] : ($tahun ?: null);
    }

    /**
     * Hitung total & per-status kegiatan (opsional dibatasi tahun) dan bukti diterima.
     *
     * @param  Builder<Dinas>  $query
     * @return Builder<Dinas>
     */
    protected function withProgres(Builder $query, array $tahun): Builder
    {
        $padaTahun = fn (Builder $rencana) => $rencana->when($tahun !== [], function (Builder $q) use ($tahun) {
            $q->where(function (Builder $q) use ($tahun) {
                foreach ($tahun as $nilai) {
                    $q->orWhereJsonContains('tahun_pelaksanaan', $nilai);
                }
            });
        });

        return $query->withCount([
            'rencanaAksi as total_rencana_aksi' => $padaTahun,
            'rencanaAksi as belum_mulai_rencana_aksi' => fn (Builder $q) => $padaTahun($q)->denganStatus(StatusKegiatan::BelumMulai),
            'rencanaAksi as berjalan_rencana_aksi' => fn (Builder $q) => $padaTahun($q)->denganStatus(StatusKegiatan::Berjalan),
            'rencanaAksi as selesai_rencana_aksi' => fn (Builder $q) => $padaTahun($q)->denganStatus(StatusKegiatan::Selesai),
            'buktiDukung as bukti_diterima_count' => fn (Builder $q) => $q
                ->where('status_verifikasi', StatusVerifikasi::Diterima)
                ->when($tahun !== [], function (Builder $q) use ($tahun) {
                    $q->where(function (Builder $q) use ($tahun) {
                        foreach ($tahun as $nilai) {
                            $q->orWhereJsonContains('rencana_aksi.tahun_pelaksanaan', $nilai);
                        }
                    });
                }),
        ]);
    }
}
