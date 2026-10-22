<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Jawaban "lupa password" yang sama persis, baik email terdaftar maupun tidak,
 * supaya formulir ini tidak bisa dipakai menebak email mana yang punya akun.
 */
class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function __construct(public string $status = '') {}

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => 'Jika email tersebut terdaftar, tautan untuk mengatur ulang password sudah dikirim. Periksa kotak masuk Anda.',
        ]);
    }
}
