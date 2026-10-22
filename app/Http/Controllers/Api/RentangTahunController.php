<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\RentangTahun;
use Illuminate\Http\JsonResponse;

/**
 * Rentang tahun kegiatan untuk pilihan filter tahun di SPA — publik.
 */
class RentangTahunController extends Controller
{
    public function __invoke(RentangTahun $rentang): JsonResponse
    {
        return response()->json(['data' => $rentang->hitung()]);
    }
}
