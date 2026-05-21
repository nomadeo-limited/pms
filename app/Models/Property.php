<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id',
        'name',
        'slug',
        'type',
        'address',
        'city',
        'country',
        'timezone',
        'check_in_time',
        'check_out_time',
        'currency',
        'locale',
        'latitude',
        'longitude',
        'description',
        'amenities',
        'images',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'images' => 'array',
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }
}
