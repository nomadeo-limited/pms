<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AvailabilityRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'ruleable_type', 'ruleable_id',
        'rule_type', 'start_date', 'end_date',
        'weekday_mask', 'is_start_date', 'capacity',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_start_date' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function ruleable(): MorphTo
    {
        return $this->morphTo();
    }
}
