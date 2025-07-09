<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Log;

class Device extends Model
{
    use HasFactory, HasUuids;

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

    protected $hidden = [
        'api_key',
    ];

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

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'active')
            ->where('last_seen', '>', now()->subMinutes(5));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function getStatusText(): string
    {
        if (!$this->isOnline()) {
            return 'Offline';
        }
        
        return match($this->status) {
            'active' => 'Online',
            'suspended' => 'Suspended',
            'inactive' => 'Inactive',
            default => 'Unknown'
        };
    }
    
    public function getLastSeenHuman(): string
    {
        if (!$this->last_seen) {
            return 'Never';
        }
        
        return $this->last_seen->diffForHumans();
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen) {
            return false;
        }
        
        // Consider device online if seen in last 5 minutes
        return $this->last_seen->diffInMinutes(now()) < 5;
    }

    public function getLatestMetricByType(string $metricType): ?DeviceMetric
    {
        return $this->metrics()
            ->where('metric_type', $metricType)
            ->orderBy('collected_at', 'desc')
            ->first();
    }

    public function getSystemHealth(): array
    {
        $systemMetric = $this->getLatestMetricByType('system_data');
        
        if (!$systemMetric || !isset($systemMetric->data['cpu'])) {
            return [
                'overall' => 0,
                'cpu' => 0,
                'memory' => 0,
                'disk' => 0,
                'status' => 'no_data'
            ];
        }
        
        $data = $systemMetric->data;
        $cpuHealth = max(0, 100 - ($data['cpu']['usage'] ?? 0));
        $memoryHealth = max(0, 100 - ($data['memory']['usage_percent'] ?? 0));
        $diskHealth = max(0, 100 - ($data['disk']['usage_percent'] ?? 0));
        
        $overall = ($cpuHealth + $memoryHealth + $diskHealth) / 3;
        
        return [
            'overall' => round($overall),
            'cpu' => round($cpuHealth),
            'memory' => round($memoryHealth),
            'disk' => round($diskHealth),
            'status' => $overall > 80 ? 'good' : ($overall > 60 ? 'warning' : 'critical')
        ];
    }

    public function getNetworkSummary(): array
    {
        $networkMetric = $this->getLatestMetricByType('network_data');
        
        if (!$networkMetric || !isset($networkMetric->data['summary'])) {
            return [
                'active_devices' => 0,
                'bandwidth_utilization' => 0,
                'blocked_requests' => 0,
                'status' => 'no_data'
            ];
        }
        
        $summary = $networkMetric->data['summary'];
        
        return [
            'active_devices' => $summary['active_devices'] ?? 0,
            'bandwidth_utilization' => $summary['bandwidth_utilization'] ?? 0,
            'blocked_requests' => $summary['blocked_requests'] ?? 0,
            'total_devices' => $summary['total_devices'] ?? count($networkMetric->data['devices'] ?? []),
            'status' => 'active'
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public static function findForCustomer($deviceId, $customerId): ?self
    {
        return static::where('id', $deviceId)
            ->where('customer_id', $customerId)
            ->first();
    }

    public function belongsToCustomer($customerId): bool
    {
        return $this->customer_id === $customerId;
    }

    public function recordActivity(string $activity, array $details = []): void
    {
        Log::info("Device activity: {$activity}", [
            'device_id' => $this->id,
            'device_name' => $this->name,
            'customer_id' => $this->customer_id,
            'details' => $details
        ]);
        
        // You could also create a DeviceActivity model to store these
    }

    public function updateLastSeen(): void
    {
        $this->update(['last_seen' => now()]);
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'name' => $this->name,
            'type' => $this->device_type,
            'status' => $this->status,
            'status_text' => $this->getStatusText(),
            'last_seen' => $this->last_seen,
            'last_seen_human' => $this->getLastSeenHuman(),
            'metadata' => $this->metadata,
            'is_online' => $this->isOnline(),
            'health' => $this->getSystemHealth(),
            'network_summary' => $this->device_type === 'network' ? $this->getNetworkSummary() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
