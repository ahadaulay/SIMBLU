<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingCycle extends Model
{
    protected $fillable = ['year', 'frequency', 'created_by'];

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(ReportingPeriod::class, 'cycle_id');
    }

    public function getFrequencyLabelAttribute(): string
    {
        return match ($this->frequency) {
            'MONTHLY' => 'Bulanan',
            'SEMESTERLY' => 'Semesteran',
            default => 'Triwulanan',
        };
    }
}
