<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit trail (khusus Admin Pusat): siapa, aksi apa, kapan, data sebelum/sesudah.
 */
class ActivityLogController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filter = $request->validate([
            'log_name' => ['nullable', Rule::in(['rencana_aksi', 'bukti_dukung', 'manajemen_akses'])],
            'event' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'causer' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'subject_id' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $activities = Activity::query()
            ->with('causer')
            ->when($filter['log_name'] ?? null, fn (Builder $q, string $log) => $q->inLog($log))
            ->when($filter['event'] ?? null, fn (Builder $q, string $event) => $q->forEvent($event))
            ->when($filter['causer'] ?? null, fn (Builder $q, int $causer) => $q->where('causer_id', $causer))
            ->when($filter['subject_id'] ?? null, fn (Builder $q, int $subject) => $q->where('subject_id', $subject))
            ->when($filter['dari'] ?? null, fn (Builder $q, string $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($filter['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->latest()
            ->orderByDesc('id')
            ->paginate((int) ($filter['per_page'] ?? 50))
            ->withQueryString();

        return ActivityResource::collection($activities);
    }
}
