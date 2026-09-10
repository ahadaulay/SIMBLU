<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingExport extends Model
{
    protected $fillable = ['user_id', 'filters', 'status', 'path'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
