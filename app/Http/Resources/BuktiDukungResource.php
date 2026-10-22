<?php

namespace App\Http\Resources;

use App\Models\BuktiDukung;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BuktiDukung
 */
class BuktiDukungResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Pengenal untuk URL; `id` tidak dipakai di tautan mana pun.
            'uuid' => $this->uuid,
            'rencana_aksi_id' => $this->rencana_aksi_id,
            'rencana_aksi' => RencanaAksiResource::make($this->whenLoaded('rencanaAksi')),
            'nama_file' => $this->nama_file,
            'tipe_file' => $this->tipe_file,
            'kelompok_tipe' => $this->kelompokTipeFile(),
            'ukuran_file' => $this->ukuran_file,
            'deskripsi' => $this->deskripsi,
            'url_unduh' => route('api.bukti.download', $this->resource),
            // null untuk jenis yang tak bisa dirender browser (dokumen Office).
            'url_lihat' => $this->bisaDilihatLangsung()
                ? route('api.bukti.lihat', ['bukti_dukung' => $this->resource, 'nama' => $this->nama_file])
                : null,
            'uploaded_by' => $this->uploaded_by,
            'uploader' => UserResource::make($this->whenLoaded('uploader')),
            'status_verifikasi' => $this->status_verifikasi->value,
            'status_verifikasi_label' => $this->status_verifikasi->label(),
            'alasan_penolakan' => $this->alasan_penolakan,
            'verified_by' => $this->verified_by,
            'verifier' => UserResource::make($this->whenLoaded('verifier')),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
