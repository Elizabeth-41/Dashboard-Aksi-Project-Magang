<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

/**
 * Melihat bukti langsung di tab baru (tanpa mengunduh), dengan hak akses sama
 * seperti unduh dan tanpa membuka celah XSS lewat file unggahan.
 */
class LihatBuktiTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function bukti(string $mime, string $namaFile, string $isi, bool $diterima = true, array $state = []): BuktiDukung
    {
        $factory = BuktiDukung::factory()->state(['tipe_file' => $mime, 'nama_file' => $namaFile, ...$state]);
        $bukti = $diterima ? $factory->diterima()->create() : $factory->create();
        Storage::disk('local')->put($bukti->path_file, $isi);

        return $bukti;
    }

    public function test_pdf_is_downloaded_as_an_attachment_with_its_detected_type(): void
    {
        $bukti = $this->bukti('application/pdf', 'laporan kegiatan.pdf', '%PDF-1.4 isi');

        $response = $this->get("/api/bukti/{$bukti->uuid}/lihat");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('laporan kegiatan.pdf', $response->headers->get('Content-Disposition'));
        // Tanpa "sandbox": penampil PDF Chromium menolak tampil di dokumen sandbox.
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
        $this->assertSame('%PDF-1.4 isi', $response->streamedContent());
    }

    public function test_image_is_shown_inline_inside_a_script_free_sandbox(): void
    {
        $bukti = $this->bukti('image/png', 'foto.png', "\x89PNG\r\n");

        $response = $this->get("/api/bukti/{$bukti->uuid}/lihat");

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
    }

    public function test_image_csp_is_not_overwritten_by_the_page_csp_in_production(): void
    {
        config(['keamanan.csp' => 'enforce']);
        $bukti = $this->bukti('image/png', 'foto.png', "\x89PNG\r\n");

        $csp = $this->get("/api/bukti/{$bukti->uuid}/lihat")->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('sandbox', $csp);
        $this->assertStringNotContainsString('nonce-', $csp);
    }

    public function test_page_csp_is_not_attached_to_pdf_in_production(): void
    {
        config(['keamanan.csp' => 'enforce']);
        $bukti = $this->bukti('application/pdf', 'laporan.pdf', '%PDF-1.4');

        $this->assertFalse($this->get("/api/bukti/{$bukti->uuid}/lihat")->headers->has('Content-Security-Policy'));
    }

    public function test_office_documents_fall_back_to_download(): void
    {
        $bukti = $this->bukti(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'notulen.docx',
            'PK isi dokumen',
        );

        $response = $this->get("/api/bukti/{$bukti->uuid}/lihat")->assertOk();

        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_resource_only_offers_a_view_link_for_pdf_and_images(): void
    {
        $rencana = RencanaAksi::factory()->create();
        $pdf = $this->bukti('application/pdf', 'a.pdf', '%PDF', state: ['rencana_aksi_id' => $rencana->id]);
        $docx = $this->bukti('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'b.docx', 'PK', state: ['rencana_aksi_id' => $rencana->id]);

        $data = collect($this->actingAs($this->unitUser())->getJson("/api/rencana-aksi/{$rencana->uuid}/bukti")->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(route('api.bukti.lihat', ['bukti_dukung' => $pdf, 'nama' => 'a.pdf']), $data[$pdf->id]['url_lihat']);
        $this->assertStringEndsWith('/lihat/a.pdf', $data[$pdf->id]['url_lihat']);
        $this->assertNull($data[$docx->id]['url_lihat']);
        $this->assertNotNull($data[$docx->id]['url_unduh']);
    }

    public function test_viewing_follows_the_same_access_rules_as_downloading(): void
    {
        $menunggu = $this->bukti('application/pdf', 'draf.pdf', '%PDF', diterima: false);
        $pemilik = $this->unitUser($menunggu->rencanaAksi->dinas);

        $this->getJson("/api/bukti/{$menunggu->uuid}/lihat")->assertForbidden();
        $this->actingAs($this->unitUser())->getJson("/api/bukti/{$menunggu->uuid}/lihat")->assertForbidden();
        $this->actingAs($pemilik)->get("/api/bukti/{$menunggu->uuid}/lihat")->assertOk();
        $this->actingAs($this->adminPusatUser())->get("/api/bukti/{$menunggu->uuid}/lihat")->assertOk();
    }

    public function test_filename_segment_is_cosmetic_and_cannot_pick_another_file(): void
    {
        $bukti = $this->bukti('application/pdf', 'laporan resmi.pdf', '%PDF-1.4 benar');

        $sah = $this->get("/api/bukti/{$bukti->uuid}/lihat/laporan%20resmi.pdf")->assertOk();
        $this->assertSame('%PDF-1.4 benar', $sah->streamedContent());

        // Nama lain tetap menyajikan file bukti itu sendiri (segmen diabaikan).
        $this->assertSame('%PDF-1.4 benar', $this->get("/api/bukti/{$bukti->uuid}/lihat/apa-saja.pdf")->streamedContent());

        // Percobaan path traversal bahkan tidak cocok dengan rute.
        $this->get("/api/bukti/{$bukti->uuid}/lihat/..%2F..%2F.env")->assertNotFound();
    }

    public function test_missing_file_returns_404(): void
    {
        $bukti = BuktiDukung::factory()->diterima()->create(['tipe_file' => 'application/pdf']);

        $this->getJson("/api/bukti/{$bukti->uuid}/lihat")->assertNotFound();
    }
}
