<?php

namespace Tests\Feature\Models;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuktiDukungTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_bukti_starts_as_menunggu_without_verifier(): void
    {
        $bukti = BuktiDukung::factory()->create()->fresh();

        $this->assertSame(StatusVerifikasi::Menunggu, $bukti->status_verifikasi);
        $this->assertNull($bukti->verified_by);
        $this->assertNull($bukti->verified_at);
        $this->assertNull($bukti->alasan_penolakan);
        $this->assertFalse($bukti->isDiterima());
    }

    public function test_diterima_and_menunggu_scopes_partition_by_verification_status(): void
    {
        $menunggu = BuktiDukung::factory()->create();
        $diterima = BuktiDukung::factory()->diterima()->create();
        BuktiDukung::factory()->ditolak('File tidak terbaca')->create();

        $this->assertSame([$diterima->id], BuktiDukung::diterima()->pluck('id')->all());
        $this->assertSame([$menunggu->id], BuktiDukung::menunggu()->pluck('id')->all());
    }

    public function test_rejected_bukti_stores_reason_and_verifier(): void
    {
        $bukti = BuktiDukung::factory()->ditolak('File tidak terbaca')->create()->fresh();

        $this->assertSame(StatusVerifikasi::Ditolak, $bukti->status_verifikasi);
        $this->assertSame('File tidak terbaca', $bukti->alasan_penolakan);
        $this->assertTrue($bukti->verifier->isAdminPusat());
        $this->assertNotNull($bukti->verified_at);
    }

    public function test_deskripsi_is_required(): void
    {
        $this->expectException(QueryException::class);

        BuktiDukung::factory()->create(['deskripsi' => null]);
    }

    public function test_deleting_rencana_aksi_deletes_its_bukti(): void
    {
        $rencana = RencanaAksi::factory()->create();
        $bukti = BuktiDukung::factory()->for($rencana)->create();

        $rencana->delete();

        $this->assertDatabaseMissing('bukti_dukung', ['id' => $bukti->id]);
    }

    public function test_deleting_uploader_keeps_bukti(): void
    {
        $uploader = User::factory()->create();
        $bukti = BuktiDukung::factory()->create(['uploaded_by' => $uploader->id]);

        $uploader->delete();

        $this->assertNull($bukti->fresh()->uploaded_by);
    }
}
