<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class BuktiDukungControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function buktiDenganFile(array $state = [], string $status = 'menunggu'): BuktiDukung
    {
        $factory = BuktiDukung::factory()->state($state);
        $bukti = match ($status) {
            'diterima' => $factory->diterima()->create(),
            'ditolak' => $factory->ditolak()->create(),
            default => $factory->create(),
        };
        Storage::disk('local')->put($bukti->path_file, '%PDF-1.4 isi dokumen');

        return $bukti;
    }

    public function test_guest_cannot_access_bukti_endpoints(): void
    {
        $diterima = $this->buktiDenganFile(status: 'diterima');
        $menunggu = $this->buktiDenganFile();

        $this->get("/api/bukti/{$diterima->uuid}/download")
            ->assertOk()
            ->assertDownload($diterima->nama_file);
        $this->getJson("/api/bukti/{$diterima->uuid}")->assertUnauthorized();
        $this->getJson("/api/bukti/{$menunggu->uuid}/download")->assertForbidden();
        $this->getJson("/api/bukti/{$menunggu->uuid}")->assertUnauthorized();
    }

    public function test_owner_unit_can_download_pending_bukti_and_other_unit_cannot(): void
    {
        $pemilik = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($pemilik->dinas)->create();
        $bukti = $this->buktiDenganFile(['rencana_aksi_id' => $rencana->id]);

        $this->actingAs($pemilik)->get("/api/bukti/{$bukti->uuid}/download")->assertOk();
        $this->actingAs($this->unitUser())->getJson("/api/bukti/{$bukti->uuid}/download")->assertForbidden();
    }

    public function test_missing_file_on_disk_returns_404(): void
    {
        $bukti = BuktiDukung::factory()->diterima()->create();

        $this->getJson("/api/bukti/{$bukti->uuid}/download")->assertNotFound();
    }

    public function test_owner_unit_deletes_bukti_and_its_file(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();
        $bukti = $this->buktiDenganFile(['rencana_aksi_id' => $rencana->id]);

        $this->actingAs($user)->deleteJson("/api/bukti/{$bukti->uuid}")->assertNoContent();

        $this->assertDatabaseMissing('bukti_dukung', ['id' => $bukti->id]);
        Storage::disk('local')->assertMissing($bukti->path_file);
    }

    public function test_scope_rules_for_delete(): void
    {
        $dinas = Dinas::factory()->create();
        $bukti = $this->buktiDenganFile(['rencana_aksi_id' => RencanaAksi::factory()->for($dinas)->create()->id]);

        $this->actingAs($this->unitUser())->deleteJson("/api/bukti/{$bukti->uuid}")->assertForbidden();
        $this->asGuest()->deleteJson("/api/bukti/{$bukti->uuid}")->assertUnauthorized();
        $this->assertDatabaseHas('bukti_dukung', ['id' => $bukti->id]);

        $this->actingAs($this->unitUser($dinas))->deleteJson("/api/bukti/{$bukti->uuid}")->assertNoContent();
    }

    public function test_admin_pusat_can_delete_any_bukti(): void
    {
        $bukti = $this->buktiDenganFile(status: 'diterima');

        $this->actingAs($this->adminPusatUser())->deleteJson("/api/bukti/{$bukti->uuid}")->assertNoContent();
        $this->assertDatabaseMissing('bukti_dukung', ['id' => $bukti->id]);
    }
}
