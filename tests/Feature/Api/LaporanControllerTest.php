<?php

namespace Tests\Feature\Api;

use App\Enums\Dimensi;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class LaporanControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    /**
     * DLH: 2 selesai (2024 & 2025-2026), 1 berjalan (2026); Dinkes: 1 belum mulai (2019).
     *
     * @return array{dlh: Dinas, dinkes: Dinas}
     */
    private function dataContoh(): array
    {
        $dlh = Dinas::factory()->create(['nama' => 'DLH']);
        $dinkes = Dinas::factory()->create(['nama' => 'Dinkes']);
        $selesai = RencanaAksi::factory()->for($dlh)->tahun([2024])->selesai()->create(['dimensi' => Dimensi::Environment]);
        RencanaAksi::factory()->for($dlh)->tahun([2025, 2026])->selesai()->create(['dimensi' => Dimensi::Environment]);
        RencanaAksi::factory()->for($dlh)->tahun([2026])->berjalan()->create(['dimensi' => Dimensi::Living]);
        RencanaAksi::factory()->for($dinkes)->tahun([2019])->create(['dimensi' => Dimensi::Living]);
        BuktiDukung::factory()->for($selesai)->diterima()->count(2)->create();
        BuktiDukung::factory()->for($selesai)->create();

        return ['dlh' => $dlh, 'dinkes' => $dinkes];
    }

    public function test_json_report_aggregates_by_dimensi_unit_and_tahun(): void
    {
        $this->dataContoh();

        $response = $this->actingAs($this->unitUser())->getJson('/api/laporan');

        $response->assertOk()
            ->assertJsonPath('data.ringkasan.total', 4)
            ->assertJsonPath('data.ringkasan.selesai', 2)
            ->assertJsonPath('data.ringkasan.berjalan', 1)
            ->assertJsonPath('data.ringkasan.belum_mulai', 1)
            ->assertJsonPath('data.ringkasan.persentase', 50)
            ->assertJsonPath('data.ringkasan.bukti_diterima', 2)
            ->assertJsonPath('data.ringkasan.bukti_menunggu', 1)
            ->assertJsonCount(6, 'data.per_dimensi')
            ->assertJsonCount(8, 'data.per_tahun')
            ->assertJsonCount(4, 'data.kegiatan');

        $dimensi = collect($response->json('data.per_dimensi'))->keyBy('dimensi');
        $this->assertSame(['total' => 2, 'selesai' => 2, 'persentase' => 100], collect($dimensi['Smart Environment'])->only(['total', 'selesai', 'persentase'])->all());
        $this->assertSame(0, $dimensi['Smart Branding']['total']);

        $unit = collect($response->json('data.per_unit'))->keyBy('dinas');
        $this->assertSame(67, $unit['DLH']['persentase']);
        $this->assertSame(0, $unit['Dinkes']['persentase']);
        $this->assertSame(2, $unit['DLH']['bukti_diterima']);

        $tahun = collect($response->json('data.per_tahun'))->keyBy('tahun');
        $this->assertSame(2, $tahun[2026]['total']);
        $this->assertSame(1, $tahun[2026]['selesai']);
        $this->assertSame(1, $tahun[2019]['belum_mulai']);
        $this->assertSame(0, $tahun[2021]['total']);
    }

    public function test_filters_narrow_the_report(): void
    {
        ['dlh' => $dlh] = $this->dataContoh();
        $user = $this->unitUser();

        $this->actingAs($user)->getJson("/api/laporan?tahun=2026&dinas={$dlh->slug}")
            ->assertOk()
            ->assertJsonPath('data.filter.tahun', 2026)
            ->assertJsonPath('data.filter.dinas.nama', 'DLH')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonCount(1, 'data.per_tahun')
            ->assertJsonPath('data.per_tahun.0.tahun', 2026);

        $this->actingAs($user)->getJson('/api/laporan?dimensi=Smart+Living')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonCount(1, 'data.per_dimensi');

        $this->actingAs($user)->getJson('/api/laporan?dimensi=Smart+Banking')->assertUnprocessable();
    }

    public function test_xlsx_export_downloads_workbook_with_five_sheets(): void
    {
        $this->dataContoh();

        $response = $this->actingAs($this->adminPusatUser())->get('/api/laporan/export?format=xlsx&tahun=2026');

        $response->assertOk()
            ->assertDownload('laporan-rencana-aksi-2026-'.now()->format('Ymd').'.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'laporan').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $workbook = IOFactory::load($path);

        $this->assertSame(['Ringkasan', 'Per Dimensi', 'Per Unit', 'Per Tahun', 'Daftar Kegiatan'], $workbook->getSheetNames());
        $ringkasan = $workbook->getSheetByName('Ringkasan');
        $this->assertSame('Total kegiatan', $ringkasan->getCell('A6')->getValue());
        $this->assertSame(2, $ringkasan->getCell('B6')->getValue());
        $daftar = $workbook->getSheetByName('Daftar Kegiatan');
        $this->assertSame('Kegiatan / Rencana Aksi', $daftar->getCell('E1')->getValue());
        $this->assertSame(3, $daftar->getHighestRow());
        unlink($path);
    }

    public function test_multi_year_export_uses_all_selected_years(): void
    {
        ['dlh' => $dlh] = $this->dataContoh();
        $user = $this->adminPusatUser();

        $query = http_build_query(['tahun' => [2024, 2026], 'dinas' => $dlh->slug]);
        $json = $this->actingAs($user)->getJson("/api/laporan?{$query}");
        $json->assertOk()
            ->assertJsonPath('data.ringkasan.total', 3)
            ->assertJsonPath('data.ringkasan.selesai', 2)
            ->assertJsonPath('data.ringkasan.persentase', 67);

        $response = $this->actingAs($user)->get("/api/laporan/export?format=xlsx&{$query}");
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'laporan').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $workbook = IOFactory::load($path);
        $ringkasan = $workbook->getSheetByName('Ringkasan');

        $this->assertSame(3, $ringkasan->getCell('B6')->getValue());
        $this->assertSame(2, $ringkasan->getCell('B9')->getValue());
        $this->assertSame(67, $ringkasan->getCell('B10')->getValue());

        unlink($path);
    }

    public function test_pdf_export_downloads_a_pdf_document(): void
    {
        $this->dataContoh();

        $response = $this->actingAs($this->unitUser())->get('/api/laporan/export?format=pdf');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('laporan-rencana-aksi-semua-tahun-'.now()->format('Ymd').'.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_export_requires_valid_format_and_login(): void
    {
        $this->actingAs($this->unitUser())->getJson('/api/laporan/export')->assertUnprocessable()->assertJsonValidationErrors(['format']);
        $this->actingAs($this->unitUser())->getJson('/api/laporan/export?format=csv')->assertUnprocessable();
        $this->asGuest()->getJson('/api/laporan')->assertUnauthorized();
        $this->asGuest()->getJson('/api/laporan/export?format=pdf')->assertUnauthorized();
    }
}
