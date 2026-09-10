<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEntry extends Model
{
    protected $attributes = ['no_activity' => false];

    protected $fillable = ['report_id', 'indicator_id', 'definition', 'required', 'no_activity', 'rows', 'explanation', 'responsible_name'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'rows' => 'array', 'required' => 'boolean', 'no_activity' => 'boolean'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(UnitReport::class, 'report_id');
    }
}
