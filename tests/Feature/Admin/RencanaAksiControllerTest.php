<?php

namespace Tests\Feature\Admin;

use App\Enums\Dimensi;
use App\Enums\StatusKegiatan;
use App\Imports\RencanaAksiImport;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function test_admin_pusat_can_delete_activity_and_cascade_its_related_data(): void
    {
        Storage::fake('local');
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->create();
        $bukti = BuktiDukung::factory()->for($rencana)->create(['path_file' => 'bukti/hapus.pdf']);
        Storage::disk('local')->put($bukti->path_file, 'isi');

        $this->actingAs($admin)->deleteJson("/api/admin/rencana-aksi/{$rencana->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Kegiatan dan seluruh bukti pendukungnya berhasil dihapus.');

        $this->assertDatabaseMissing('rencana_aksi', ['id' => $rencana->id]);
        $this->assertDatabaseMissing('bukti_dukung', ['id' => $bukti->id]);
        Storage::disk('local')->assertMissing($bukti->path_file);
    }

    public function test_non_admin_cannot_delete_activity(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($this->unitUser())
            ->deleteJson("/api/admin/rencana-aksi/{$rencana->uuid}")
            ->assertForbidden();

        $this->assertDatabaseHas('rencana_aksi', ['id' => $rencana->id]);
    }

    public function test_admin_pusat_can_import_multiple_rencana_aksi_rows(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $import = new RencanaAksiImport($admin->id);

        $import->collection(new Collection([
            [
                'dinas_id' => $dinas->id,
                'judul' => 'Kegiatan hasil import',
                'program' => 'Program digital',
                'dimensi' => 'Smart Governance',
                'jenis_keluaran' => 'aplikasi',
                'kategori' => 'tik',
                'status' => 'berjalan',
                'perlu_verifikasi' => 1,
                'tahun_pelaksanaan' => '2025,2027',
            ],
        ]));

        $this->assertSame(1, $import->jumlahDiimpor());
        $this->assertDatabaseHas('rencana_aksi', [
            'dinas_id' => $dinas->id,
            'judul' => 'Kegiatan hasil import',
            'tahun_label' => '2025 & 2027',
            'status' => 'berjalan',
            'created_by' => $admin->id,
        ]);
    }

    public function test_import_maps_numeric_excel_columns_in_template_order(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $import = new RencanaAksiImport($admin->id);

        $import->collection(new Collection([
            [
                $dinas->id,
                'Kegiatan dengan kolom Excel',
                'Program contoh',
                'Smart Governance',
                'aplikasi',
                'tik',
                'belum_mulai',
                1,
                '2025,2026',
            ],
        ]));

        $this->assertSame(1, $import->jumlahDiimpor());
        $this->assertDatabaseHas('rencana_aksi', [
            'judul' => 'Kegiatan dengan kolom Excel',
            'dimensi' => 'Smart Governance',
        ]);
    }

    public function test_import_rejects_an_activity_that_already_exists(): void
    {
        $admin = $this->adminPusatUser();
        $rencana = RencanaAksi::factory()->tahun([2025, 2026])->create([
            'judul' => 'Kegiatan yang sudah ada',
        ]);
        $import = new RencanaAksiImport($admin->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Baris 2: Kegiatan "Kegiatan yang sudah ada" untuk unit ID '.$rencana->dinas_id.' pada tahun 2025, 2026 sudah ada. Hapus atau perbaiki baris ini di file Excel, lalu impor ulang.');

        $import->collection(new Collection([[
            'dinas_id' => $rencana->dinas_id,
            'judul' => $rencana->judul,
            'dimensi' => 'Smart Governance',
            'jenis_keluaran' => 'aplikasi',
            'kategori' => 'tik',
            'tahun_pelaksanaan' => '2025,2026',
        ]]));
    }

    public function test_import_is_atomic_when_a_row_is_invalid(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $import = new RencanaAksiImport($admin->id);

        $this->expectException(ValidationException::class);

        try {
            $import->collection(new Collection([
                [
                    'dinas_id' => $dinas->id,
                    'judul' => 'Baris valid',
                    'dimensi' => 'Smart Governance',
                    'jenis_keluaran' => 'aplikasi',
                    'kategori' => 'tik',
                    'tahun_pelaksanaan' => '2025',
                ],
                [
                    'dinas_id' => 999999,
                    'judul' => 'Baris tidak valid',
                    'dimensi' => 'Smart Governance',
                    'jenis_keluaran' => 'aplikasi',
                    'kategori' => 'tik',
                    'tahun_pelaksanaan' => '2025',
                ],
            ]));
        } finally {
            $this->assertDatabaseMissing('rencana_aksi', ['judul' => 'Baris valid']);
        }
    }

    public function test_import_ignores_blank_rows_after_the_last_activity(): void
    {
        $admin = $this->adminPusatUser();
        $dinas = Dinas::factory()->create();
        $import = new RencanaAksiImport($admin->id);

        $import->collection(new Collection([
            [
                'dinas_id' => $dinas->id,
                'judul' => 'Kegiatan terakhir',
                'dimensi' => 'Smart Governance',
                'jenis_keluaran' => 'aplikasi',
                'kategori' => 'tik',
                'tahun_pelaksanaan' => '2025',
            ],
            [
                'dinas_id' => null,
                'judul' => null,
                'program' => null,
                'dimensi' => null,
                'jenis_keluaran' => null,
                'kategori' => null,
                'status' => null,
                'perlu_verifikasi' => null,
                'tahun_pelaksanaan' => null,
            ],
        ]));

        $this->assertSame(1, $import->jumlahDiimpor());
        $this->assertDatabaseCount('rencana_aksi', 1);
    }
}
