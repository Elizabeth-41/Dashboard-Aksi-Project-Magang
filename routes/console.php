<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat bulanan untuk unit: kegiatan tahun berjalan yang masih "Belum Mulai".
// Butuh `php artisan schedule:run` tiap menit di cron server.
Schedule::command('notifikasi:reminder-belum-mulai')
    ->monthlyOn(1, '08:00')
    ->timezone('Asia/Makassar')
    ->withoutOverlapping();
