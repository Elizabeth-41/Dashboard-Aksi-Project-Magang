<?php

namespace App\Policies;

use App\Models\BuktiDukung;
use App\Models\User;

class BuktiDukungPolicy
{
    /**
     * Lihat/unduh bukti. Bukti "diterima" publik (termasuk Guest, $user null);
     * selain itu hanya Unit pemilik dan Admin dalam cakupan.
     */
    public function view(?User $user, BuktiDukung $buktiDukung): bool
    {
        if ($buktiDukung->isDiterima()) {
            return true;
        }

        return $user?->mencakupDinas($buktiDukung->rencanaAksi->dinas_id) ?? false;
    }

    /**
     * Hapus bukti: Unit pemilik atau Admin Pusat.
     */
    public function delete(User $user, BuktiDukung $buktiDukung): bool
    {
        return $user->mencakupDinas($buktiDukung->rencanaAksi->dinas_id);
    }

    /**
     * Verifikasi (terima/tolak): hanya Admin Pusat.
     */
    public function verify(User $user, BuktiDukung $buktiDukung): bool
    {
        return $user->isAdminPusat();
    }
}
