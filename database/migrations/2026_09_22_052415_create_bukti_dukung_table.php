<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bukti_dukung', function (Blueprint $table) {
            $table->id();

            // Boleh lebih dari satu bukti per kegiatan, tidak dipisah per tahun.
            $table->foreignId('rencana_aksi_id')
                ->constrained('rencana_aksi')
                ->cascadeOnDelete();

            $table->string('nama_file');
            $table->string('path_file', 500);
            // MIME type hasil deteksi server, bukan dari ekstensi.
            $table->string('tipe_file', 100)->index();
            $table->unsignedBigInteger('ukuran_file');

            // Wajib: keterangan singkat, nama file saja tidak cukup.
            $table->text('deskripsi');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Verifikasi oleh Admin Kecamatan/Pusat. Guest hanya melihat "diterima".
            $table->string('status_verifikasi', 20)->default('menunggu')->index();
            $table->text('alasan_penolakan')->nullable();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bukti_dukung');
    }
};
