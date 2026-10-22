<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kegiatan dan bukti dikenali lewat UUID di URL (halaman detail, tautan
 * lihat/unduh, tautan notifikasi), bukan ID auto increment, supaya jumlah data
 * dan urutannya tidak terbaca dan ID tidak bisa ditebak berurutan.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tabel = ['rencana_aksi', 'bukti_dukung'];

    public function up(): void
    {
        foreach ($this->tabel as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            DB::table($tabel)->whereNull('uuid')->orderBy('id')->eachById(function (object $baris) use ($tabel) {
                DB::table($tabel)->where('id', $baris->id)->update(['uuid' => (string) Str::uuid7()]);
            });

            Schema::table($tabel, function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->unique()->change();
            });
        }

        $this->ubahTautanNotifikasi(function (string $url): string {
            // Pengingat kegiatan belum mulai dulu menaut ke /dinas/{id}, halaman
            // yang tidak pernah ada di SPA; tujuan yang benar adalah Kegiatan Unit.
            $url = preg_replace('#/dinas/\d+$#', '/kegiatan-unit', $url);

            return preg_replace_callback(
                '#/rencana-aksi/(\d+)$#',
                fn (array $cocok) => '/rencana-aksi/'.(DB::table('rencana_aksi')->where('id', $cocok[1])->value('uuid') ?? $cocok[1]),
                $url,
            );
        });
    }

    public function down(): void
    {
        $this->ubahTautanNotifikasi(fn (string $url): string => preg_replace_callback(
            '#/rencana-aksi/([0-9a-f-]{36})$#',
            fn (array $cocok) => '/rencana-aksi/'.(DB::table('rencana_aksi')->where('uuid', $cocok[1])->value('id') ?? $cocok[1]),
            $url,
        ));

        foreach ($this->tabel as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }

    /**
     * Tautan di notifikasi yang sudah tersimpan ikut diperbarui agar tetap bisa dibuka.
     *
     * @param  Closure(string): string  $ubah
     */
    protected function ubahTautanNotifikasi(Closure $ubah): void
    {
        DB::table('notifications')->orderBy('id')->each(function (object $notifikasi) use ($ubah) {
            $data = json_decode($notifikasi->data, true);

            if (! is_array($data) || ! is_string($data['url'] ?? null)) {
                return;
            }

            $baru = $ubah($data['url']);

            if ($baru !== $data['url']) {
                $data['url'] = $baru;
                DB::table('notifications')->where('id', $notifikasi->id)->update(['data' => json_encode($data)]);
            }
        });
    }
};
