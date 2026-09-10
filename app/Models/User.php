<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'email', 'password', 'username', 'role', 'unit_id', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function hasReportingRole(array $roles): bool
    {
        return $this->active && in_array($this->role, $roles, true)
            && ($this->role !== 'UNIT' || ($this->unit_id && $this->unit?->active));
    }
}
