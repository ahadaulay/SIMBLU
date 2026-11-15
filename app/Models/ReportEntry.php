<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ReportEntry extends Model
{
    protected $attributes = ['no_activity' => false];

    protected $fillable = ['report_id', 'indicator_id', 'source_entry_id', 'definition', 'required', 'no_activity', 'rows', 'explanation', 'responsible_name'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'rows' => 'array', 'required' => 'boolean', 'no_activity' => 'boolean'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(UnitReport::class, 'report_id');
    }

    public function sourceEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_entry_id');
    }

    public function effectiveRows(array $visited = []): array
    {
        if (! $this->source_entry_id) {
            return $this->rows;
        }

        if (in_array($this->id, $visited, true)) {
            throw new RuntimeException('Rujukan data indikator membentuk lingkaran.');
        }

        $source = $this->relationLoaded('sourceEntry')
            ? $this->getRelation('sourceEntry')
            : $this->sourceEntry()->first();

        return $source?->effectiveRows([...$visited, $this->id]) ?? $this->rows;
    }

    public function effectiveNoActivity(array $visited = []): bool
    {
        if (! $this->source_entry_id) {
            return $this->no_activity;
        }

        if (in_array($this->id, $visited, true)) {
            throw new RuntimeException('Rujukan data indikator membentuk lingkaran.');
        }

        $source = $this->relationLoaded('sourceEntry')
            ? $this->getRelation('sourceEntry')
            : $this->sourceEntry()->first();

        return $source?->effectiveNoActivity([...$visited, $this->id]) ?? $this->no_activity;
    }
}
