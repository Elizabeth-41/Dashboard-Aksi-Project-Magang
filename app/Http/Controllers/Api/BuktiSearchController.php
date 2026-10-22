<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatusKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchBuktiRequest;
use App\Http\Resources\BuktiDukungResource;
use App\Http\Resources\BuktiDukungPublikResource;
use App\Models\BuktiDukung;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Halaman Pencarian Bukti — publik. Visibilitas status verifikasi dibatasi
 * di level query lewat scope visibleTo().
 */
class BuktiSearchController extends Controller
{
    public function __invoke(SearchBuktiRequest $request): AnonymousResourceCollection
    {
        $filter = $request->validated();
        $user = $request->user('sanctum');

        $bukti = BuktiDukung::query()
            ->visibleTo($user)
            ->with(['rencanaAksi.dinas', 'uploader'])
            ->when($filter['q'] ?? null, fn (Builder $q, string $term) => $q->where(function (Builder $q) use ($term) {
                $q->where('nama_file', 'like', "%{$term}%")
                    ->orWhere('deskripsi', 'like', "%{$term}%")
                    ->orWhereHas('rencanaAksi', fn (Builder $r) => $r->where('judul', 'like', "%{$term}%"));
            }))
            ->when($filter['status_verifikasi'] ?? null, fn (Builder $q, string $status) => $q->where('status_verifikasi', $status))
            ->when($filter['tipe_file'] ?? null, fn (Builder $q, string $kelompok) => $q->kelompokTipe($kelompok))
            ->whereHas('rencanaAksi', function (Builder $rencana) use ($filter) {
                $rencana
                    ->when($filter['dinas'] ?? null, fn (Builder $q, string $slug) => $q->milikUnit($slug))
                    ->when($filter['dimensi'] ?? null, fn (Builder $q, string $dimensi) => $q->where('dimensi', $dimensi))
                    ->when($filter['jenis_keluaran'] ?? null, fn (Builder $q, string $jenis) => $q->where('jenis_keluaran', $jenis))
                    ->when($filter['kategori'] ?? null, fn (Builder $q, string $kategori) => $q->where('kategori', $kategori))
                    ->when($filter['status'] ?? null, fn (Builder $q, string $status) => $q->denganStatus(StatusKegiatan::from($status)))
                    ->when($filter['tahun'] ?? null, fn (Builder $q, int $tahun) => $q->tahun($tahun));
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate((int) ($filter['per_page'] ?? 25))
            ->withQueryString();

        $resource = $user
            ? BuktiDukungResource::collection($bukti)
            : BuktiDukungPublikResource::collection($bukti);

        return $resource->additional([
            'message' => $bukti->isEmpty() ? 'Tidak ada bukti yang ditemukan' : null,
        ]);
    }
}
