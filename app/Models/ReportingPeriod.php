<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingPeriod extends Model
{
    protected $fillable = ['cycle_id', 'year', 'quarter', 'deadline', 'status'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'quarter' => 'integer', 'deadline' => 'date'];
    }

    public function getLabelAttribute(): string
    {
        return match ($this->cycle?->frequency) {
            'MONTHLY' => $this->monthLabel().' '.$this->year,
            'SEMESTERLY' => 'Semester '.$this->quarter.' / '.$this->year,
            default => 'TW '.$this->quarter.' / '.$this->year,
        };
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ReportingCycle::class, 'cycle_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(UnitReport::class, 'period_id');
    }

    private function monthLabel(): string
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$this->quarter] ?? 'Periode '.$this->quarter;
    }
}
