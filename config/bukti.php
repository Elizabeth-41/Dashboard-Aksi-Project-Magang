<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Penyimpanan Bukti Dukung
    |--------------------------------------------------------------------------
    |
    | File disimpan di disk privat (bukan public/) dan hanya bisa diunduh lewat
    | endpoint yang memeriksa status verifikasi & cakupan user.
    |
    */

    'disk' => env('BUKTI_DISK', 'local'),

    'path' => 'bukti',

    // Ukuran maksimal per file, dalam kilobyte.
    'max_size_kb' => (int) env('BUKTI_MAX_SIZE_KB', 10240),

    /*
    |--------------------------------------------------------------------------
    | Tipe File yang Diizinkan
    |--------------------------------------------------------------------------
    |
    | Divalidasi dari MIME type hasil deteksi isi file (bukan ekstensi), lalu
    | dikelompokkan untuk filter "tipe file" di halaman pencarian.
    |
    */

    'mime_groups' => [
        'pdf' => [
            'application/pdf',
        ],
        'gambar' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'dokumen' => [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ],

];
