<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAttachment extends Model
{
    protected $fillable = ['report_id', 'entry_id', 'uploaded_by', 'name', 'path', 'mime_type', 'size'];

    protected function casts(): array
    {
        return [];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(UnitReport::class, 'report_id');
    }
}
