<?php

namespace Tests\Feature\Admin;

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

    public function test_admin_pusat_creates_rencana_aksi_with_generated_tahun_label(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/rencana-aksi', [
            'dinas_id' => $dinas->id,
            'judul' => 'Integrasi Data Lintas OPD',
            'program' => '',
            'jenis_keluaran' => 'aplikasi',
            'kategori' => 'tik',
            'dimensi' => 'Smart Governance',
            'tahun_pelaksanaan' => [2026, 2025, 2027],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.judul', 'Integrasi Data Lintas OPD')
            ->assertJsonPath('data.program', null)
            ->assertJsonPath('data.tahun_label', '2025 - 2027')
            ->assertJsonPath('data.tahun_pelaksanaan', [2025, 2026, 2027])
            ->assertJsonPath('data.status', 'belum_mulai')
            ->assertJsonPath('data.created_by', $admin->id)
            ->assertJsonPath('data.dinas.id', $dinas->id);
    }

    public function test_non_contiguous_years_get_ampersand_label(): void
    {
        $admin = $this->adminPusatUser();

        $this->actingAs($admin)->postJson('/api/admin/rencana-aksi', [
            'dinas_id' => Dinas::factory()->create()->id,
            'judul' => 'Lomba inovasi',
            'jenis_keluaran' => 'infrastruktur',
            'kategori' => 'non_tik',
            'dimensi' => 'Smart Environment',
            'tahun_pelaksanaan' => [2025, 2023],
            'status' => 'berjalan',
        ])->assertCreated()
            ->assertJsonPath('data.tahun_label', '2023 & 2025')
            ->assertJsonPath('data.status', 'berjalan');
    }

    public function test_store_validates_required_fields_and_enums(): void
    {
        $admin = $this->adminPusatUser();

        $this->actingAs($admin)->postJson('/api/admin/rencana-aksi', [
            'dinas_id' => 999,
            'dimensi' => 'Smart Banking',
            'tahun_pelaksanaan' => [2023, 2023],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'dinas_id', 'judul', 'jenis_keluaran', 'kategori', 'dimensi', 'tahun_pelaksanaan.0',
        ]);
    }

    public function test_only_admin_pusat_can_create_or_edit(): void
    {
        $rencana = RencanaAksi::factory()->create();
        $payload = ['judul' => 'Diubah'];

        $this->actingAs($this->unitUser())->postJson('/api/admin/rencana-aksi', $payload)->assertForbidden();
        $this->actingAs($this->unitUser())->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", $payload)->assertForbidden();
        $this->asGuest()->postJson('/api/admin/rencana-aksi', $payload)->assertUnauthorized();
    }

    public function test_admin_pusat_edits_partial_fields(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->tahun([2022, 2023])->create(['dimensi' => Dimensi::Economy]);

        $this->actingAs($admin)->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", [
            'judul' => 'Judul Baru',
            'tahun_pelaksanaan' => [2024],
            'status' => 'selesai',
        ])->assertOk()
            ->assertJsonPath('data.judul', 'Judul Baru')
            ->assertJsonPath('data.tahun_label', '2024')
            ->assertJsonPath('data.dimensi', Dimensi::Economy->value);

        $fresh = $rencana->fresh();
        $this->assertSame([2024], $fresh->tahun_pelaksanaan);
        $this->assertSame(StatusKegiatan::Selesai, $fresh->status);
        $this->assertSame(Dimensi::Economy, $fresh->dimensi);
    }
}
