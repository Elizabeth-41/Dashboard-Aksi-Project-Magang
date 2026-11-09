<?php

namespace Tests\Feature\Api;

use App\Enums\Dimensi;
use App\Models\BuktiDukung;
use App\Models\QuickWin;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickWinControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_year_quick_wins_only_include_accepted_images(): void
    {
        $year = now()->year;
        $quickWin = RencanaAksi::factory()->tahun([$year])->create(['dimensi' => Dimensi::Governance]);
        QuickWin::create(['rencana_aksi_id' => $quickWin->id, 'tahun' => $year]);
        BuktiDukung::factory()->for($quickWin)->diterima()->create(['tipe_file' => 'image/jpeg']);
        BuktiDukung::factory()->for($quickWin)->diterima()->create(['tipe_file' => 'application/pdf']);
        BuktiDukung::factory()->for($quickWin)->create(['tipe_file' => 'image/png']);

        $otherYear = RencanaAksi::factory()->tahun([$year - 1])->create();
        QuickWin::create(['rencana_aksi_id' => $otherYear->id, 'tahun' => $year - 1]);
        BuktiDukung::factory()->for($otherYear)->diterima()->create(['tipe_file' => 'image/png']);

        $this->getJson('/api/quick-wins')
            ->assertOk()
            ->assertJsonPath('meta.tahun', $year)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.bukti')
            ->assertJsonPath('data.0.bukti.0.kelompok_tipe', 'gambar');
    }

    public function test_quick_win_without_accepted_image_is_not_returned(): void
    {
        $year = now()->year;
        $quickWin = RencanaAksi::factory()->tahun([$year])->create();
        QuickWin::create(['rencana_aksi_id' => $quickWin->id, 'tahun' => $year]);
        BuktiDukung::factory()->for($quickWin)->diterima()->create(['tipe_file' => 'application/pdf']);

        $this->getJson('/api/quick-wins')->assertOk()->assertJsonCount(0, 'data');
    }
}
