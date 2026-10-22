<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki unit (induk kecamatan) hanya dipakai untuk menentukan wilayah Admin
 * Kecamatan. Role itu sudah dihapus, jadi kolom induknya ikut dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dinas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }

    /**
     * Mengembalikan kolomnya saja; isi induk yang lama tidak bisa dipulihkan.
     */
    public function down(): void
    {
        Schema::table('dinas', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('slug')
                ->constrained('dinas')
                ->nullOnDelete();
        });
    }
};
