<?php

namespace Tests\Feature\Api;

use App\Enums\StatusVerifikasi;
use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Support\BatasUnggah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class RencanaAksiBuktiControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_cannot_open_bukti_list(): void
    {
        $rencana = RencanaAksi::factory()->create();
        $diterima = BuktiDukung::factory()->for($rencana)->diterima()->create();
        BuktiDukung::factory()->for($rencana)->create();
        BuktiDukung::factory()->for($rencana)->ditolak()->create();

        $this->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertUnauthorized();
    }

    public function test_owner_unit_sees_all_statuses_but_other_unit_only_diterima(): void
    {
        $pemilik = $this->unitUser();
        $lain = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($pemilik->dinas)->create();
        BuktiDukung::factory()->for($rencana)->diterima()->create();
        BuktiDukung::factory()->for($rencana)->create();

        $this->actingAs($pemilik)->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonCount(2, 'data');
        $this->actingAs($lain)->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonCount(1, 'data');
        $this->actingAs($this->adminPusatUser())->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonCount(2, 'data');
    }

    public function test_unit_uploads_bukti_with_deskripsi_and_it_waits_for_verification(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();
        $file = UploadedFile::fake()->create('laporan-kegiatan.pdf', 512, 'application/pdf');

        $response = $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => $file,
            'deskripsi' => 'Laporan pelaksanaan kegiatan triwulan pertama.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.nama_file', 'laporan-kegiatan.pdf')
            ->assertJsonPath('data.tipe_file', 'application/pdf')
            ->assertJsonPath('data.kelompok_tipe', 'pdf')
            ->assertJsonPath('data.status_verifikasi', 'menunggu')
            ->assertJsonPath('data.uploaded_by', $user->id)
            ->assertJsonPath('message', 'Bukti berhasil diunggah dan menunggu verifikasi admin.');

        $bukti = BuktiDukung::query()->sole();
        $this->assertSame(StatusVerifikasi::Menunggu, $bukti->status_verifikasi);
        $this->assertStringStartsWith("bukti/{$rencana->id}/", $bukti->path_file);
        $this->assertStringEndsWith('.pdf', $bukti->path_file);
        $this->assertStringNotContainsString('laporan-kegiatan', $bukti->path_file);
        Storage::disk('local')->assertExists($bukti->path_file);
    }

    public function test_multiple_bukti_can_be_uploaded_to_the_same_rencana_aksi(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        foreach (['a.pdf', 'b.pdf'] as $nama) {
            $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
                'file' => UploadedFile::fake()->create($nama, 10, 'application/pdf'),
                'deskripsi' => "Dokumen pendukung {$nama}",
            ])->assertCreated();
        }

        $this->assertSame(2, $rencana->buktiDukung()->count());
    }

    public function test_upload_without_deskripsi_is_rejected(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['deskripsi']);

        $this->assertSame(0, BuktiDukung::count());
    }

    /**
     * UploadedFile::fake() menebak MIME dari nama file, jadi untuk menguji
     * deteksi isi file dipakai UploadedFile sungguhan dari file temporer.
     */
    private function fileSungguhan(string $namaKlien, string $isi): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bukti-test-');
        file_put_contents($path, $isi);

        return new UploadedFile($path, $namaKlien, null, null, true);
    }

    public function test_mime_type_is_detected_from_content_not_extension(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $pdfAsli = $this->fileSungguhan('laporan.bin', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");
        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => $pdfAsli,
            'deskripsi' => 'PDF asli dengan ekstensi aneh tetap diterima',
        ])->assertCreated()->assertJsonPath('data.tipe_file', 'application/pdf');

        $exeBerkedokPdf = $this->fileSungguhan('virus.pdf', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00".str_repeat("\x00", 64));
        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => $exeBerkedokPdf,
            'deskripsi' => 'File berbahaya berkedok pdf',
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->assertSame(1, BuktiDukung::count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $besar = UploadedFile::fake()->create('besar.pdf', config('bukti.max_size_kb') + 1, 'application/pdf');
        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => $besar,
            'deskripsi' => 'File terlalu besar untuk diunggah',
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);

        $this->assertSame(0, BuktiDukung::count());
    }

    public function test_unit_cannot_upload_to_another_units_rencana_aksi(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->create();

        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Mencoba unggah ke unit lain',
        ])->assertForbidden();
    }

    public function test_guest_cannot_upload(): void
    {
        $rencana = RencanaAksi::factory()->create();

        $this->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf'),
            'deskripsi' => 'Tamu mencoba unggah bukti',
        ])->assertUnauthorized();
    }

    public function test_rejections_explain_what_is_wrong_and_how_to_fix_it(): void
    {
        config(['bukti.max_size_kb' => 1024]);
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => UploadedFile::fake()->create('scan.pdf', 1025, 'application/pdf'),
            'deskripsi' => 'Pendek',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Ukuran file melebihi batas 1 MB. Perkecil atau kompres file (mis. PDF hasil pindai) lalu coba lagi.')
            ->assertJsonPath('errors.deskripsi.0', 'Keterangan bukti terlalu singkat: minimal 10 karakter. Jelaskan isi bukti, mis. kegiatan dan tanggalnya.');

        $this->actingAs($user)->postJson("/api/rencana-aksi/{$rencana->uuid}/bukti", [
            'file' => $this->fileSungguhan('program.pdf', "MZ\x90\x00".str_repeat("\x00", 64)),
            'deskripsi' => 'File yang jenisnya tidak diizinkan',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', fn (string $pesan) => str_starts_with($pesan, 'Jenis file tidak diizinkan.'));
    }

    public function test_request_above_the_php_post_limit_gets_a_clear_reason_instead_of_a_bare_413(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();

        $this->actingAs($user)
            ->call('POST', "/api/rencana-aksi/{$rencana->uuid}/bukti", server: [
                'CONTENT_LENGTH' => (string) (1024 * 1024 * 1024),
                'HTTP_ACCEPT' => 'application/json',
            ])
            ->assertStatus(413)
            ->assertJsonPath('errors.file.0', 'Ukuran file melebihi batas '.BatasUnggah::label().'. Perkecil atau kompres file lalu coba lagi.');
    }

    public function test_upload_limit_is_the_smaller_of_the_app_setting_and_the_php_limit(): void
    {
        $user = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($user->dinas)->create();
        $batasPhpKb = (int) floor(UploadedFile::getMaxFilesize() / 1024);

        config(['bukti.max_size_kb' => 512]);
        $this->actingAs($user)->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonPath('meta.batas_unggah_kb', 512);
        $this->assertSame('0,5 MB', BatasUnggah::label());

        config(['bukti.max_size_kb' => $batasPhpKb * 10]);
        $this->actingAs($user)->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertJsonPath('meta.batas_unggah_kb', $batasPhpKb);
    }
}
