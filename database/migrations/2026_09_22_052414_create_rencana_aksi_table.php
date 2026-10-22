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
        Schema::create('rencana_aksi', function (Blueprint $table) {
            $table->id();

            // Leading sector. Kegiatan tidak boleh ikut terhapus saat unit dihapus.
            $table->foreignId('dinas_id')
                ->constrained('dinas')
                ->restrictOnDelete();

            // Program induk nullable: kegiatan lama (2019-2021) sering tidak punya program.
            $table->text('program')->nullable();
            $table->string('judul', 500);

            // Nilai-nilai berikut dikunci lewat enum PHP di App\Enums, bukan enum DB,
            // supaya penambahan nilai tidak butuh ALTER TABLE.
            $table->string('jenis_keluaran', 30)->index();
            $table->string('kategori', 20)->index();
            $table->string('dimensi', 50)->index();

            // Label tampilan asli ("2022 - 2026", "2023 & 2025") + daftar tahun
            // terurai untuk filter, karena beberapa periode tidak kontinu.
            $table->string('tahun_label', 50);
            $table->json('tahun_pelaksanaan');

            // Selalu diisi manual (Belum Mulai/Berjalan/Selesai), tidak pernah dihitung.
            $table->string('status', 20)->default('belum_mulai')->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rencana_aksi');
    }
};
