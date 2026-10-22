<?php

namespace Tests\Feature\Api;

use App\Models\BuktiDukung;
use App\Models\User;
use App\Notifications\BuktiDitolak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWhitelistedUsers;
use Tests\TestCase;

class NotifikasiControllerTest extends TestCase
{
    use CreatesWhitelistedUsers, RefreshDatabase;

    private function kirimNotifikasi(User $user, int $jumlah = 1): void
    {
        $bukti = BuktiDukung::factory()->ditolak('Contoh alasan')->create();

        foreach (range(1, $jumlah) as $i) {
            $user->notify(new BuktiDitolak($bukti));
        }
    }

    public function test_user_lists_own_notifications_with_unread_count(): void
    {
        $user = $this->unitUser();
        $lain = $this->unitUser();
        $this->kirimNotifikasi($user, 2);
        $this->kirimNotifikasi($lain);

        $this->actingAs($user)->getJson('/api/notifikasi')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.belum_dibaca', 2)
            ->assertJsonPath('data.0.jenis', 'bukti_ditolak')
            ->assertJsonPath('data.0.dibaca', false)
            ->assertJsonPath('data.0.judul', 'Bukti ditolak, mohon unggah ulang');
    }

    public function test_mark_one_and_all_as_read(): void
    {
        $user = $this->unitUser();
        $this->kirimNotifikasi($user, 3);
        $id = $user->notifications()->first()->id;

        $this->actingAs($user)->patchJson("/api/notifikasi/{$id}")
            ->assertOk()
            ->assertJsonPath('data.dibaca', true);
        $this->actingAs($user)->getJson('/api/notifikasi?belum_dibaca=1')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.belum_dibaca', 2);

        $this->actingAs($user)->postJson('/api/notifikasi/baca-semua')
            ->assertOk()
            ->assertJsonPath('message', '2 notifikasi ditandai sudah dibaca.');
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_cannot_touch_another_users_notification(): void
    {
        $user = $this->unitUser();
        $lain = $this->unitUser();
        $this->kirimNotifikasi($lain);
        $id = $lain->notifications()->first()->id;

        $this->actingAs($user)->patchJson("/api/notifikasi/{$id}")->assertNotFound();
        $this->actingAs($user)->deleteJson("/api/notifikasi/{$id}")->assertNotFound();
        $this->assertSame(1, $lain->unreadNotifications()->count());

        $this->actingAs($lain)->deleteJson("/api/notifikasi/{$id}")->assertNoContent();
        $this->assertSame(0, $lain->notifications()->count());
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/notifikasi')->assertUnauthorized();
    }
}
