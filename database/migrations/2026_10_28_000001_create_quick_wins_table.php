<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quick_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rencana_aksi_id')->constrained('rencana_aksi')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['rencana_aksi_id', 'tahun']);
            $table->index(['tahun', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_wins');
    }
};
