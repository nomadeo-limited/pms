<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, HasUuids, LogsActivity;

    protected $fillable = [
        'organizer_id', 'property_id', 'program_id', 'customer_id',
        'check_in_date', 'check_out_date', 'nights', 'guests',
        'status', 'payment_status', 'total_price', 'currency',
        'discount_id', 'discount_amount', 'notes', 'source', 'external_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'total_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->useLogName('booking');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'booking_units')
            ->withPivot('guests', 'price_per_night');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'booking_add_ons')
            ->withPivot('quantity', 'unit_price', 'total_price');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
