<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecapMetric extends Model
{
    protected $fillable = ['indicator_id', 'indicator_version', 'code', 'name', 'result_field', 'aggregation', 'denominator_field', 'ratio_scale', 'filter_field', 'filter_values', 'combine_units', 'active'];

    protected function casts(): array
    {
        return ['filter_values' => 'array', 'combine_units' => 'boolean', 'active' => 'boolean'];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function definition(array $source): array
    {
        return array_replace($source, $this->only(['code', 'name', 'result_field', 'aggregation', 'denominator_field', 'ratio_scale']));
    }

    public function rows(array $rows): array
    {
        if (! $this->filter_field) {
            return $rows;
        }

        return array_values(array_filter($rows, fn ($row) => in_array((string) ($row['values'][$this->filter_field] ?? ''), $this->filter_values, true)));
    }
}
