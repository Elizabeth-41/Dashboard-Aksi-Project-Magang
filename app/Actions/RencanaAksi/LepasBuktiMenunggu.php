<?php

namespace App\Actions\RencanaAksi;

use App\Actions\BuktiDukung\VerifyBuktiDukung;
use App\Enums\StatusVerifikasi;
use App\Models\RencanaAksi;
use App\Models\User;

/**
 * Setelah verifikasi sebuah kegiatan dimatikan, bukti yang terlanjur
 * "Menunggu" tidak boleh menggantung di antrean yang tak akan ditinjau siapa
 * pun: semuanya diterima atas nama admin yang mematikan verifikasi. Bukti yang
 * sudah ditolak tidak disentuh.
 */
class LepasBuktiMenunggu
{
    public function __construct(private VerifyBuktiDukung $verify) {}

    /**
     * @return int Jumlah bukti yang dilepas.
     */
    public function handle(RencanaAksi $rencanaAksi, User $admin): int
    {
        $menunggu = $rencanaAksi->buktiDukung()->menunggu()->get();

        foreach ($menunggu as $bukti) {
            $this->verify->handle($bukti, StatusVerifikasi::Diterima, null, $admin);
        }

        return $menunggu->count();
    }
}
