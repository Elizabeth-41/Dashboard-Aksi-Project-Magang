<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Role "admin_kecamatan" dihapus dari aplikasi. Baris lama yang masih memakai
 * role itu akan membuat cast enum UserRole gagal saat dimuat, jadi dialihkan
 * ke "unit" pada unit yang sama (akun kecamatan tetap bisa mengelola kegiatan
 * kantor kecamatannya sendiri).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'allowed_users'] as $tabel) {
            DB::table($tabel)->where('role', 'admin_kecamatan')->update(['role' => 'unit']);
        }
    }

    /**
     * Tidak dapat dibalik: tidak tercatat akun mana yang semula Admin Kecamatan.
     */
    public function down(): void
    {
        //
    }
};
