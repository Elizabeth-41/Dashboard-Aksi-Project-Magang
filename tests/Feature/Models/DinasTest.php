<?php

namespace Tests\Feature\Models;

use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DinasTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_dinas_with_rencana_aksi_is_rejected(): void
    {
        $dinas = Dinas::factory()->create();
        RencanaAksi::factory()->for($dinas)->create();

        $this->expectException(QueryException::class);

        $dinas->delete();
    }

    public function test_slug_must_be_unique(): void
    {
        Dinas::factory()->create(['nama' => 'DLH', 'slug' => 'dlh']);

        $this->expectException(QueryException::class);

        Dinas::factory()->create(['nama' => 'Dinas Lingkungan Hidup', 'slug' => 'dlh']);
    }
}
