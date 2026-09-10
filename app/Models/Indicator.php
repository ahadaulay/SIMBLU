<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Indicator extends Model
{
    protected $attributes = ['version' => 1, 'active' => true, 'requires_attachment' => false, 'requires_explanation' => false, 'ratio_scale' => 1, 'form_type' => 'SINGLE', 'aggregation' => 'SUM', 'direction' => 'NEUTRAL'];

    protected $fillable = ['category_id', 'code', 'name', 'description', 'form_type', 'aggregation', 'direction', 'result_field', 'denominator_field', 'ratio_scale', 'requires_attachment', 'requires_explanation', 'active', 'version', 'fields', 'items', 'created_by'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'items' => 'array', 'active' => 'boolean', 'requires_attachment' => 'boolean', 'requires_explanation' => 'boolean', 'ratio_scale' => 'float'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IndicatorCategory::class, 'category_id');
    }

    public function definition(): array
    {
        return array_merge($this->only(['code', 'name', 'description', 'version', 'form_type', 'aggregation', 'direction', 'result_field', 'denominator_field', 'ratio_scale', 'requires_attachment', 'requires_explanation', 'fields', 'items']), ['category' => $this->category->name]);
    }
}
