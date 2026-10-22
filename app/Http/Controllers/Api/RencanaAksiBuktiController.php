<?php

namespace App\Http\Controllers\Api;

use App\Actions\BuktiDukung\UploadBuktiDukung;
use App\Enums\StatusVerifikasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBuktiDukungRequest;
use App\Http\Resources\BuktiDukungResource;
use App\Http\Resources\BuktiDukungPublikResource;
use App\Models\RencanaAksi;
use App\Support\BatasUnggah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bukti dukung milik satu kegiatan.
 */
class RencanaAksiBuktiController extends Controller
{
    /**
     * Publik. Guest hanya melihat bukti "diterima"; Unit pemilik & Admin dalam
     * cakupan melihat semua status. Filter dilakukan di level query.
     */
    public function index(Request $request, RencanaAksi $rencanaAksi): AnonymousResourceCollection
    {
        $user = $request->user('sanctum');
        $bisaLihatSemua = $user?->can('viewBuktiBelumDiterima', $rencanaAksi) ?? false;

        $bukti = $rencanaAksi->buktiDukung()
            ->when(! $bisaLihatSemua, fn ($q) => $q->diterima())
            ->with(['uploader', 'verifier'])
            ->latest()
            ->orderByDesc('id')
            ->get();

        $resource = $user
            ? BuktiDukungResource::collection($bukti)
            : BuktiDukungPublikResource::collection($bukti);

        return $resource->additional([
            'meta' => ['batas_unggah_kb' => BatasUnggah::kilobyte()],
        ]);
    }

    /**
     * Upload bukti (boleh banyak file per kegiatan, satu per request) + deskripsi wajib.
     */
    public function store(
        StoreBuktiDukungRequest $request,
        RencanaAksi $rencanaAksi,
        UploadBuktiDukung $upload,
    ): JsonResponse {
        $bukti = $upload->handle(
            $rencanaAksi,
            $request->file('file'),
            $request->validated('deskripsi'),
            $request->user(),
        );

        $pesan = $bukti->status_verifikasi === StatusVerifikasi::Diterima
            ? 'Bukti berhasil diunggah dan langsung diterima, karena kegiatan ini tidak memerlukan verifikasi admin. Bukti sudah tampil ke publik.'
            : 'Bukti berhasil diunggah dan menunggu verifikasi admin.';

        return BuktiDukungResource::make($bukti->load('uploader'))
            ->additional(['message' => $pesan])
            ->response()
            ->setStatusCode(201);
    }
}
