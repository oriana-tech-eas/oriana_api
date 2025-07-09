<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Helpers\FormatHelper;

class DeviceSession extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'family_device_id',
        'started_at',
        'ended_at',
        'data_usage_bytes',
        'is_active'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'data_usage_bytes' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function familyDevice(): BelongsTo
    {
        return $this->belongsTo(FamilyDevice::class);
    }

    public function trafficLogs(): HasMany
    {
        return $this->hasMany(DeviceTrafficLog::class, 'session_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_active', false);
    }

    // Accessors
    public function getDurationAttribute(): ?int
    {
        if (!$this->ended_at) {
            return $this->started_at->diffInMinutes(now());
        }
        
        return $this->started_at->diffInMinutes($this->ended_at);
    }

    public function getFormattedDataUsageAttribute(): string
    {
        return FormatHelper::formatBytes($this->data_usage_bytes ?? 0);
    }


    public function getDurationHumanAttribute(): string
    {
        $minutes = $this->duration;
        
        if ($minutes < 60) {
            return "{$minutes} mins";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        return "{$hours} hrs {$remainingMinutes} mins";
    }

    // Business Logic
    public function end(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return $this->update([
            'ended_at' => now(),
            'is_active' => false
        ]);
    }
}
