<?php

namespace Tests\Feature\Api;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Notifications\BuktiBaruMenungguVerifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Admin Pusat dapat menetapkan bahwa bukti sebuah kegiatan tidak perlu
 * diverifikasi; unggahannya langsung berstatus "diterima".
 */
class VerifikasiOpsionalTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_kegiatan_defaults_to_requiring_verification(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->assertTrue($rencana->perlu_verifikasi);
        $this->actingAs($this->unitUser())->getJson("/api/rencana-aksi/{$rencana->uuid}")->assertJsonPath('data.perlu_verifikasi', true);
    }

    public function test_upload_to_kegiatan_without_verification_is_accepted_immediately(): void
    {
        Notification::fake();
        Storage::fake('local');
        $unit = $this->unitUser();
        $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->for($unit->dinas)->tanpaVerifikasi()->create();

        $response = $this->actingAs($unit)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Dokumentasi kegiatan yang tidak perlu diverifikasi.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status_verifikasi', 'diterima')
            ->assertJsonPath('message', 'Bukti berhasil diunggah dan langsung diterima, karena kegiatan ini tidak memerlukan verifikasi admin. Bukti sudah tampil ke publik.');

        $bukti = BuktiDukung::query()->sole();
        $this->assertSame(StatusVerifikasi::Diterima, $bukti->status_verifikasi);
        $this->assertNotNull($bukti->verified_at);
        $this->assertNull($bukti->verified_by, 'Tidak ada admin yang meninjau, jadi verified_by tetap kosong.');

        // Langsung terlihat publik dan tidak mengganggu antrean admin.
        $this->actingAs($unit)->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonCount(1, 'data');
        Notification::assertNothingSent();
    }

    public function test_upload_still_waits_when_verification_is_required(): void
    {
        Notification::fake();
        Storage::fake('local');
        $unit = $this->unitUser();
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->for($unit->dinas)->create();

        $this->actingAs($unit)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Dokumentasi kegiatan yang perlu diverifikasi.',
        ])->assertCreated()->assertJsonPath('data.status_verifikasi', 'menunggu');

        $this->asGuest()->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertUnauthorized();
        Notification::assertSentTo($admin, BuktiBaruMenungguVerifikasi::class);
    }

    public function test_admin_pusat_can_switch_the_flag_and_pending_bukti_are_released(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->create();
        BuktiDukung::factory()->for($rencana)->count(2)->create();
        $ditolak = BuktiDukung::factory()->for($rencana)->ditolak('Tidak sesuai')->create();

        $this->actingAs($admin)->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", ['perlu_verifikasi' => false])
            ->assertOk()
            ->assertJsonPath('data.perlu_verifikasi', false)
            ->assertJsonPath('meta.bukti_dilepas', 2)
            ->assertJsonPath('message', 'Kegiatan diperbarui. 2 bukti yang masih menunggu langsung diterima.');

        $this->assertFalse($rencana->fresh()->perlu_verifikasi);
        $this->assertSame(0, $rencana->buktiDukung()->menunggu()->count());
        $this->assertSame(2, $rencana->buktiDukung()->diterima()->count());
        $this->assertSame(StatusVerifikasi::Ditolak, $ditolak->fresh()->status_verifikasi, 'Bukti yang sudah ditolak tidak ikut dilepas.');
    }

    public function test_turning_verification_back_on_does_not_touch_existing_bukti(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->tanpaVerifikasi()->create();
        BuktiDukung::factory()->for($rencana)->diterima()->create();

        $this->actingAs($admin)->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", ['perlu_verifikasi' => true])
            ->assertOk()
            ->assertJsonPath('data.perlu_verifikasi', true)
            ->assertJsonPath('meta.bukti_dilepas', 0);

        $this->assertSame(1, $rencana->buktiDukung()->diterima()->count());
    }

    public function test_flag_change_is_recorded_in_audit_trail(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->create(['judul' => 'Portal Smart City']);

        $this->actingAs($admin)->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", ['perlu_verifikasi' => false])->assertOk();

        $catatan = Activity::query()->where('log_name', 'rencana_aksi')->latest('id')->first();
        $this->assertSame('Menetapkan bukti kegiatan «Portal Smart City» langsung diterima tanpa verifikasi', $catatan->description);
        $this->assertSame($admin->id, $catatan->causer_id);
        $this->assertFalse($catatan->attribute_changes['attributes']['perlu_verifikasi']);
    }

    public function test_new_kegiatan_can_be_created_without_verification(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();

        $this->actingAs($admin)->postJson('/api/admin/rencana-aksi', [
            'dinas_id' => $dinas->id,
            'judul' => 'Kegiatan tanpa verifikasi',
            'jenis_keluaran' => 'aplikasi',
            'kategori' => 'tik',
            'dimensi' => 'Smart Governance',
            'tahun_pelaksanaan' => [2026],
            'perlu_verifikasi' => false,
        ])->assertCreated()->assertJsonPath('data.perlu_verifikasi', false);
    }

    public function test_unit_cannot_change_the_flag(): void
    {
        $unit = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($unit->dinas)->create();

        $this->actingAs($unit)->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", ['perlu_verifikasi' => false])
            ->assertForbidden();
        $this->assertTrue($rencana->fresh()->perlu_verifikasi);
    }
}
