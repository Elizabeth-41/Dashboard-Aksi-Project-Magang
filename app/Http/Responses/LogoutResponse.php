<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => 'Logout berhasil'])
            : redirect(Fortify::redirects('logout', '/'));
    }
}
