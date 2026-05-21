<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitBlock extends Model
{
    use HasUuids;

    protected $fillable = [
        'unit_id', 'property_id', 'organizer_id',
        'start_date', 'end_date', 'reason', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'is_active'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
