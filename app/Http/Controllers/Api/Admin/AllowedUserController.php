<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\AllowedUsers\InviteAllowedUser;
use App\Actions\AllowedUsers\RevokeAllowedUser;
use App\Actions\AllowedUsers\UpdateAllowedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllowedUserRequest;
use App\Http\Requests\Admin\UpdateAllowedUserRequest;
use App\Http\Resources\AllowedUserResource;
use App\Models\AllowedUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Manajemen Akses (khusus Admin Pusat): whitelist email + role + unit.
 */
class AllowedUserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $allowedUsers = AllowedUser::query()
            ->with(['dinas', 'inviter'])
            ->orderBy('email')
            ->orderBy('id')
            ->get();

        return AllowedUserResource::collection($allowedUsers);
    }

    public function store(StoreAllowedUserRequest $request, InviteAllowedUser $invite): JsonResponse
    {
        $allowed = $invite->handle($request->payload(), $request->user());

        return AllowedUserResource::make($allowed->load(['dinas', 'inviter']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AllowedUser $allowedUser): AllowedUserResource
    {
        return AllowedUserResource::make($allowedUser->load(['dinas', 'inviter']));
    }

    public function update(
        UpdateAllowedUserRequest $request,
        AllowedUser $allowedUser,
        UpdateAllowedUser $update,
    ): AllowedUserResource {
        $allowed = $update->handle($allowedUser, $request->payload());

        return AllowedUserResource::make($allowed->load(['dinas', 'inviter']));
    }

    public function destroy(AllowedUser $allowedUser, RevokeAllowedUser $revoke): JsonResponse
    {
        $revoke->handle($allowedUser);

        return response()->json(status: 204);
    }
}
