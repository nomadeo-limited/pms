<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PricingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'priceable_type', 'priceable_id',
        'name', 'model', 'amount', 'currency',
        'start_date', 'end_date', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
