<?php

namespace Tests\Feature\Models;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RencanaAksiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_rencana_aksi_starts_as_belum_mulai(): void
    {
        $this->assertSame(StatusKegiatan::BelumMulai, (new RencanaAksi)->status);
        $this->assertSame(StatusKegiatan::BelumMulai, RencanaAksi::factory()->create()->fresh()->status);
    }

    public function test_enum_and_json_columns_round_trip(): void
    {
        $rencana = RencanaAksi::factory()->tahun([2023, 2025])->create([
            'dimensi' => Dimensi::Environment,
            'jenis_keluaran' => JenisKeluaran::Infrastruktur,
            'kategori' => Kategori::NonTik,
            'program' => null,
        ]);

        $fresh = $rencana->fresh();

        $this->assertSame(Dimensi::Environment, $fresh->dimensi);
        $this->assertSame(JenisKeluaran::Infrastruktur, $fresh->jenis_keluaran);
        $this->assertSame(Kategori::NonTik, $fresh->kategori);
        $this->assertSame([2023, 2025], $fresh->tahun_pelaksanaan);
        $this->assertSame('2023 - 2025', $fresh->tahun_label);
        $this->assertNull($fresh->program);
    }

    public function test_tahun_scope_matches_non_contiguous_periods(): void
    {
        $terputus = RencanaAksi::factory()->tahun([2023, 2025])->create();
        RencanaAksi::factory()->tahun([2022, 2023, 2024])->create();
        $lama = RencanaAksi::factory()->tahun([2019])->create();

        $this->assertSame([$terputus->id], RencanaAksi::tahun(2025)->pluck('id')->all());
        $this->assertCount(2, RencanaAksi::tahun(2023)->get());
        $this->assertSame([$lama->id], RencanaAksi::tahun(2019)->pluck('id')->all());
        $this->assertCount(0, RencanaAksi::tahun(2021)->get());
    }

    public function test_dengan_status_scope_filters_manual_status(): void
    {
        RencanaAksi::factory()->create();
        $berjalan = RencanaAksi::factory()->berjalan()->create();
        RencanaAksi::factory()->selesai()->create();

        $this->assertSame([$berjalan->id], RencanaAksi::denganStatus(StatusKegiatan::Berjalan)->pluck('id')->all());
    }

    public function test_bukti_diterima_relation_hides_unverified_evidence(): void
    {
        $rencana = RencanaAksi::factory()->create();
        $diterima = BuktiDukung::factory()->for($rencana)->diterima()->create();
        BuktiDukung::factory()->for($rencana)->create();
        BuktiDukung::factory()->for($rencana)->ditolak()->create();

        $this->assertCount(3, $rencana->buktiDukung);
        $this->assertSame([$diterima->id], $rencana->buktiDiterima->pluck('id')->all());
    }

    public function test_deleting_creator_keeps_rencana_aksi(): void
    {
        $admin = User::factory()->adminPusat()->create();
        $rencana = RencanaAksi::factory()->create(['created_by' => $admin->id]);

        $admin->delete();

        $this->assertNull($rencana->fresh()->created_by);
    }
}
