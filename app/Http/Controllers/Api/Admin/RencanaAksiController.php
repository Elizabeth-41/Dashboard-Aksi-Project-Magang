<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\RencanaAksi\LepasBuktiMenunggu;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRencanaAksiRequest;
use App\Http\Requests\Admin\UbahVerifikasiMassalRequest;
use App\Http\Requests\Admin\UpdateRencanaAksiRequest;
use App\Http\Resources\RencanaAksiResource;
use App\Models\RencanaAksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Tambah/Kelola Kegiatan (khusus Admin Pusat) — kegiatan baru di luar 156 data awal.
 */
class RencanaAksiController extends Controller
{
    public function __construct(private LepasBuktiMenunggu $lepasBukti) {}

    public function store(StoreRencanaAksiRequest $request): JsonResponse
    {
        $rencanaAksi = RencanaAksi::create([
            ...$request->payload(),
            'created_by' => $request->user()->id,
        ]);

        return RencanaAksiResource::make($rencanaAksi->load('dinas'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRencanaAksiRequest $request, RencanaAksi $rencanaAksi): RencanaAksiResource
    {
        $rencanaAksi->update($request->payload());

        $dilepas = $rencanaAksi->wasChanged('perlu_verifikasi') && ! $rencanaAksi->perlu_verifikasi
            ? $this->lepasBukti->handle($rencanaAksi, $request->user())
            : 0;

        return RencanaAksiResource::make($rencanaAksi->load('dinas'))->additional([
            'message' => $dilepas > 0
                ? "Kegiatan diperbarui. {$dilepas} bukti yang masih menunggu langsung diterima."
                : 'Kegiatan diperbarui.',
            'meta' => ['bukti_dilepas' => $dilepas],
        ]);
    }

    /**
     * Tetapkan kewajiban verifikasi untuk semua kegiatan yang cocok dengan
     * filter — bukan hanya yang tampil di halaman aktif. Diubah satu per satu
     * (bukan UPDATE massal) agar tiap perubahan tercatat di audit trail.
     */
    public function ubahVerifikasiMassal(UbahVerifikasiMassalRequest $request): JsonResponse
    {
        $perlu = $request->perluVerifikasi();
        $admin = $request->user();

        [$diubah, $dilepas] = DB::transaction(function () use ($request, $perlu, $admin) {
            $kegiatan = RencanaAksi::query()
                ->saring($request->filter())
                ->where('perlu_verifikasi', '!=', $perlu)
                ->get();

            $dilepas = 0;

            foreach ($kegiatan as $item) {
                $item->update(['perlu_verifikasi' => $perlu]);

                if (! $perlu) {
                    $dilepas += $this->lepasBukti->handle($item, $admin);
                }
            }

            return [$kegiatan->count(), $dilepas];
        });

        $pesan = $diubah === 0
            ? 'Tidak ada perubahan: semua kegiatan pada filter ini sudah dalam pengaturan tersebut.'
            : sprintf('%d kegiatan kini %s.', $diubah, $perlu ? 'wajib diverifikasi' : 'tidak perlu verifikasi');

        if ($dilepas > 0) {
            $pesan .= " {$dilepas} bukti yang masih menunggu langsung diterima.";
        }

        return response()->json([
            'message' => $pesan,
            'meta' => ['kegiatan_diubah' => $diubah, 'bukti_dilepas' => $dilepas],
        ]);
    }
}
