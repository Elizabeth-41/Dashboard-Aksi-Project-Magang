<?php

namespace Tests\Feature\Admin;

use App\Models\RencanaAksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class ActivityLogControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    public function test_admin_pusat_lists_audit_trail_newest_first_with_filters(): void
    {
        $admin = $this->adminPusatUser();
        $unit = $this->unitUser();
        $rencana = RencanaAksi::factory()->for($unit->dinas)->create();

        $this->actingAs($unit)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'berjalan'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/rencana-aksi/{$rencana->uuid}", ['status' => 'selesai'])->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/admin/activity-log?log_name=rencana_aksi&event=updated');

        $response->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.causer.id', $admin->id)
            ->assertJsonPath('data.0.sebelum.status', 'berjalan')
            ->assertJsonPath('data.0.sesudah.status', 'selesai')
            ->assertJsonPath('data.0.subject_type', 'RencanaAksi')
            ->assertJsonPath('data.0.subject_id', $rencana->id)
            ->assertJsonPath('data.1.causer.id', $unit->id);

        $this->actingAs($admin)->getJson("/api/admin/activity-log?causer={$unit->id}")->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson('/api/admin/activity-log?log_name=manajemen_akses&event=updated')->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson('/api/admin/activity-log?log_name=lainnya')->assertUnprocessable();
    }

    public function test_only_admin_pusat_can_read_audit_trail(): void
    {
        $this->actingAs($this->unitUser())->getJson('/api/admin/activity-log')->assertForbidden();
        $this->actingAs($this->unitUser())->getJson('/api/admin/activity-log')->assertForbidden();
        $this->asGuest()->getJson('/api/admin/activity-log')->assertUnauthorized();
    }
}
