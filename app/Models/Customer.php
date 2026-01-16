<?php

namespace App\Models;

use App\Models\IoT\Device;
use App\Models\IoT\DeviceMetric;
use App\Models\IoT\DeviceProfile;
use App\Models\IoT\FamilyDevice;
use App\Models\IoT\SecurityEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'keycloak_user_id',
        'subscription_tier',
        'max_devices',
        'status',
        'tax_id',
        'address',
        'phone',
        'settings',
        'trial_ends_at',
        'last_login_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Set primary key type and incrementing for UUIDs
    protected $keyType = 'string';
    public $incrementing = false;

    // Auto-generate UUID when creating
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }


    // Relationships
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function deviceMetrics(): HasManyThrough
    {
        return $this->hasManyThrough(DeviceMetric::class, Device::class);
    }

    public function securityEvents(): HasManyThrough
    {
        return $this->hasManyThrough(SecurityEvent::class, Device::class);
    }

    // Convenience relationship methods
    public function onlineDevices(): HasMany
    {
        return $this->devices()->online();
    }

    public function activeDevices(): HasMany
    {
        return $this->devices()->active();
    }

    public function networkDevices(): HasMany
    {
        return $this->devices()->byType('network');
    }

    public function serverDevices(): HasMany
    {
        return $this->devices()->byType('server');
    }

    // Business Logic Methods
    public function getActiveDevicesCount(): int
    {
        return $this->devices()->where('status', 'active')->count();
    }

    public function canAddDevice(): bool
    {
        return $this->getActiveDevicesCount() < $this->max_devices;
    }

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    public function scopeBySubscriptionTier($query, string $tier)
    {
        return $query->where('subscription_tier', $tier);
    }

    public function scopeTrialExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', 'trial')
                    ->whereBetween('trial_ends_at', [now(), now()->addDays($days)]);
    }

    // IoT Relationships
    public function familyDevices()
    {
        return $this->hasMany(FamilyDevice::class);
    }

    public function deviceProfiles()
    {
        return $this->hasMany(DeviceProfile::class);
    }

    public function identifiedDevices()
    {
        return $this->familyDevices()->where('is_identified', true);
    }

    public function onlineFamilyDevices()
    {
        return $this->familyDevices()->online();
    }

    // Business Logic for IoT
    public function getOnlineFamilyDevicesCountAttribute(): int
    {
        return $this->onlineFamilyDevices()->count();
    }

    public function getTotalFamilyDevicesCountAttribute(): int
    {
        return $this->identifiedDevices()->count();
    }
}
