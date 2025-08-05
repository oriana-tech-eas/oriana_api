<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceRuleOverride extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'family_device_id',
        'family_rule_id',
        'override_type',
        'override_value',
        'reason',
        'expires_at',
        'created_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    // Constants for override types
    const TYPE_ALLOW_DOMAIN = 'allow_domain';
    const TYPE_BLOCK_DOMAIN = 'block_domain';
    const TYPE_EXTEND_TIME = 'extend_time';
    const TYPE_RESTRICT_TIME = 'restrict_time';
    const TYPE_DISABLE_CATEGORY = 'disable_category';
    const TYPE_ENABLE_CATEGORY = 'enable_category';

    // Relationships
    public function familyDevice(): BelongsTo
    {
        return $this->belongsTo(FamilyDevice::class);
    }

    public function familyRule(): BelongsTo
    {
        return $this->belongsTo(FamilyRule::class);
    }

    // Scopes
    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('family_device_id', $deviceId);
    }

    public function scopeForRule($query, $ruleId)
    {
        return $query->where('family_rule_id', $ruleId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('override_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeCreatedBy($query, $createdBy)
    {
        return $query->where('created_by', $createdBy);
    }

    // Business Logic
    public function isActive(): bool
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasExpiration(): bool
    {
        return !is_null($this->expires_at);
    }

    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }

    public function getOverrideDescriptionAttribute(): string
    {
        return match($this->override_type) {
            self::TYPE_ALLOW_DOMAIN => "Allow access to {$this->override_value}",
            self::TYPE_BLOCK_DOMAIN => "Block access to {$this->override_value}",
            self::TYPE_EXTEND_TIME => "Extend time limit by {$this->override_value}",
            self::TYPE_RESTRICT_TIME => "Restrict time limit to {$this->override_value}",
            self::TYPE_DISABLE_CATEGORY => "Disable filtering for {$this->override_value} category",
            self::TYPE_ENABLE_CATEGORY => "Enable filtering for {$this->override_value} category",
            default => "Unknown override type"
        };
    }

    public function getOverrideIconAttribute(): string
    {
        return match($this->override_type) {
            self::TYPE_ALLOW_DOMAIN => 'check-circle',
            self::TYPE_BLOCK_DOMAIN => 'x-circle',
            self::TYPE_EXTEND_TIME => 'clock',
            self::TYPE_RESTRICT_TIME => 'clock',
            self::TYPE_DISABLE_CATEGORY => 'unlock',
            self::TYPE_ENABLE_CATEGORY => 'lock',
            default => 'help-circle'
        };
    }

    public function getOverrideColorAttribute(): string
    {
        return match($this->override_type) {
            self::TYPE_ALLOW_DOMAIN => 'green',
            self::TYPE_BLOCK_DOMAIN => 'red',
            self::TYPE_EXTEND_TIME => 'blue',
            self::TYPE_RESTRICT_TIME => 'orange',
            self::TYPE_DISABLE_CATEGORY => 'yellow',
            self::TYPE_ENABLE_CATEGORY => 'red',
            default => 'gray'
        };
    }

    public function wasCreatedByParent(): bool
    {
        return $this->created_by === 'parent';
    }

    public function wasCreatedByAdmin(): bool
    {
        return $this->created_by === 'admin';
    }

    public function extendExpiration(int $minutes): bool
    {
        if (!$this->expires_at) {
            $this->expires_at = now()->addMinutes($minutes);
        } else {
            $this->expires_at = $this->expires_at->addMinutes($minutes);
        }

        return $this->save();
    }

    public function markAsExpired(): bool
    {
        return $this->update(['expires_at' => now()]);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'override_type' => $this->override_type,
            'override_value' => $this->override_value,
            'override_description' => $this->getOverrideDescriptionAttribute(),
            'override_icon' => $this->getOverrideIconAttribute(),
            'override_color' => $this->getOverrideColorAttribute(),
            'reason' => $this->reason,
            'expires_at' => $this->expires_at,
            'time_remaining' => $this->getTimeRemainingAttribute(),
            'created_by' => $this->created_by,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'has_expiration' => $this->hasExpiration(),
            'device' => [
                'id' => $this->familyDevice->id,
                'name' => $this->familyDevice->name
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}