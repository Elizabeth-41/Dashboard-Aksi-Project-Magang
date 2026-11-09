<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickWin extends Model
{
    protected $fillable = ['rencana_aksi_id', 'tahun', 'created_by'];

    protected function casts(): array
    {
        return ['tahun' => 'integer'];
    }

    public function rencanaAksi(): BelongsTo
    {
        return $this->belongsTo(RencanaAksi::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
