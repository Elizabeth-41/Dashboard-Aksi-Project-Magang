<?php

namespace Tests\Feature\Notifications;

use App\Models\AllowedUser;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Notifications\BuktiBaruMenungguVerifikasi;
use App\Notifications\BuktiDitolak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class BuktiNotificationTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_new_upload_notifies_every_admin_pusat_but_no_unit(): void
    {
        Notification::fake();
        Storage::fake('local');
        $unit = $this->unitUser();
        $adminPusat = $this->adminPusatUser();
        $adminPusatLain = $this->adminPusatUser();
        $unitLain = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($unit->dinas)->create(['judul' => 'SIYANTI']);

        $this->actingAs($unit)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Dokumentasi peluncuran aplikasi',
        ])->assertCreated();

        Notification::assertSentTo([$adminPusat, $adminPusatLain], BuktiBaruMenungguVerifikasi::class, function (BuktiBaruMenungguVerifikasi $n, array $channels, User $notifiable) use ($rencana) {
            $data = $n->toArray($notifiable);

            return $channels === ['database', 'mail']
                && $data['jenis'] === 'bukti_baru'
                && $data['rencana_aksi_id'] === $rencana->id
                && str_contains($data['pesan'], 'SIYANTI');
        });
        Notification::assertNotSentTo([$unitLain, $unit], BuktiBaruMenungguVerifikasi::class);
    }

    public function test_upload_by_admin_does_not_notify_the_admin_themself(): void
    {
        Notification::fake();
        Storage::fake('local');
        $adminPusat = $this->adminPusatUser();
        $adminLain = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($adminPusat)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Diunggah admin atas nama unit',
        ])->assertCreated();

        Notification::assertSentTo($adminLain, BuktiBaruMenungguVerifikasi::class);
        Notification::assertNotSentTo($adminPusat, BuktiBaruMenungguVerifikasi::class);
    }

    public function test_rejection_notifies_unit_accounts_and_uploader_with_reason(): void
    {
        Notification::fake();
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $unit = $this->unitUser($dinas);
        $unitKedua = $this->unitUser($dinas);
        $unitLain = $this->unitUser();
        $pengunggahAdmin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()
            ->for(RencanaAksi::factory()->for($dinas))
            ->create(['uploaded_by' => $pengunggahAdmin->id, 'nama_file' => 'laporan.pdf']);

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", [
            'status_verifikasi' => 'ditolak',
            'alasan_penolakan' => 'Halaman 3 tidak terbaca',
        ])->assertOk();

        Notification::assertSentTo([$unit, $unitKedua, $pengunggahAdmin], BuktiDitolak::class, function (BuktiDitolak $n, array $channels, User $notifiable) use ($bukti) {
            $data = $n->toArray($notifiable);
            $mail = $n->toMail($notifiable);

            return $data['jenis'] === 'bukti_ditolak'
                && $data['bukti_id'] === $bukti->id
                && $data['alasan_penolakan'] === 'Halaman 3 tidak terbaca'
                && str_ends_with($data['url'], '/rencana-aksi/'.$bukti->rencanaAksi->uuid)
                && $mail->subject === 'Bukti ditolak: laporan.pdf';
        });
        Notification::assertNotSentTo([$unitLain, $admin], BuktiDitolak::class);
        Notification::assertCount(3);
    }

    public function test_acceptance_sends_no_rejection_notification(): void
    {
        Notification::fake();
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->create();
        $this->unitUser($bukti->rencanaAksi->dinas);

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_revoked_accounts_do_not_receive_notifications(): void
    {
        Notification::fake();
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $aktif = $this->unitUser($dinas);
        $dicabut = User::factory()->unit($dinas)->create();
        AllowedUser::factory()->untuk($dicabut)->dicabut()->create();
        $bukti = BuktiDukung::factory()->for(RencanaAksi::factory()->for($dinas))->create(['uploaded_by' => $aktif->id]);

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", [
            'status_verifikasi' => 'ditolak',
            'alasan_penolakan' => 'Bukti tidak sesuai kegiatan',
        ])->assertOk();

        Notification::assertSentTo($aktif, BuktiDitolak::class);
        Notification::assertNotSentTo($dicabut, BuktiDitolak::class);
    }

    public function test_rejection_is_stored_in_notifications_table(): void
    {
        $admin = $this->adminPusatUser();
        $unit = $this->unitUser();
        $bukti = BuktiDukung::factory()->for(RencanaAksi::factory()->for($unit->dinas))->create();

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", [
            'status_verifikasi' => 'ditolak',
            'alasan_penolakan' => 'Format file tidak sesuai',
        ])->assertOk();

        $notifikasi = $unit->unreadNotifications()->sole();
        $this->assertSame(BuktiDitolak::class, $notifikasi->type);
        $this->assertSame('bukti_ditolak', $notifikasi->data['jenis']);
        $this->assertSame($bukti->id, $notifikasi->data['bukti_id']);
    }
}
