<?php

namespace App\Notifications;

use App\Models\BuktiDukung;
use App\Models\User;
use App\Support\TeksEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke Admin Pusat saat ada unggahan baru.
 */
class BuktiBaruMenungguVerifikasi extends Notification
{
    use Queueable;

    public function __construct(public BuktiDukung $bukti) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $rencana = $this->bukti->rencanaAksi;

        return (new MailMessage)
            ->subject("Bukti baru menunggu verifikasi: {$rencana->dinas->nama}")
            ->greeting('Halo '.TeksEmail::aman($notifiable->name).',')
            ->line(TeksEmail::aman($rencana->dinas->nama).' mengunggah bukti «'.TeksEmail::aman($this->bukti->nama_file).'» untuk kegiatan «'.TeksEmail::aman($rencana->judul).'».')
            ->line('Keterangan: '.TeksEmail::aman($this->bukti->deskripsi))
            ->action('Buka antrean verifikasi', $this->url());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $rencana = $this->bukti->rencanaAksi;

        return [
            'jenis' => 'bukti_baru',
            'judul' => 'Bukti baru menunggu verifikasi',
            'pesan' => "{$rencana->dinas->nama} mengunggah «{$this->bukti->nama_file}» untuk kegiatan «{$rencana->judul}».",
            'url' => $this->url(),
            'bukti_id' => $this->bukti->id,
            'rencana_aksi_id' => $rencana->id,
            'dinas_id' => $rencana->dinas_id,
            'nama_file' => $this->bukti->nama_file,
            'uploaded_by' => $this->bukti->uploaded_by,
        ];
    }

    protected function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verifikasi';
    }
}
