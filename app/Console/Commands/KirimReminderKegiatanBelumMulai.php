<?php

namespace App\Console\Commands;

use App\Enums\StatusKegiatan;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Notifications\KegiatanBelumMulaiReminder;
use App\Support\PenerimaNotifikasi;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Notification;

/**
 * Pengingat berkala ke akun unit untuk kegiatan tahun berjalan yang masih
 * "Belum Mulai". Dijadwalkan di routes/console.php.
 */
class KirimReminderKegiatanBelumMulai extends Command
{
    protected $signature = 'notifikasi:reminder-belum-mulai
                            {--tahun= : Tahun yang diperiksa (default: tahun berjalan)}
                            {--dinas= : Batasi ke satu dinas (id)}';

    protected $description = 'Kirim pengingat ke unit untuk kegiatan tahun berjalan yang masih berstatus Belum Mulai';

    public function handle(PenerimaNotifikasi $penerima): int
    {
        $tahun = (int) ($this->option('tahun') ?: now()->year);

        $dinasList = Dinas::query()
            ->when($this->option('dinas'), fn (Builder $q, string $id) => $q->whereKey($id))
            ->whereHas('rencanaAksi', fn (Builder $q) => $this->belumMulaiPadaTahun($q, $tahun))
            ->with(['rencanaAksi' => fn ($q) => $this->belumMulaiPadaTahun($q, $tahun)->orderBy('judul')])
            ->orderBy('nama')
            ->get();

        $terkirim = 0;

        foreach ($dinasList as $dinas) {
            $users = $penerima->unitUntukDinas($dinas);

            if ($users->isEmpty()) {
                $this->warn("{$dinas->nama}: {$dinas->rencanaAksi->count()} kegiatan belum mulai, tetapi belum ada akun unit aktif.");

                continue;
            }

            Notification::send($users, new KegiatanBelumMulaiReminder($dinas, $dinas->rencanaAksi, $tahun));

            $this->line("{$dinas->nama}: {$dinas->rencanaAksi->count()} kegiatan → {$users->count()} akun.");
            $terkirim += $users->count();
        }

        $this->info("Selesai. {$dinasList->count()} unit diperiksa, {$terkirim} notifikasi dikirim untuk tahun {$tahun}.");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<RencanaAksi>|HasMany<RencanaAksi, Dinas>  $query
     */
    protected function belumMulaiPadaTahun($query, int $tahun)
    {
        return $query->tahun($tahun)->denganStatus(StatusKegiatan::BelumMulai);
    }
}
