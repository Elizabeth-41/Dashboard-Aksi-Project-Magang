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
        // Whitelist email: login hanya berhasil jika email ada di sini dan
        // statusnya aktif. Dikelola Admin Pusat lewat halaman Manajemen Akses.
        Schema::create('allowed_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('role', 30);
            $table->foreignId('dinas_id')
                ->nullable()
                ->constrained('dinas')
                ->nullOnDelete();
            $table->string('status', 20)->default('aktif')->index();
            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allowed_users');
    }
};
