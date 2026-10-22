<?php

namespace App\Policies;

use App\Models\RencanaAksi;
use App\Models\User;

/**
 * Daftar/detail kegiatan bersifat publik (tanpa policy). Policy ini hanya
 * mengatur aksi tulis.
 */
class RencanaAksiPolicy
{
    /**
     * Tambah kegiatan baru di luar data awal: khusus Admin Pusat.
     */
    public function create(User $user): bool
    {
        return $user->isAdminPusat();
    }

    /**
     * Ubah data kegiatan (judul, program, dsb.): khusus Admin Pusat.
     */
    public function update(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->isAdminPusat();
    }

    /**
     * Ubah status kegiatan secara manual: Unit pemilik atau Admin Pusat.
     */
    public function updateStatus(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->mencakupDinas($rencanaAksi->dinas_id);
    }

    /**
     * Unggah bukti ke kegiatan ini — cakupan sama dengan ubah status.
     */
    public function uploadBukti(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->mencakupDinas($rencanaAksi->dinas_id);
    }

    /**
     * Lihat bukti yang belum/tidak diterima (Menunggu/Ditolak) pada kegiatan ini.
     */
    public function viewBuktiBelumDiterima(User $user, RencanaAksi $rencanaAksi): bool
    {
        return $user->mencakupDinas($rencanaAksi->dinas_id);
    }
}
