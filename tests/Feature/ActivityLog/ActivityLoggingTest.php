<?php

namespace Tests\Feature\ActivityLog;

use App\Models\AllowedUser;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Audit trail harus mencatat siapa, aksi apa, kapan, dan data sebelum/sesudah
 * untuk: ubah status kegiatan, verifikasi/penolakan bukti, hapus bukti,
 * dan perubahan Manajemen Akses.
 */
class ActivityLoggingTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_status_change_is_logged_with_causer_and_before_after(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create(['judul' => 'Portal Smart City']);

        $this->actingAs($user)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'berjalan'])->assertOk();

        $activity = Activity::query()->inLog('rencana_aksi')->forEvent('updated')->sole();
        $this->assertSame('Mengubah status kegiatan «Portal Smart City» dari Belum Mulai menjadi Berjalan', $activity->description);
        $this->assertTrue($activity->causer->is($user));
        $this->assertTrue($activity->subject->is($rencana));
        $this->assertSame(['status' => 'belum_mulai'], $activity->attribute_changes->get('old'));
        $this->assertSame(['status' => 'berjalan'], $activity->attribute_changes->get('attributes'));
    }

    public function test_verification_and_rejection_are_logged(): void
    {
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->create(['nama_file' => 'laporan.pdf']);

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", [
            'status_verifikasi' => 'ditolak',
            'alasan_penolakan' => 'File tidak lengkap',
        ])->assertOk();
        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])->assertOk();

        $logs = Activity::query()->inLog('bukti_dukung')->forEvent('updated')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertStringStartsWith('Menolak bukti «laporan.pdf»', $logs[0]->description);
        $this->assertStringEndsWith(': File tidak lengkap', $logs[0]->description);
        $this->assertSame('menunggu', $logs[0]->attribute_changes['old']['status_verifikasi']);
        $this->assertSame('ditolak', $logs[0]->attribute_changes['attributes']['status_verifikasi']);
        $this->assertSame($admin->id, $logs[0]->attribute_changes['attributes']['verified_by']);
        $this->assertStringStartsWith('Menerima bukti «laporan.pdf»', $logs[1]->description);
        $this->assertTrue($logs[1]->causer->is($admin));
    }

    public function test_upload_and_deletion_of_bukti_are_logged(): void
    {
        Storage::fake('local');
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create(['judul' => 'e-Puskesmas']);

        $bukti = $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('foto-kegiatan.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Dokumentasi peluncuran aplikasi',
        ])->assertCreated()->json('data');
        $this->actingAs($user)->deleteJson("/api/bukti/{$bukti['uuid']}")->assertNoContent();

        $upload = Activity::query()->inLog('bukti_dukung')->forEvent('created')->sole();
        $hapus = Activity::query()->inLog('bukti_dukung')->forEvent('deleted')->sole();

        $this->assertSame('Mengunggah bukti «foto-kegiatan.pdf» untuk kegiatan «e-Puskesmas»', $upload->description);
        $this->assertSame('Menghapus bukti «foto-kegiatan.pdf» dari kegiatan «e-Puskesmas»', $hapus->description);
        $this->assertTrue($hapus->causer->is($user));
        $this->assertSame($bukti['id'], $hapus->subject_id);
        $this->assertSame('foto-kegiatan.pdf', $hapus->attribute_changes['old']['nama_file']);
    }

    public function test_access_management_changes_are_logged(): void
    {
        Notification::fake();
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();

        $id = $this->actingAs($admin)->postJson('/api/admin/allowed-users', [
            'email' => 'baru@banjarmasinkota.go.id',
            'role' => 'unit',
            'dinas_id' => $dinas->id,
        ])->assertCreated()->json('data.id');
        $this->actingAs($admin)->patchJson("/api/admin/allowed-users/{$id}", ['status' => 'dicabut'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/admin/allowed-users/{$id}")->assertNoContent();

        $logs = Activity::query()->inLog('manajemen_akses')->where('subject_id', $id)->orderBy('id')->get();

        $this->assertSame(
            ['Menambahkan akses baru@banjarmasinkota.go.id sebagai Unit / OPD', 'Mencabut akses baru@banjarmasinkota.go.id', 'Menghapus akses baru@banjarmasinkota.go.id'],
            $logs->pluck('description')->all(),
        );
        $this->assertSame(['aktif', 'dicabut'], [$logs[1]->attribute_changes['old']['status'], $logs[1]->attribute_changes['attributes']['status']]);
        $this->assertTrue($logs->every(fn (Activity $log) => $log->causer->is($admin)));
    }

    public function test_login_does_not_create_noise_in_access_log(): void
    {
        $user = $this->unitUser();
        $sebelum = Activity::count();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertSame($sebelum, Activity::count());
        $this->assertNotNull(AllowedUser::where('email', $user->email)->sole()->last_login_at);
    }
}
