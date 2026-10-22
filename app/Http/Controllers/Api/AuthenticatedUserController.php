<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Profil user yang sedang login, untuk inisialisasi SPA.
 */
class AuthenticatedUserController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user()->loadMissing('dinas'));
    }
}
