<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorCategory extends Model
{
    protected $attributes = ['active' => true];

    protected $fillable = ['code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
