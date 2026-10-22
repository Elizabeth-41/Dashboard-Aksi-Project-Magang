<?php

namespace Tests\Feature\Api;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class BuktiVerifikasiControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_admin_pusat_accepts_bukti_and_it_becomes_public(): void
    {
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->create();

        $this->asGuest()->getJson("/api/rencana-aksi/{$bukti->rencanaAksi->uuid}/bukti")->assertUnauthorized();

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'diterima')
            ->assertJsonPath('data.verified_by', $admin->id)
            ->assertJsonPath('data.verifier.id', $admin->id)
            ->assertJsonPath('data.alasan_penolakan', null)
            ->assertJsonPath('message', 'Bukti diterima.');

        $fresh = $bukti->fresh();
        $this->assertSame(StatusVerifikasi::Diterima, $fresh->status_verifikasi);
        $this->assertNotNull($fresh->verified_at);
        $this->asGuest()->getJson("/api/rencana-aksi/{$bukti->rencanaAksi->uuid}/bukti")->assertUnauthorized();
    }

    public function test_admin_pusat_rejects_bukti_with_reason(): void
    {
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->create();

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", [
            'status_verifikasi' => 'ditolak',
            'alasan_penolakan' => 'Dokumen tidak terbaca, mohon unggah ulang.',
        ])->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'ditolak')
            ->assertJsonPath('data.alasan_penolakan', 'Dokumen tidak terbaca, mohon unggah ulang.');

        $this->assertSame(StatusVerifikasi::Ditolak, $bukti->fresh()->status_verifikasi);
    }

    public function test_rejection_without_reason_is_rejected(): void
    {
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->create();

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'ditolak'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['alasan_penolakan']);

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'menunggu'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status_verifikasi']);

        $this->assertSame(StatusVerifikasi::Menunggu, $bukti->fresh()->status_verifikasi);
    }

    public function test_accepting_a_previously_rejected_bukti_clears_the_reason(): void
    {
        $admin = $this->adminPusatUser();
        $bukti = BuktiDukung::factory()->ditolak('Salah file')->create();

        $this->actingAs($admin)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])
            ->assertOk()
            ->assertJsonPath('data.alasan_penolakan', null);
    }

    public function test_unit_and_guest_cannot_verify(): void
    {
        $bukti = BuktiDukung::factory()->create();
        $pemilik = $this->unitUser($bukti->rencanaAksi->dinas);

        $this->actingAs($pemilik)->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])->assertForbidden();
        $this->asGuest()->patchJson("/api/bukti/{$bukti->uuid}/verifikasi", ['status_verifikasi' => 'diterima'])->assertUnauthorized();

        $this->assertSame(StatusVerifikasi::Menunggu, $bukti->fresh()->status_verifikasi);
    }
}
