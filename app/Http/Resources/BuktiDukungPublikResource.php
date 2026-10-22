<?php

namespace App\Http\Resources;

use App\Models\BuktiDukung;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BuktiDukung
 */
class BuktiDukungPublikResource extends JsonResource
{
    /**
     * Public representation deliberately excludes uploader/verifier identity
     * and database identifiers that are only needed by authenticated staff.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nama_file' => $this->nama_file,
            'tipe_file' => $this->tipe_file,
            'kelompok_tipe' => $this->kelompokTipeFile(),
            'ukuran_file' => $this->ukuran_file,
            'deskripsi' => $this->deskripsi,
            'url_unduh' => route('api.bukti.download', $this->resource),
            'url_lihat' => $this->bisaDilihatLangsung()
                ? route('api.bukti.lihat', ['bukti_dukung' => $this->resource, 'nama' => $this->nama_file])
                : null,
            'status_verifikasi' => $this->status_verifikasi->value,
            'status_verifikasi_label' => $this->status_verifikasi->label(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
