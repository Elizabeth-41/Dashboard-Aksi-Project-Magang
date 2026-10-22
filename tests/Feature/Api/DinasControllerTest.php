<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinasControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_gets_units_with_progress_counts(): void
    {
        $dinas = Dinas::factory()->create(['nama' => 'DLH']);
        RencanaAksi::factory()->for($dinas)->selesai()->count(2)->create();
        RencanaAksi::factory()->for($dinas)->berjalan()->create();
        RencanaAksi::factory()->for($dinas)->create();
        BuktiDukung::factory()->diterima()->for(RencanaAksi::factory()->for($dinas)->selesai())->create();
        BuktiDukung::factory()->for(RencanaAksi::factory()->for($dinas))->create();

        $response = $this->getJson('/api/dinas');

        // Factory bukti ikut membuat user pengunggah beserta dinas-nya, jadi
        // ambil entri DLH secara eksplisit.
        $response->assertOk();
        $entry = collect($response->json('data'))->firstWhere('id', $dinas->id);
        $this->assertSame('DLH', $entry['nama']);
        $this->assertSame([
            'total' => 6,
            'belum_mulai' => 2,
            'berjalan' => 1,
            'selesai' => 3,
            'bukti_diterima' => 1,
            'persentase' => 50,
        ], $entry['progres']);
    }

    public function test_tahun_filter_limits_progress_to_that_year(): void
    {
        $dinas = Dinas::factory()->create();
        RencanaAksi::factory()->for($dinas)->tahun([2019])->selesai()->create();
        RencanaAksi::factory()->for($dinas)->tahun([2022, 2023, 2024])->create();
        RencanaAksi::factory()->for($dinas)->tahun([2023, 2025])->selesai()->create();

        $response = $this->getJson('/api/dinas?tahun=2023');

        $response->assertOk()->assertJsonPath('meta.tahun', 2023);
        $this->assertSame(2, $response->json('data.0.progres.total'));
        $this->assertSame(1, $response->json('data.0.progres.selesai'));
        $this->assertSame(50, $response->json('data.0.progres.persentase'));

        $this->getJson('/api/dinas?tahun=2019')->assertJsonPath('data.0.progres.persentase', 100);
        $this->getJson('/api/dinas?tahun=abc')->assertUnprocessable();
    }

    public function test_show_returns_one_unit_with_its_progress_and_no_hierarchy(): void
    {
        $dinas = Dinas::factory()->create(['nama' => 'DLH']);
        RencanaAksi::factory()->for($dinas)->selesai()->create();
        RencanaAksi::factory()->for($dinas)->create();

        $this->getJson("/api/dinas/{$dinas->slug}")
            ->assertOk()
            ->assertJsonPath('data.nama', 'DLH')
            ->assertJsonPath('data.progres.total', 2)
            ->assertJsonPath('data.progres.persentase', 50)
            ->assertJsonMissingPath('data.parent_id')
            ->assertJsonMissingPath('data.children');
    }

    public function test_unit_rencana_aksi_list_filters_by_tahun(): void
    {
        $dinas = Dinas::factory()->create();
        $lama = RencanaAksi::factory()->for($dinas)->tahun([2019, 2020])->create();
        RencanaAksi::factory()->for($dinas)->tahun([2024])->create();
        RencanaAksi::factory()->create();

        $this->getJson("/api/dinas/{$dinas->slug}/rencana-aksi")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/dinas/{$dinas->slug}/rencana-aksi?tahun=2020")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lama->id)
            ->assertJsonPath('data.0.jumlah_bukti', 0);
    }
}
