<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSpecificRule extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'family_device_id',
        'rule_type',
        'target_value',
        'is_enabled',
        'schedule',
        'custom_settings'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'schedule' => 'array',
        'custom_settings' => 'array'
    ];

    // Constants for rule types
    const TYPE_DOMAIN_BLACKLIST = 'domain_blacklist';
    const TYPE_DOMAIN_WHITELIST = 'domain_whitelist';
    const TYPE_TIME_RESTRICTION = 'time_restriction';
    const TYPE_CATEGORY_BLOCK = 'category_block';

    // Relationships
    public function familyDevice(): BelongsTo
    {
        return $this->belongsTo(FamilyDevice::class);
    }

    // Scopes
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('rule_type', $type);
    }

    // Business Logic
    public function isActiveNow(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // If no schedule, rule is always active
        if (!$this->schedule) {
            return true;
        }

        // Check if current time matches schedule
        return $this->matchesCurrentSchedule();
    }

    private function matchesCurrentSchedule(): bool
    {
        $schedule = $this->schedule;
        $now = now();
        
        // Check day of week
        if (isset($schedule['days'])) {
            $currentDay = strtolower($now->format('l'));
            if (!in_array($currentDay, $schedule['days'])) {
                return false;
            }
        }
        
        // Check time range
        if (isset($schedule['hours'])) {
            $currentTime = $now->format('H:i');
            $startTime = $schedule['hours']['start'] ?? '00:00';
            $endTime = $schedule['hours']['end'] ?? '23:59';
            
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }
        
        return true;
    }
}
