<?php

namespace App\Models;

use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'organizer_id', 'property_id', 'program_id',
        'type', 'deposit_percentage', 'balance_due_days_before',
    ];

    protected function casts(): array
    {
        return ['deposit_percentage' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
