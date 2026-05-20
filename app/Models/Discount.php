<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'code', 'type', 'value', 'currency',
        'min_nights', 'max_uses', 'uses_count',
        'valid_from', 'valid_until', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
