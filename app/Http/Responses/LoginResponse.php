<?php

namespace App\Http\Responses;

use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if (! $request->wantsJson()) {
            return redirect()->intended(Fortify::redirects('login'));
        }

        return new JsonResponse([
            'message' => 'Login Berhasil',
            'two_factor' => false,
            'user' => UserResource::make($request->user()->loadMissing('dinas'))->resolve($request),
        ]);
    }
}
