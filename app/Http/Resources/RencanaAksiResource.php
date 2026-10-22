<?php

namespace App\Http\Resources;

use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RencanaAksi
 */
class RencanaAksiResource extends JsonResource
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
            'dinas_id' => $this->dinas_id,
            'dinas' => DinasResource::make($this->whenLoaded('dinas')),
            'program' => $this->program,
            'judul' => $this->judul,
            'jenis_keluaran' => $this->jenis_keluaran->value,
            'jenis_keluaran_label' => $this->jenis_keluaran->label(),
            'kategori' => $this->kategori->value,
            'kategori_label' => $this->kategori->label(),
            'dimensi' => $this->dimensi->value,
            'tahun_label' => $this->tahun_label,
            'tahun_pelaksanaan' => $this->tahun_pelaksanaan,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'perlu_verifikasi' => $this->perlu_verifikasi,
            'jumlah_bukti' => $this->whenCounted('buktiDukung'),
            'jumlah_bukti_diterima' => $this->whenCounted('buktiDiterima'),
            'bukti' => BuktiDukungResource::collection($this->whenLoaded('buktiDukung')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
