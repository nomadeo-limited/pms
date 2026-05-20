<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Program extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'property_id', 'name', 'type',
        'description', 'duration_days', 'images', 'is_active',
    ];

    protected function casts(): array
    {
        return ['images' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'program_add_ons')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
