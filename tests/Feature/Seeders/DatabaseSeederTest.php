<?php

namespace Tests\Feature\Seeders;

use App\Enums\Dimensi;
use App\Enums\JenisKeluaran;
use App\Enums\Kategori;
use App\Enums\StatusKegiatan;
use App\Enums\UserRole;
use App\Models\AllowedUser;
use App\Models\Dinas;
use App\Models\RencanaAksi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_39_units_and_156_rencana_aksi(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(39, Dinas::count());
        $this->assertSame(156, RencanaAksi::count());
        $this->assertSame(156, RencanaAksi::denganStatus(StatusKegiatan::BelumMulai)->count());
    }

    public function test_seeded_rows_match_source_columns(): void
    {
        $this->seed(DatabaseSeeder::class);

        $rencana = RencanaAksi::query()
            ->where('judul', 'Lomba inovasi energi dari pengolahan limbah dan sampah')
            ->with('dinas')
            ->sole();

        $this->assertSame('dlh', $rencana->dinas->slug);
        $this->assertSame('DLH', $rencana->dinas->nama);
        $this->assertSame(Dimensi::Environment, $rencana->dimensi);
        $this->assertSame(JenisKeluaran::Infrastruktur, $rencana->jenis_keluaran);
        $this->assertSame(Kategori::NonTik, $rencana->kategori);
        $this->assertSame('2023 & 2025', $rencana->tahun_label);
        $this->assertSame([2023, 2025], $rencana->tahun_pelaksanaan);
        $this->assertStringStartsWith('Mengembangkan gerakan Banjarmasin Mandiri Energi', $rencana->program);

        $tanpaProgram = RencanaAksi::query()->where('judul', 'Pembentukan Forum Smart City')->sole();
        $this->assertNull($tanpaProgram->program);
        $this->assertSame([2019], $tanpaProgram->tahun_pelaksanaan);

        $this->assertSame('Setda, Bag Hukum', Dinas::query()->where('slug', 'setda-bag-hukum')->sole()->nama);
        $this->assertSame(78, RencanaAksi::where('kategori', Kategori::Tik)->count());
    }

    public function test_seeds_initial_admin_pusat_that_can_log_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('role', UserRole::AdminPusat)->sole();
        $diskominfotik = Dinas::query()->where('slug', 'diskominfotik')->sole();
        $this->assertSame($diskominfotik->id, $admin->dinas_id, 'Semua akun wajib terikat unit; Admin Pusat dari Diskominfotik.');
        $this->assertSame($diskominfotik->id, AllowedUser::query()->where('email', $admin->email)->value('dinas_id'));
        $this->assertNull($admin->cakupanDinasIds(), 'Cakupannya tetap seluruh unit.');
        $this->assertTrue(AllowedUser::aktif()->where('email', $admin->email)->exists());

        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => (string) env('ADMIN_PUSAT_PASSWORD'),
        ])->assertOk();
    }

    public function test_seeding_twice_does_not_duplicate_rows(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(39, Dinas::count());
        $this->assertSame(156, RencanaAksi::count());
        $this->assertSame(1, User::count());
        $this->assertSame(1, AllowedUser::count());
    }
}
