<?php

namespace App\Models\IoT;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceProfile extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'name',
        'description',
        'is_default',
        'default_time_limits'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'default_time_limits' => 'array'
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function familyDevices(): HasMany
    {
        return $this->hasMany(FamilyDevice::class, 'profile_id');
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Business Logic
    public function getDeviceCountAttribute(): int
    {
        return $this->familyDevices()->count();
    }

    public function isAssignedToDevices(): bool
    {
        return $this->familyDevices()->exists();
    }
}
