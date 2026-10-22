<?php

namespace Tests\Feature\Api;

use App\Enums\Dimensi;
use App\Enums\StatusKegiatan;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class RencanaAksiControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_guest_can_search_rencana_aksi_by_keyword_and_filters(): void
    {
        $dinas = Dinas::factory()->create();
        $cocok = RencanaAksi::factory()->for($dinas)->create([
            'judul' => 'Pengembangan Portal Smart City',
            'dimensi' => Dimensi::Governance,
        ]);
        RencanaAksi::factory()->for($dinas)->create(['judul' => 'Penanaman pohon', 'dimensi' => Dimensi::Environment]);
        RencanaAksi::factory()->create(['judul' => 'Portal Pendidikan', 'dimensi' => Dimensi::Society]);

        $this->getJson('/api/rencana-aksi?q=portal')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/rencana-aksi?q=portal&dinas={$dinas->slug}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cocok->id)
            ->assertJsonPath('data.0.dinas.id', $dinas->id)
            ->assertJsonPath('message', null);

        $this->getJson('/api/rencana-aksi?dimensi=Smart+Environment')->assertJsonCount(1, 'data');
        $this->getJson('/api/rencana-aksi?dimensi=Smart+Banking')->assertUnprocessable();
    }

    public function test_empty_result_returns_tidak_ditemukan_message(): void
    {
        $this->getJson('/api/rencana-aksi?q=tidak-ada')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'Tidak ada Rencana Kegiatan Aksi yang ditemukan');
    }

    public function test_show_returns_detail_with_counts(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->getJson("/api/rencana-aksi/{$rencana->uuid}")->assertUnauthorized();

        $this->getJson('/api/rencana-aksi/999999')->assertUnauthorized();
    }

    public function test_unit_can_change_status_of_its_own_rencana_aksi(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $this->actingAs($user)
            ->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'berjalan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'berjalan')
            ->assertJsonPath('data.status_label', 'Berjalan');

        $this->assertSame(StatusKegiatan::Berjalan, $rencana->fresh()->status);
    }

    public function test_unit_cannot_change_status_of_another_unit(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'selesai'])
            ->assertForbidden();

        $this->assertSame(StatusKegiatan::BelumMulai, $rencana->fresh()->status);
    }

    public function test_admin_pusat_can_change_any_status_and_invalid_status_is_rejected(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($admin)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'selesai'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'ditunda'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_guest_cannot_change_status(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'selesai'])->assertUnauthorized();
    }
}
