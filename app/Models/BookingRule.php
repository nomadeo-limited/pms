<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'property_id', 'program_id',
        'min_nights', 'max_nights',
        'check_in_days', 'check_out_days',
        'min_advance_days', 'max_advance_days',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
