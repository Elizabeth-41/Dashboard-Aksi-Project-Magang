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
        Schema::create('dinas', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('slug')->unique();

            // Hierarki kecamatan: diisi manual oleh Admin Pusat setelah seed,
            // karena data Excel asli tidak menyediakan relasi ini.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('dinas')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dinas');
    }
};
