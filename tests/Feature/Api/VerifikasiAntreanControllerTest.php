<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class VerifikasiAntreanControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_admin_pusat_sees_all_pending_bukti_oldest_first(): void
    {
        $admin = $this->adminPusatUser();
        $lama = BuktiDukung::factory()->create(['created_at' => now()->subDays(2)]);
        $baru = BuktiDukung::factory()->create(['created_at' => now()->subDay()]);
        BuktiDukung::factory()->diterima()->create();
        BuktiDukung::factory()->ditolak()->create();

        $response = $this->actingAs($admin)->getJson('/api/verifikasi/antrean');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $lama->id)
            ->assertJsonPath('data.1.id', $baru->id)
            ->assertJsonPath('meta.total', 2);
        $this->assertNotNull($response->json('data.0.rencana_aksi.dinas.nama'));
    }

    public function test_filters_by_dinas_and_tahun(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $cocok = BuktiDukung::factory()->for(RencanaAksi::factory()->for($dinas)->tahun([2024]))->create();
        BuktiDukung::factory()->for(RencanaAksi::factory()->for($dinas)->tahun([2019]))->create();
        BuktiDukung::factory()->create();

        $this->actingAs($admin)->getJson("/api/verifikasi/antrean?dinas={$dinas->slug}")->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson("/api/verifikasi/antrean?dinas={$dinas->slug}&tahun=2024")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cocok->id);
    }

    public function test_unit_and_guest_cannot_access_queue(): void
    {
        $this->actingAs($this->unitUser())->getJson('/api/verifikasi/antrean')->assertForbidden();
        $this->asGuest()->getJson('/api/verifikasi/antrean')->assertUnauthorized();
    }
}
