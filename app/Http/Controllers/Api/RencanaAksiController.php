<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRencanaAksiRequest;
use App\Http\Requests\UpdateRencanaAksiStatusRequest;
use App\Http\Resources\RencanaAksiResource;
use App\Models\RencanaAksi;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RencanaAksiController extends Controller
{
    /**
     * Daftar/pencarian kegiatan — publik. Hasil kosong disertai pesan
     * "Tidak ada Rencana Kegiatan Aksi yang ditemukan".
     */
    public function index(FilterRencanaAksiRequest $request): AnonymousResourceCollection
    {
        $filter = $request->filter();

        $rencanaAksi = RencanaAksi::query()
            ->saring($filter)
            ->with('dinas')
            ->withCount(['buktiDukung', 'buktiDiterima'])
            ->orderBy('dimensi')
            ->orderBy('judul')
            ->orderBy('id')
            ->paginate((int) ($request->validated('per_page') ?? 25))
            ->withQueryString();

        // Dipakai kotak centang "wajib verifikasi" di Kelola Kegiatan untuk
        // menampilkan keadaan seluruh kegiatan yang cocok dengan filter.
        $wajib = RencanaAksi::query()->saring($filter)->where('perlu_verifikasi', true)->count();

        return RencanaAksiResource::collection($rencanaAksi)->additional([
            'message' => $rencanaAksi->isEmpty() ? 'Tidak ada Rencana Kegiatan Aksi yang ditemukan' : null,
            'meta' => [
                'verifikasi' => [
                    'wajib' => $wajib,
                    'tanpa' => $rencanaAksi->total() - $wajib,
                ],
            ],
        ]);
    }

    public function show(RencanaAksi $rencanaAksi): RencanaAksiResource
    {
        $rencanaAksi->load(['dinas', 'creator'])->loadCount(['buktiDukung', 'buktiDiterima']);

        return RencanaAksiResource::make($rencanaAksi);
    }

    /**
     * Ubah status kegiatan secara manual (Belum Mulai / Berjalan / Selesai).
     */
    public function update(UpdateRencanaAksiStatusRequest $request, RencanaAksi $rencanaAksi): RencanaAksiResource
    {
        $rencanaAksi->update(['status' => $request->status()]);

        return RencanaAksiResource::make($rencanaAksi->load('dinas'));
    }
}
