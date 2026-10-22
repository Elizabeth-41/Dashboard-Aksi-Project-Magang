<?php

namespace App\Http\Controllers\Api;

use App\Actions\BuktiDukung\VerifyBuktiDukung;
use App\Enums\StatusVerifikasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyBuktiDukungRequest;
use App\Http\Resources\BuktiDukungResource;
use App\Models\BuktiDukung;

/**
 * Keputusan verifikasi bukti oleh Admin Pusat.
 */
class BuktiVerifikasiController extends Controller
{
    public function update(
        VerifyBuktiDukungRequest $request,
        BuktiDukung $buktiDukung,
        VerifyBuktiDukung $verify,
    ): BuktiDukungResource {
        $bukti = $verify->handle($buktiDukung, $request->keputusan(), $request->alasan(), $request->user());

        return BuktiDukungResource::make($bukti->load(['rencanaAksi.dinas', 'uploader', 'verifier']))
            ->additional([
                'message' => $request->keputusan() === StatusVerifikasi::Diterima
                    ? 'Bukti diterima.'
                    : 'Bukti ditolak; unit pengunggah akan diminta mengunggah ulang.',
            ]);
    }
}
