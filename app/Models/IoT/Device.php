<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Customer;

class Device extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'device_id',
        'device_type',
        'name',
        'status',
        'api_key',
        'last_seen',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_seen' => 'datetime',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(DeviceMetric::class, 'device_id');
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class, 'device_id');
    }

    // Essential Business Logic
    public function generateApiKey(): string
    {
        $this->api_key = 'device_' . Str::random(40);
        $this->save();
        return $this->api_key;
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen) {
            return false;
        }
        
        // Consider device online if seen in last 5 minutes
        return $this->last_seen->diffInMinutes(now()) < 5;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
