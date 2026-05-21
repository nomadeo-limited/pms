<?php
namespace App\Models;
use App\Tenant\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitHousekeeping extends Model {
    use HasUuids;
    protected $table = 'unit_housekeeping';
    protected $fillable = ['unit_id', 'property_id', 'organizer_id', 'date', 'status', 'notes'];
    protected function casts(): array { return ['date' => 'date']; }
    protected static function booted(): void { static::addGlobalScope(new TenantScope()); }
    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
}
