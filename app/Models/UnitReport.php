<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitReport extends Model
{
    protected $attributes = ['status' => 'DRAFT', 'version' => 1];

    protected $fillable = ['unit_id', 'period_id', 'created_by', 'verified_by', 'status', 'version', 'submitted_at', 'verified_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class, 'period_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReportEntry::class, 'report_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class, 'report_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReportHistory::class, 'report_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(VerificationNote::class, 'report_id');
    }
}
