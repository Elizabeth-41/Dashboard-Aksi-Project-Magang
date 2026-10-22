<?php

namespace Tests\Feature\Notifications;

use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Notifications\KegiatanBelumMulaiReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class ReminderKegiatanBelumMulaiTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_reminder_goes_to_units_with_unstarted_activities_in_current_year(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-03-01 08:00:00');
        $dinas = Dinas::factory()->create(['nama' => 'DLH']);
        $unit = $this->unitUser($dinas);
        $belumMulai = RencanaAksi::factory()->for($dinas)->tahun([2026])->create(['judul' => 'PDU skala rumah tangga']);
        $terputus = RencanaAksi::factory()->for($dinas)->tahun([2024, 2026])->create(['judul' => 'Lomba inovasi energi']);
        RencanaAksi::factory()->for($dinas)->tahun([2026])->berjalan()->create();
        RencanaAksi::factory()->for($dinas)->tahun([2019])->create();
        $unitTanpaPr = $this->unitUser();
        RencanaAksi::factory()->for($unitTanpaPr->dinas)->tahun([2026])->selesai()->create();
        $admin = $this->adminPusatUser();

        $this->artisan('notifikasi:reminder-belum-mulai')
            ->expectsOutputToContain('DLH: 2 kegiatan → 1 akun.')
            ->assertSuccessful();

        Notification::assertSentTo($unit, KegiatanBelumMulaiReminder::class, function (KegiatanBelumMulaiReminder $n, array $channels, User $notifiable) use ($belumMulai, $terputus) {
            $data = $n->toArray($notifiable);

            return $data['tahun'] === 2026
                && $data['jumlah'] === 2
                && str_ends_with($data['url'], '/kegiatan-unit')
                && collect($data['kegiatan'])->pluck('id')->all() === [$terputus->id, $belumMulai->id]
                && str_contains($n->toMail($notifiable)->subject, '2 kegiatan DLH belum dimulai di 2026');
        });
        Notification::assertNotSentTo([$unitTanpaPr, $admin], KegiatanBelumMulaiReminder::class);
        Notification::assertCount(1);
    }

    public function test_tahun_and_dinas_options_limit_the_run(): void
    {
        Notification::fake();
        $dinasA = Dinas::factory()->create();
        $dinasB = Dinas::factory()->create();
        $unitA = $this->unitUser($dinasA);
        $unitB = $this->unitUser($dinasB);
        RencanaAksi::factory()->for($dinasA)->tahun([2023])->create();
        RencanaAksi::factory()->for($dinasB)->tahun([2023])->create();

        $this->artisan('notifikasi:reminder-belum-mulai', ['--tahun' => 2023, '--dinas' => $dinasA->id])->assertSuccessful();

        Notification::assertSentTo($unitA, KegiatanBelumMulaiReminder::class);
        Notification::assertNotSentTo($unitB, KegiatanBelumMulaiReminder::class);
    }

    public function test_unit_without_active_account_is_reported_not_notified(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-06-01');
        $dinas = Dinas::factory()->create(['nama' => 'PDPAL']);
        RencanaAksi::factory()->for($dinas)->tahun([2026])->create();

        $this->artisan('notifikasi:reminder-belum-mulai')
            ->expectsOutputToContain('PDPAL: 1 kegiatan belum mulai, tetapi belum ada akun unit aktif.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_is_scheduled_monthly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'notifikasi:reminder-belum-mulai'));

        $this->assertCount(1, $events);
        $this->assertSame('0 8 1 * *', $events->first()->expression);
    }
}
