<?php

namespace App\Http\Requests;

use App\Models\RencanaAksi;
use App\Support\BatasUnggah;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreBuktiDukungRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RencanaAksi $rencanaAksi */
        $rencanaAksi = $this->route('rencana_aksi');

        return $this->user()?->can('uploadBukti', $rencanaAksi) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimes = array_merge(...array_values(config('bukti.mime_groups')));

        return [
            // `types()` memeriksa MIME dari isi file, bukan ekstensi.
            'file' => [
                'required',
                File::types($allowedMimes)->max(BatasUnggah::kilobyte()),
            ],
            'deskripsi' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'file bukti',
            'deskripsi' => 'keterangan bukti',
        ];
    }

    /**
     * Setiap penolakan menjelaskan apa yang salah dan cara memperbaikinya.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $batas = BatasUnggah::label();

        return [
            'file.required' => 'Pilih file bukti yang akan diunggah.',
            'file.file' => 'File bukti tidak terbaca. Pilih ulang filenya lalu coba lagi.',
            'file.uploaded' => "File gagal diterima server. Biasanya karena ukurannya melebihi {$batas}; perkecil atau kompres file lalu coba lagi.",
            'file.max' => "Ukuran file melebihi batas {$batas}. Perkecil atau kompres file (mis. PDF hasil pindai) lalu coba lagi.",
            'file.mimetypes' => 'Jenis file tidak diizinkan. Gunakan PDF, gambar (JPG/PNG/WebP), atau dokumen Office (Word/Excel/PowerPoint). File yang sekadar diganti ekstensinya juga ditolak.',
            'deskripsi.required' => 'Keterangan bukti wajib diisi, tidak cukup nama file saja.',
            'deskripsi.string' => 'Keterangan bukti harus berupa teks.',
            'deskripsi.min' => 'Keterangan bukti terlalu singkat: minimal :min karakter. Jelaskan isi bukti, mis. kegiatan dan tanggalnya.',
            'deskripsi.max' => 'Keterangan bukti terlalu panjang: maksimal :max karakter.',
        ];
    }
}
