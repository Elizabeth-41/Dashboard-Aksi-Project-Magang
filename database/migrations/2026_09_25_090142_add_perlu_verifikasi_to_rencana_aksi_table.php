<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebagian kegiatan tidak perlu ditinjau admin; buktinya langsung dianggap
     * diterima saat diunggah. Default tetap wajib verifikasi.
     */
    public function up(): void
    {
        Schema::table('rencana_aksi', function (Blueprint $table) {
            $table->boolean('perlu_verifikasi')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rencana_aksi', function (Blueprint $table) {
            $table->dropColumn('perlu_verifikasi');
        });
    }
};
