<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * URL tidak memuat ID auto increment: kegiatan & bukti memakai UUID, unit memakai slug.
 */
class PengenalUrlTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_new_rencana_aksi_and_bukti_get_a_uuid_while_keeping_their_numeric_primary_key(): void
    {
        $bukti = BuktiDukung::factory()->create();

        $this->assertTrue(Str::isUuid($bukti->uuid));
        $this->assertTrue(Str::isUuid($bukti->rencanaAksi->uuid));
        $this->assertIsInt($bukti->id);
        $this->assertIsInt($bukti->rencanaAksi->id);
    }

    public function test_rencana_aksi_is_reachable_by_uuid_only(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($this->unitUser())->getJson("/api/rencana-aksi/{$rencana->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $rencana->uuid);
        $this->getJson("/api/rencana-aksi/{$rencana->id}")->assertNotFound();
        $this->getJson("/api/rencana-aksi/{$rencana->id}/bukti")->assertNotFound();
        $this->actingAs($this->adminPusatUser())
            ->patchJson("/api/admin/rencana-aksi/{$rencana->id}", ['judul' => 'Tebakan'])
            ->assertNotFound();
    }

    public function test_bukti_file_links_use_uuid_and_numeric_ids_are_not_found(): void
    {
        Storage::fake('local');
        $bukti = BuktiDukung::factory()->diterima()->state(['tipe_file' => 'application/pdf', 'nama_file' => 'laporan.pdf'])->create();
        Storage::disk('local')->put($bukti->path_file, '%PDF-1.4 isi');

        $data = $this->actingAs($this->unitUser())->getJson("/api/bukti/{$bukti->uuid}")->assertOk()->json('data');

        $this->assertStringContainsString("/api/bukti/{$bukti->uuid}/download", $data['url_unduh']);
        $this->assertStringContainsString("/api/bukti/{$bukti->uuid}/lihat/laporan.pdf", $data['url_lihat']);
        $this->get("/api/bukti/{$bukti->id}/download")->assertNotFound();
        $this->get("/api/bukti/{$bukti->id}/lihat")->assertNotFound();
        $this->getJson("/api/bukti/{$bukti->id}")->assertNotFound();
    }

    public function test_unit_filters_and_unit_urls_use_the_slug_instead_of_the_id(): void
    {
        $dinas = Dinas::factory()->create(['nama' => 'Dinas Lingkungan Hidup', 'slug' => 'dinas-lingkungan-hidup']);
        RencanaAksi::factory()->for($dinas)->create();
        RencanaAksi::factory()->create();

        $this->getJson('/api/rencana-aksi?dinas=dinas-lingkungan-hidup')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/rencana-aksi?dinas={$dinas->id}")->assertUnprocessable()->assertJsonValidationErrors('dinas');
        $this->actingAs($this->unitUser())->getJson('/api/bukti/search?dinas=unit-tidak-ada')->assertUnprocessable()->assertJsonValidationErrors('dinas');
        $this->getJson('/api/dinas/dinas-lingkungan-hidup')->assertOk()->assertJsonPath('data.nama', 'Dinas Lingkungan Hidup');
        $this->getJson("/api/dinas/{$dinas->id}")->assertNotFound();
    }
}
