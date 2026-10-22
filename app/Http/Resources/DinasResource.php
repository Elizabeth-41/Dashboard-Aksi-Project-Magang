<?php

namespace App\Http\Resources;

use App\Models\Dinas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dinas
 */
class DinasResource extends JsonResource
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
            'nama' => $this->nama,
            'slug' => $this->slug,
            // Statistik progres hadir hanya jika controller memuat withCount (lihat DinasController).
            'progres' => $this->when(
                $this->hasAttribute('total_rencana_aksi'),
                fn () => $this->progresArray(),
            ),
        ];
    }

    /**
     * @return array{total: int, belum_mulai: int, berjalan: int, selesai: int, bukti_diterima: int, persentase: int}
     */
    protected function progresArray(): array
    {
        $total = (int) $this->total_rencana_aksi;
        $selesai = (int) $this->selesai_rencana_aksi;

        return [
            'total' => $total,
            'belum_mulai' => (int) $this->belum_mulai_rencana_aksi,
            'berjalan' => (int) $this->berjalan_rencana_aksi,
            'selesai' => $selesai,
            'bukti_diterima' => (int) ($this->bukti_diterima_count ?? 0),
            'persentase' => $total === 0 ? 0 : (int) round($selesai / $total * 100),
        ];
    }
}
