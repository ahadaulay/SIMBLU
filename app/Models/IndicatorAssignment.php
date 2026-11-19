<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorAssignment extends Model
{
    protected $fillable = ['period_id', 'unit_id', 'indicator_id', 'responsible_user_id', 'required', 'assigned_by', 'definition'];

    protected static function booted(): void
    {
        static::saving(function (self $assignment): void {
            if ($assignment->definition === null || ($assignment->exists && $assignment->isDirty('indicator_id'))) {
                $assignment->definition = Indicator::with('category')->findOrFail($assignment->indicator_id)->definition();
            }
        });
    }

    protected function casts(): array
    {
        return ['required' => 'boolean', 'definition' => 'array'];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
