<?php

namespace App\Notifications;

use App\Models\AllowedUser;
use App\Support\TeksEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Undangan untuk akun baru yang didaftarkan lewat Manajemen Akses. Mewarisi
 * ResetPassword agar tautannya dibangun oleh closure yang sama dengan reset
 * password biasa (lihat FortifyServiceProvider), hanya isinya yang diganti
 * jadi kalimat undangan berbahasa Indonesia.
 */
class UndanganAkses extends ResetPassword
{
    public function __construct(
        #[\SensitiveParameter] string $token,
        public AllowedUser $allowed,
    ) {
        parent::__construct($token);
    }

    /**
     * Undangan hanya lewat email: penerimanya belum bisa masuk untuk membaca
     * notifikasi dalam aplikasi.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $unit = $this->allowed->dinas?->nama;
        $berlaku = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Undangan akses Dashboard Rencana Aksi Smart City Banjarmasin')
            ->greeting('Assalamualaikum / Salam sejahtera,')
            ->line('Anda didaftarkan sebagai '.$this->allowed->role->label().($unit ? ' untuk unit '.TeksEmail::aman($unit) : '').' pada Dashboard Rencana Aksi Smart City Kota Banjarmasin.')
            ->line('Silakan buat kata sandi Anda melalui tombol di bawah ini untuk mulai menggunakan akun.')
            ->action('Buat kata sandi', $this->resetUrl($notifiable))
            ->line("Tautan ini berlaku {$berlaku} menit. Jika sudah kedaluwarsa, gunakan menu \"Lupa password?\" di halaman masuk, atau hubungi Admin Pusat (Diskominfotik) untuk dikirimi undangan baru.")
            ->line('Jika Anda merasa tidak seharusnya menerima undangan ini, abaikan saja email ini.')
            ->salutation('Salam, Diskominfotik Kota Banjarmasin');
    }
}
