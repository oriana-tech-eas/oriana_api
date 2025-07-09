<?php

namespace App\Models\IoT;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Helpers\FormatHelper;
use Illuminate\Support\Facades\DB;

class FamilyDevice extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'mac_address',
        'name',
        'avatar',
        'device_model',
        'device_type',
        'manufacturer',
        'current_ip',
        'is_active',
        'is_identified',
        'profile_id',
        'first_seen',
        'last_seen',
        'connection_started_at',
        'total_data_usage_bytes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_identified' => 'boolean',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'connection_started_at' => 'datetime',
        'total_data_usage_bytes' => 'integer'
    ];

    protected $appends = [
        'status',
        'time_connected',
        'data_usage',
        'sites_blocked',
        'sites_allowed'
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DeviceProfile::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class);
    }

    public function currentSession(): HasOne
    {
        return $this->hasOne(DeviceSession::class)->where('is_active', true);
    }

    public function trafficLogs(): HasMany
    {
        return $this->hasMany(DeviceTrafficLog::class);
    }

    public function specificRules(): HasMany
    {
        return $this->hasMany(DeviceSpecificRule::class);
    }

    public function activeRules(): HasMany
    {
        return $this->specificRules()->where('is_enabled', true);
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeIdentified($query)
    {
        return $query->where('is_identified', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('is_active', true)
                    ->where('last_seen', '>=', now()->subMinutes(5));
    }

    // Accessors (Computed Properties)
    public function getStatusAttribute(): string
    {
        if (!$this->is_active || !$this->last_seen) {
            return 'offline';
        }
        
        // If last seen within 5 minutes, consider online
        return $this->last_seen->diffInMinutes(now()) <= 5 ? 'online' : 'offline';
    }

    public function getTimeConnectedAttribute(): string
    {
        if (!$this->currentSession || $this->status === 'offline') {
            return '0 mins';
        }
        
        $session = $this->currentSession;
        return $session->started_at->diffForHumans(now(), true);
    }

    public function getDataUsageAttribute(): string
    {
        $todayUsage = $this->trafficLogs()
            ->whereDate('recorded_at', today())
            ->sum(DB::raw('bytes_downloaded + bytes_uploaded'));
            
        return FormatHelper::formatBytes($todayUsage);
    }

    public function getSitesBlockedAttribute(): array
    {
        return $this->activeRules()
            ->where('rule_type', 'domain_blacklist')
            ->pluck('target_value')
            ->toArray();
    }

    public function getSitesAllowedAttribute(): array
    {
        return $this->activeRules()
            ->where('rule_type', 'domain_whitelist')
            ->pluck('target_value')
            ->toArray();
    }

    // Business Logic Methods
    public function startNewSession(): DeviceSession
    {
        // End any existing active session
        $this->sessions()->where('is_active', true)->update([
            'is_active' => false,
            'ended_at' => now()
        ]);

        // Create new session
        $session = $this->sessions()->create([
            'started_at' => now(),
            'is_active' => true
        ]);

        // Update device connection time
        $this->update(['connection_started_at' => now()]);

        return $session;
    }

    public function endCurrentSession(): bool
    {
        $session = $this->currentSession;
        if (!$session) {
            return false;
        }

        $session->update([
            'is_active' => false,
            'ended_at' => now()
        ]);

        $this->update(['connection_started_at' => null]);

        return true;
    }

    public function updateTrafficData(int $bytesDownloaded, int $bytesUploaded): DeviceTrafficLog
    {
        // Create traffic log entry
        $trafficLog = $this->trafficLogs()->create([
            'recorded_at' => now(),
            'bytes_downloaded' => $bytesDownloaded,
            'bytes_uploaded' => $bytesUploaded,
            'session_id' => $this->currentSession?->id
        ]);

        // Update current session data usage
        if ($this->currentSession) {
            $this->currentSession->increment('data_usage_bytes', $bytesDownloaded + $bytesUploaded);
        }

        // Update total device data usage
        $this->increment('total_data_usage_bytes', $bytesDownloaded + $bytesUploaded);

        return $trafficLog;
    }

    public function identify(string $name, string $avatar = 'penguin', ?string $profileId = null): bool
    {
        return $this->update([
            'name' => $name,
            'avatar' => $avatar,
            'profile_id' => $profileId,
            'is_identified' => true
        ]);
    }

    public function getDisplayDeviceAttribute(): string
    {
        if ($this->device_model) {
            return $this->device_model;
        }

        $manufacturer = $this->manufacturer ?: 'Unknown';
        $type = ucfirst($this->device_type);
        
        return "{$manufacturer} {$type}";
    }
}
