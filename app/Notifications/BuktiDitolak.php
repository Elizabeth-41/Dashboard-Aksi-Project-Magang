<?php

namespace App\Notifications;

use App\Models\BuktiDukung;
use App\Models\User;
use App\Support\TeksEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke akun unit pemilik kegiatan saat bukti ditolak, agar mengunggah ulang.
 */
class BuktiDitolak extends Notification
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
            ->subject("Bukti ditolak: {$this->bukti->nama_file}")
            ->greeting('Halo '.TeksEmail::aman($notifiable->name).',')
            ->line('Bukti «'.TeksEmail::aman($this->bukti->nama_file).'» untuk kegiatan «'.TeksEmail::aman($rencana->judul).'» ditolak oleh verifikator.')
            ->line('Alasan penolakan: '.TeksEmail::aman($this->bukti->alasan_penolakan))
            ->action('Unggah ulang bukti', $this->url())
            ->line('Silakan perbaiki sesuai alasan di atas dan unggah kembali.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $rencana = $this->bukti->rencanaAksi;

        return [
            'jenis' => 'bukti_ditolak',
            'judul' => 'Bukti ditolak, mohon unggah ulang',
            'pesan' => "Bukti «{$this->bukti->nama_file}» untuk kegiatan «{$rencana->judul}» ditolak: {$this->bukti->alasan_penolakan}",
            'url' => $this->url(),
            'bukti_id' => $this->bukti->id,
            'rencana_aksi_id' => $rencana->id,
            'nama_file' => $this->bukti->nama_file,
            'alasan_penolakan' => $this->bukti->alasan_penolakan,
            'verified_by' => $this->bukti->verified_by,
        ];
    }

    protected function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/rencana-aksi/'.$this->bukti->rencanaAksi->uuid;
    }
}
