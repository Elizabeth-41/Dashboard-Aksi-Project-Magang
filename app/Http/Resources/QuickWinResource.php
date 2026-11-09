<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuickWinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tahun' => $this->tahun,
            'dimensi' => $this->rencanaAksi->dimensi->value,
            'judul' => $this->rencanaAksi->judul,
            'unit' => $this->rencanaAksi->dinas->nama,
            'rencana_aksi_uuid' => $this->rencanaAksi->uuid,
            'bukti' => BuktiDukungPublikResource::collection($this->rencanaAksi->buktiDukung),
        ];
    }
}
