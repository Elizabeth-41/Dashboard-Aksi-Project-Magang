<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi milik user yang sedang login (tabel notifications).
 */
class NotifikasiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = $request->validate([
            'belum_dibaca' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();

        $notifikasi = $user->notifications()
            ->when($request->boolean('belum_dibaca'), fn ($q) => $q->whereNull('read_at'))
            ->paginate((int) ($filter['per_page'] ?? 20))
            ->withQueryString();

        return NotificationResource::collection($notifikasi)->additional([
            'meta' => ['belum_dibaca' => $user->unreadNotifications()->count()],
        ]);
    }

    /**
     * Tandai satu notifikasi sudah dibaca. Route binding dibatasi ke milik user sendiri.
     */
    public function update(Request $request, string $id): NotificationResource
    {
        /** @var DatabaseNotification $notifikasi */
        $notifikasi = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notifikasi->markAsRead();

        return NotificationResource::make($notifikasi);
    }

    /**
     * Tandai semua notifikasi sudah dibaca.
     */
    public function bacaSemua(Request $request): JsonResponse
    {
        $jumlah = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => "{$jumlah} notifikasi ditandai sudah dibaca."]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->delete();

        return response()->json(status: 204);
    }
}
