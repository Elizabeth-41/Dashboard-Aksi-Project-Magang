<?php

namespace Tests\Feature\Api;

use App\Enums\Dimensi;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class BuktiSearchControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_guest_cannot_search_bukti(): void
    {
        $diterima = BuktiDukung::factory()->diterima()->create();
        BuktiDukung::factory()->create();
        BuktiDukung::factory()->ditolak()->create();

        $this->getJson('/api/bukti/search')->assertUnauthorized();
        $this->getJson('/api/bukti/search?status_verifikasi=menunggu')->assertUnauthorized();
    }

    public function test_logged_in_unit_sees_own_pending_bukti_plus_all_accepted(): void
    {
        $user = $this->unitUser();
        $sendiri = RencanaAksi::factory()->for($user->dinas)->create();
        $milikSendiriMenunggu = BuktiDukung::factory()->for($sendiri)->create();
        $orangLainDiterima = BuktiDukung::factory()->diterima()->create();
        BuktiDukung::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/bukti/search');

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            [$milikSendiriMenunggu->id, $orangLainDiterima->id],
            collect($response->json('data'))->pluck('id')->all(),
        );

        $this->actingAs($user)->getJson('/api/bukti/search?status_verifikasi=menunggu')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milikSendiriMenunggu->id);
    }

    public function test_filters_by_rencana_aksi_attributes_keyword_and_file_type(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $rencana = RencanaAksi::factory()->for($dinas)->tahun([2023, 2025])->create([
            'dimensi' => Dimensi::Living,
            'judul' => 'Aplikasi e-Puskesmas',
        ]);
        $pdf = BuktiDukung::factory()->for($rencana)->diterima()->create([
            'nama_file' => 'laporan.pdf',
            'tipe_file' => 'application/pdf',
            'deskripsi' => 'Laporan implementasi',
        ]);
        $foto = BuktiDukung::factory()->for($rencana)->diterima()->create([
            'nama_file' => 'foto.png',
            'tipe_file' => 'image/png',
            'deskripsi' => 'Dokumentasi rapat',
        ]);
        BuktiDukung::factory()->diterima()
            ->for(RencanaAksi::factory()->tahun([2019])->create(['dimensi' => Dimensi::Branding]))
            ->create(['nama_file' => 'lain.docx', 'tipe_file' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

        $this->actingAs($admin)->getJson("/api/bukti/search?dinas={$dinas->slug}")->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/bukti/search?dimensi=Smart+Living&tahun=2025')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/bukti/search?tahun=2024')->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/api/bukti/search?tipe_file=pdf')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pdf->id);
        $this->actingAs($admin)->getJson('/api/bukti/search?tipe_file=gambar')->assertJsonPath('data.0.id', $foto->id);
        $this->actingAs($admin)->getJson('/api/bukti/search?q=rapat')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $foto->id);
        $this->actingAs($admin)->getJson('/api/bukti/search?q=puskesmas')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/bukti/search?tipe_file=video')->assertUnprocessable();
    }
}
