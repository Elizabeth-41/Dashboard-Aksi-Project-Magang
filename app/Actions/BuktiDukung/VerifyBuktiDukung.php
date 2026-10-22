<?php

namespace App\Actions\BuktiDukung;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\User;
use App\Notifications\BuktiDitolak;
use App\Support\PenerimaNotifikasi;
use Illuminate\Support\Facades\Notification;

/**
 * Tandai bukti "diterima" atau "ditolak" (dengan alasan). Keputusan boleh
 * diubah lagi oleh admin dalam cakupan, mis. untuk mengoreksi kekeliruan.
 * Perubahan tercatat otomatis di activity_log lewat LogsActivity; penolakan
 * memberi tahu unit pemilik agar mengunggah ulang.
 */
class VerifyBuktiDukung
{
    public function __construct(private PenerimaNotifikasi $penerima) {}

    public function handle(BuktiDukung $buktiDukung, StatusVerifikasi $keputusan, ?string $alasan, User $verifier): BuktiDukung
    {
        $buktiDukung->update([
            'status_verifikasi' => $keputusan,
            'alasan_penolakan' => $keputusan === StatusVerifikasi::Ditolak ? $alasan : null,
            'verified_by' => $verifier->id,
            'verified_at' => now(),
        ]);

        if ($keputusan === StatusVerifikasi::Ditolak) {
            $this->beriTahuPenolakan($buktiDukung, $verifier);
        }

        return $buktiDukung;
    }

    /**
     * Penerima: akun unit pemilik kegiatan + pengunggah (bila bukan verifikator sendiri).
     */
    protected function beriTahuPenolakan(BuktiDukung $buktiDukung, User $verifier): void
    {
        $buktiDukung->loadMissing(['rencanaAksi.dinas', 'uploader']);

        $penerima = $this->penerima->unitUntukDinas($buktiDukung->rencanaAksi->dinas);

        if ($buktiDukung->uploader !== null) {
            $penerima->push($buktiDukung->uploader);
        }

        $penerima = $penerima->unique('id')->reject(fn (User $user) => $user->is($verifier));

        Notification::send($penerima, new BuktiDitolak($buktiDukung));
    }
}
