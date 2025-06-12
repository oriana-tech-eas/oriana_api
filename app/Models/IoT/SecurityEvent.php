<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SecurityEvent extends Model
{
    use HasFactory;

    // Use UUID as primary key
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'event_id',        // ADDED: This was missing!
        'event_type',
        'severity',
        'source_ip',
        'domain',
        'category',        // ADDED: This was missing!
        'action',
        'reason',
        'details',
        'occurred_at'      // ADDED: This was missing!
    ];

    protected $casts = [
        'details' => 'array',
        'occurred_at' => 'datetime',  // ADDED: This was missing!
    ];

    // Auto-generate UUID when creating
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    // Relationships
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    // Essential methods only
    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    // Essential scopes
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('occurred_at', '>=', now()->subHours($hours));
    }
}
