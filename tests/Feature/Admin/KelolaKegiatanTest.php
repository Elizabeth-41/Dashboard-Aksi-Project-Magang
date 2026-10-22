<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Halaman Kelola Kegiatan: pencarian per unit, centang verifikasi massal, dan
 * rentang tahun yang mengikuti data.
 */
class KelolaKegiatanTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_search_keyword_also_matches_unit_name(): void
    {
        $dlh = Dinas::factory()->create(['nama' => 'Dinas Lingkungan Hidup']);
        $dinkes = Dinas::factory()->create(['nama' => 'Dinas Kesehatan']);
        RencanaAksi::factory()->for($dlh)->create(['judul' => 'Bank sampah digital', 'program' => null]);
        RencanaAksi::factory()->for($dinkes)->create(['judul' => 'Aplikasi e-Puskesmas', 'program' => null]);

        $response = $this->getJson('/api/rencana-aksi?q=Lingkungan');

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.judul', 'Bank sampah digital');
    }

    public function test_unit_filter_narrows_the_list(): void
    {
        $dlh = Dinas::factory()->create();
        RencanaAksi::factory()->for($dlh)->count(2)->create();
        RencanaAksi::factory()->count(3)->create();

        $this->getJson("/api/rencana-aksi?dinas={$dlh->slug}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_list_reports_verification_counts_across_all_pages(): void
    {
        RencanaAksi::factory()->count(30)->create();
        RencanaAksi::factory()->tanpaVerifikasi()->count(4)->create();

        // Hitungan meliputi seluruh 34 kegiatan, bukan hanya 25 di halaman pertama.
        $this->getJson('/api/rencana-aksi')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 34)
            ->assertJsonPath('meta.verifikasi.wajib', 30)
            ->assertJsonPath('meta.verifikasi.tanpa', 4);
    }

    public function test_bulk_switch_only_touches_kegiatan_matching_the_filter(): void
    {
        $admin = $this->adminPusatUser();
        $dlh = Dinas::factory()->create();
        $milikDlh = RencanaAksi::factory()->for($dlh)->count(3)->create();
        $lainnya = RencanaAksi::factory()->count(2)->create();
        BuktiDukung::factory()->for($milikDlh->first())->count(2)->create();
        $ditolak = BuktiDukung::factory()->for($milikDlh->first())->ditolak('Tidak sesuai')->create();

        $this->actingAs($admin)->patchJson('/api/admin/rencana-aksi/verifikasi-massal', [
            'dinas' => $dlh->slug,
            'perlu_verifikasi' => false,
        ])
            ->assertOk()
            ->assertJsonPath('meta.kegiatan_diubah', 3)
            ->assertJsonPath('meta.bukti_dilepas', 2)
            ->assertJsonPath('message', '3 kegiatan kini tidak perlu verifikasi. 2 bukti yang masih menunggu langsung diterima.');

        $milikDlh->each(fn (RencanaAksi $r) => $this->assertFalse($r->fresh()->perlu_verifikasi));
        $lainnya->each(fn (RencanaAksi $r) => $this->assertTrue($r->fresh()->perlu_verifikasi, 'Di luar filter tidak boleh ikut berubah.'));
        $this->assertSame(StatusVerifikasi::Ditolak, $ditolak->fresh()->status_verifikasi);
    }

    public function test_bulk_switch_back_on_and_no_op_message(): void
    {
        $admin = $this->adminPusatUser();
        RencanaAksi::factory()->tanpaVerifikasi()->count(2)->create();

        $this->actingAs($admin)->patchJson('/api/admin/rencana-aksi/verifikasi-massal', ['perlu_verifikasi' => true])
            ->assertOk()
            ->assertJsonPath('meta.kegiatan_diubah', 2)
            ->assertJsonPath('message', '2 kegiatan kini wajib diverifikasi.');

        $this->actingAs($admin)->patchJson('/api/admin/rencana-aksi/verifikasi-massal', ['perlu_verifikasi' => true])
            ->assertOk()
            ->assertJsonPath('meta.kegiatan_diubah', 0);
    }

    public function test_bulk_switch_respects_keyword_filter(): void
    {
        $admin = $this->adminPusatUser();
        $cocok = RencanaAksi::factory()->create(['judul' => 'Portal Smart City', 'program' => null]);
        $tidak = RencanaAksi::factory()->create(['judul' => 'Bank sampah', 'program' => null]);

        $this->actingAs($admin)->patchJson('/api/admin/rencana-aksi/verifikasi-massal', [
            'q' => 'Portal',
            'perlu_verifikasi' => false,
        ])->assertOk()->assertJsonPath('meta.kegiatan_diubah', 1);

        $this->assertFalse($cocok->fresh()->perlu_verifikasi);
        $this->assertTrue($tidak->fresh()->perlu_verifikasi);
    }

    public function test_each_bulk_change_is_recorded_in_audit_trail(): void
    {
        $admin = $this->adminPusatUser();
        RencanaAksi::factory()->count(3)->create();

        $this->actingAs($admin)->patchJson('/api/admin/rencana-aksi/verifikasi-massal', ['perlu_verifikasi' => false])->assertOk();

        $this->assertSame(3, Activity::query()
            ->where('log_name', 'rencana_aksi')
            ->where('description', 'like', 'Menetapkan bukti kegiatan%tanpa verifikasi')
            ->where('causer_id', $admin->id)
            ->count());
    }

    public function test_bulk_switch_is_admin_pusat_only_and_validated(): void
    {
        $this->actingAs($this->unitUser())
            ->patchJson('/api/admin/rencana-aksi/verifikasi-massal', ['perlu_verifikasi' => false])
            ->assertForbidden();

        $this->actingAs($this->adminPusatUser())
            ->patchJson('/api/admin/rencana-aksi/verifikasi-massal', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['perlu_verifikasi']);

        $this->assertSame(0, RencanaAksi::query()->where('perlu_verifikasi', false)->count());
    }

    public function test_single_update_route_still_resolves_numeric_ids(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($this->adminPusatUser())
            ->patchJson("/api/admin/rencana-aksi/{$rencana->uuid}", ['judul' => 'Judul baru'])
            ->assertOk()
            ->assertJsonPath('data.judul', 'Judul baru');
    }

    public function test_year_range_follows_data_including_future_periods(): void
    {
        RencanaAksi::factory()->tahun([2019, 2020])->create();
        RencanaAksi::factory()->tahun([2027, 2028, 2029, 2030, 2031])->create();

        $this->getJson('/api/rentang-tahun')
            ->assertOk()
            ->assertJsonPath('data.awal', 2019)
            ->assertJsonPath('data.akhir', 2031);
    }

    public function test_report_per_year_extends_to_future_periods(): void
    {
        RencanaAksi::factory()->tahun([2027, 2028, 2029, 2030, 2031])->create();

        $response = $this->actingAs($this->unitUser())->getJson('/api/laporan')->assertOk();

        $tahun = collect($response->json('data.per_tahun'))->pluck('total', 'tahun');
        $this->assertSame(1, $tahun[2031]);
        $this->assertSame(0, $tahun[2019]);
    }

    public function test_five_year_period_gets_a_range_label(): void
    {
        $dinas = Dinas::factory()->create();

        $this->actingAs($this->adminPusatUser())->postJson('/api/admin/rencana-aksi', [
            'dinas_id' => $dinas->id,
            'judul' => 'Kegiatan periode baru',
            'jenis_keluaran' => 'aplikasi',
            'kategori' => 'tik',
            'dimensi' => 'Smart Governance',
            'tahun_pelaksanaan' => [2027, 2028, 2029, 2030, 2031],
        ])
            ->assertCreated()
            ->assertJsonPath('data.tahun_label', '2027 - 2031');
    }
}
