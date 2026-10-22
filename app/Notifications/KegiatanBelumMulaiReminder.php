<?php

namespace App\Notifications;

use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Support\TeksEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat berkala untuk akun unit: kegiatan yang dijadwalkan tahun ini
 * tetapi statusnya masih "Belum Mulai".
 */
class KegiatanBelumMulaiReminder extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, RencanaAksi>  $kegiatan
     */
    public function __construct(
        public Dinas $dinas,
        public Collection $kegiatan,
        public int $tahun,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Pengingat: {$this->kegiatan->count()} kegiatan {$this->dinas->nama} belum dimulai di {$this->tahun}")
            ->greeting('Halo '.TeksEmail::aman($notifiable->name).',')
            ->line("Kegiatan berikut dijadwalkan pada {$this->tahun} namun statusnya masih \"Belum Mulai\":");

        foreach ($this->kegiatan->take(20) as $rencana) {
            $mail->line('• '.TeksEmail::aman($rencana->judul));
        }

        if ($this->kegiatan->count() > 20) {
            $mail->line('… dan '.($this->kegiatan->count() - 20).' kegiatan lainnya.');
        }

        return $mail
            ->action('Perbarui status kegiatan', $this->url())
            ->line('Mohon perbarui status dan unggah bukti dukungnya.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'jenis' => 'reminder_belum_mulai',
            'judul' => "{$this->kegiatan->count()} kegiatan belum dimulai di {$this->tahun}",
            'pesan' => "Kegiatan {$this->dinas->nama} yang dijadwalkan {$this->tahun} masih berstatus \"Belum Mulai\". Mohon perbarui status dan unggah bukti.",
            'url' => $this->url(),
            'tahun' => $this->tahun,
            'dinas_id' => $this->dinas->id,
            'jumlah' => $this->kegiatan->count(),
            'kegiatan' => $this->kegiatan->take(20)->map(fn (RencanaAksi $rencana) => [
                'id' => $rencana->id,
                'judul' => $rencana->judul,
            ])->values()->all(),
        ];
    }

    protected function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/kegiatan-unit';
    }
}
