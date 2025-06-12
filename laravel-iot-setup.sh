#!/bin/bash
# laravel-iot-setup.sh - Complete Laravel IoT module setup
# Run this from your oriana_api directory

set -e

echo "🚀 Setting up Laravel IoT Module"
echo "================================"

# Check if we're in Laravel directory
if [ ! -f "artisan" ]; then
    echo "❌ Not in Laravel directory. Please run from oriana_api folder"
    exit 1
fi

echo "📦 Installing required packages..."
composer require pusher/pusher-php-server
composer require laravel/sanctum
composer require beyondcode/laravel-websockets --dev

echo "📁 Creating directory structure..."
mkdir -p app/Http/Controllers/IoT
mkdir -p app/Models/IoT
mkdir -p app/Services/IoT
mkdir -p database/migrations
mkdir -p resources/views/iot

echo "🗄️ Creating database migrations..."

# 1. Enhance customers table
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_enhance_customers_for_iot.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('keycloak_user_id')->nullable()->unique()->after('email');
            $table->enum('subscription_tier', ['starter', 'professional', 'enterprise'])
                  ->default('starter')->after('keycloak_user_id');
            $table->integer('max_devices')->default(5)->after('subscription_tier');
            $table->enum('status', ['active', 'suspended', 'trial'])
                  ->default('trial')->after('max_devices');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['keycloak_user_id', 'subscription_tier', 'max_devices', 'status']);
        });
    }
};
EOF

# 2. Create devices table
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_devices_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('device_id')->unique();
            $table->enum('device_type', ['network', 'server', 'hybrid']);
            $table->string('name');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('api_key')->unique();
            $table->timestamp('last_seen')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('last_seen');
        });
    }

    public function down()
    {
        Schema::dropIfExists('devices');
    }
};
EOF

# 3. Create device_metrics table
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_device_metrics_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('devices')->cascadeOnDelete();
            $table->enum('metric_type', ['system', 'network', 'security']);
            $table->json('data');
            $table->timestamp('collected_at');
            $table->timestamps();
            
            $table->index(['device_id', 'metric_type']);
            $table->index('collected_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_metrics');
    }
};
EOF

# 4. Create security_events table
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_security_events_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->ipAddress('source_ip')->nullable();
            $table->string('domain')->nullable();
            $table->string('action', 100);
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            
            $table->index(['device_id', 'severity']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('security_events');
    }
};
EOF

# 5. Create device_registrations table
cat > database/migrations/$(date +%Y_%m_%d_%H%M%S)_create_device_registrations_table.php << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('device_id');
            $table->enum('device_type', ['network', 'server', 'hybrid']);
            $table->string('name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_registrations');
    }
};
EOF

echo "🏗️ Creating Eloquent models..."

# Customer model enhancement
cat > app/Models/Customer.php << 'EOF'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email', 
        'keycloak_user_id',
        'subscription_tier',
        'max_devices',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }

    public function getActiveDevicesCount(): int
    {
        return $this->devices()->where('status', 'active')->count();
    }

    public function canAddDevice(): bool
    {
        return $this->getActiveDevicesCount() < $this->max_devices;
    }

    public function getSubscriptionLimits(): array
    {
        return match($this->subscription_tier) {
            'starter' => ['max_devices' => 5, 'data_retention_days' => 30],
            'professional' => ['max_devices' => 25, 'data_retention_days' => 90],
            'enterprise' => ['max_devices' => 100, 'data_retention_days' => 365],
            default => ['max_devices' => 5, 'data_retention_days' => 30]
        };
    }
}
EOF

# Device model
cat > app/Models/IoT/Device.php << 'EOF'
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(DeviceMetric::class);
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function generateApiKey(): string
    {
        $this->api_key = 'device_' . Str::random(40);
        $this->save();
        return $this->api_key;
    }

    public function getLatestMetrics(string $type = null, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->metrics()->latest('collected_at');
        
        if ($type) {
            $query->where('metric_type', $type);
        }
        
        return $query->limit($limit)->get();
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen) {
            return false;
        }
        
        return $this->last_seen->diffInMinutes(now()) < 5; // Online if seen in last 5 minutes
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'active' => $this->isOnline() ? 'green' : 'yellow',
            'inactive' => 'gray',
            'suspended' => 'red',
            default => 'gray'
        };
    }
}
EOF

# DeviceMetric model
cat > app/Models/IoT/DeviceMetric.php << 'EOF'
<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceMetric extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'metric_type',
        'data',
        'collected_at'
    ];

    protected $casts = [
        'data' => 'array',
        'collected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function getFormattedData(): array
    {
        return match($this->metric_type) {
            'system' => $this->formatSystemData(),
            'network' => $this->formatNetworkData(),
            'security' => $this->formatSecurityData(),
            default => $this->data
        };
    }

    private function formatSystemData(): array
    {
        $data = $this->data;
        return [
            'cpu_usage' => $data['cpu']['usage'] ?? 0,
            'memory_usage' => $data['memory']['usage_percent'] ?? 0,
            'disk_usage' => $data['disk']['usage_percent'] ?? 0,
            'uptime' => $data['system']['uptime'] ?? 0,
            'temperature' => $data['cpu']['temperature'] ?? null,
        ];
    }

    private function formatNetworkData(): array
    {
        $data = $this->data;
        return [
            'active_devices' => $data['summary']['active_devices'] ?? 0,
            'bandwidth_utilization' => $data['summary']['bandwidth_utilization'] ?? 0,
            'blocked_requests' => $data['summary']['blocked_requests'] ?? 0,
            'total_upload' => $data['bandwidth']['total_upload'] ?? 0,
            'total_download' => $data['bandwidth']['total_download'] ?? 0,
        ];
    }

    private function formatSecurityData(): array
    {
        return $this->data;
    }
}
EOF

# SecurityEvent model
cat > app/Models/IoT/SecurityEvent.php << 'EOF'
<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'event_type',
        'severity',
        'source_ip',
        'domain',
        'action',
        'reason',
        'details'
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

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

    public function scopeByThreatLevel($query, string $level)
    {
        $levels = match($level) {
            'low' => ['low'],
            'medium' => ['low', 'medium'],
            'high' => ['low', 'medium', 'high'],
            'critical' => ['low', 'medium', 'high', 'critical'],
            default => ['low', 'medium', 'high', 'critical']
        };

        return $query->whereIn('severity', $levels);
    }
}
EOF

# DeviceRegistration model
cat > app/Models/IoT/DeviceRegistration.php << 'EOF'
<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Customer;
use App\Models\User;

class DeviceRegistration extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'customer_id',
        'device_id',
        'device_type', 
        'name',
        'status',
        'requested_by',
        'approved_by',
        'notes'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approve(User $approver): Device
    {
        // Create the actual device
        $device = Device::create([
            'customer_id' => $this->customer_id,
            'device_id' => $this->device_id,
            'device_type' => $this->device_type,
            'name' => $this->name,
            'status' => 'active'
        ]);

        // Generate API key
        $device->generateApiKey();

        // Update registration status
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id
        ]);

        return $device;
    }

    public function reject(User $approver, string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'notes' => $reason
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
EOF

echo "🎛️ Creating controllers..."

# Device Controller
cat > app/Http/Controllers/IoT/DeviceController.php << 'EOF'
<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\Device;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        $devices = $customer->devices()
            ->with(['metrics' => function($query) {
                $query->latest('collected_at')->limit(1);
            }])
            ->get()
            ->map(function($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'device_type' => $device->device_type,
                    'status' => $device->status,
                    'is_online' => $device->isOnline(),
                    'last_seen' => $device->last_seen,
                    'latest_metrics' => $device->metrics->first()?->getFormattedData(),
                    'metadata' => $device->metadata,
                ];
            });

        return response()->json([
            'devices' => $devices,
            'customer' => [
                'name' => $customer->name,
                'subscription_tier' => $customer->subscription_tier,
                'device_count' => $devices->count(),
                'max_devices' => $customer->max_devices,
            ]
        ]);
    }

    public function show(Request $request, string $deviceId): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        $device = Device::where('device_id', $deviceId)
            ->where('customer_id', $customer->id)
            ->with(['metrics' => function($query) {
                $query->latest('collected_at')->limit(50);
            }, 'securityEvents' => function($query) {
                $query->latest()->limit(20);
            }])
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        return response()->json([
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'device_type' => $device->device_type,
                'status' => $device->status,
                'is_online' => $device->isOnline(),
                'last_seen' => $device->last_seen,
                'metadata' => $device->metadata,
                'metrics' => $device->metrics->map->getFormattedData(),
                'security_events' => $device->securityEvents,
            ]
        ]);
    }

    public function update(Request $request, string $deviceId): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        $device = Device::where('device_id', $deviceId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive,suspended',
            'metadata' => 'sometimes|array'
        ]);

        $device->update($validated);

        return response()->json(['device' => $device]);
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        $device = Device::where('device_id', $deviceId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully']);
    }

    public function regenerateApiKey(Request $request, string $deviceId): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        $device = Device::where('device_id', $deviceId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $newApiKey = $device->generateApiKey();

        return response()->json([
            'message' => 'API key regenerated successfully',
            'api_key' => $newApiKey
        ]);
    }
}
EOF

# WebSocket Controller
cat > app/Http/Controllers/IoT/WebSocketController.php << 'EOF'
<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\Device;
use App\Models\IoT\DeviceMetric;
use App\Models\IoT\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebSocketController extends Controller
{
    public function handleDeviceMessage(Request $request)
    {
        // This will be called by WebSocket server when device sends data
        $deviceId = $request->input('device_id');
        $messageType = $request->input('type');
        $data = $request->input('data');

        $device = Device::where('device_id', $deviceId)->first();
        
        if (!$device) {
            Log::warning("Received message from unknown device: {$deviceId}");
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Update last seen
        $device->update(['last_seen' => now()]);

        // Process message based on type
        switch ($messageType) {
            case 'heartbeat':
                $this->handleHeartbeat($device, $data);
                break;
                
            case 'system_data':
                $this->handleSystemData($device, $data);
                break;
                
            case 'network_data':
                $this->handleNetworkData($device, $data);
                break;
                
            case 'security_event':
                $this->handleSecurityEvent($device, $data);
                break;
                
            default:
                Log::info("Unknown message type: {$messageType} from device: {$deviceId}");
        }

        // Broadcast to customer dashboard
        $this->broadcastToCustomer($device, $messageType, $data);

        return response()->json(['status' => 'processed']);
    }

    private function handleHeartbeat(Device $device, array $data): void
    {
        Log::info("Heartbeat from device: {$device->device_id}");
        // Just update last_seen (already done above)
    }

    private function handleSystemData(Device $device, array $data): void
    {
        DeviceMetric::create([
            'device_id' => $device->id,
            'metric_type' => 'system',
            'data' => $data,
            'collected_at' => now()
        ]);

        Log::info("System data received from device: {$device->device_id}");
    }

    private function handleNetworkData(Device $device, array $data): void
    {
        DeviceMetric::create([
            'device_id' => $device->id,
            'metric_type' => 'network',
            'data' => $data,
            'collected_at' => now()
        ]);

        Log::info("Network data received from device: {$device->device_id}");
    }

    private function handleSecurityEvent(Device $device, array $data): void
    {
        SecurityEvent::create([
            'device_id' => $device->id,
            'event_type' => $data['type'] ?? 'unknown',
            'severity' => $data['severity'] ?? 'low',
            'source_ip' => $data['source_ip'] ?? null,
            'domain' => $data['domain'] ?? null,
            'action' => $data['action'] ?? 'unknown',
            'reason' => $data['reason'] ?? null,
            'details' => $data['details'] ?? null,
        ]);

        Log::warning("Security event from device: {$device->device_id} - Type: {$data['type']}, Severity: {$data['severity']}");
    }

    private function broadcastToCustomer(Device $device, string $messageType, array $data): void
    {
        // Use Laravel broadcasting to send real-time updates to customer dashboard
        broadcast(new \App\Events\DeviceUpdate($device, $messageType, $data));
    }
}
EOF

# Device Registration Controller
cat > app/Http/Controllers/IoT/DeviceRegistrationController.php << 'EOF'
<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\DeviceRegistration;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceRegistrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        $registrations = $customer->deviceRegistrations()
            ->with(['requester', 'approver'])
            ->latest()
            ->get();

        return response()->json(['registrations' => $registrations]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $customer = $user->customer ?? Customer::where('keycloak_user_id', $user->id)->first();
        
        if (!$customer) {
            return response()->json(['error' => 'Customer not found'], 404);
        }

        // Check if customer can add more devices
        if (!$customer->canAddDevice()) {
            return response()->json([
                'error' => 'Device limit exceeded',
                'current_devices' => $customer->getActiveDevicesCount(),
                'max_devices' => $customer->max_devices
            ], 403);
        }

        $validated = $request->validate([
            'device_id' => 'required|string|unique:devices,device_id|unique:device_registrations,device_id',
            'device_type' => 'required|in:network,server,hybrid',
            'name' => 'required|string|max:255',
        ]);

        $registration = DeviceRegistration::create([
            'customer_id' => $customer->id,
            'device_id' => $validated['device_id'],
            'device_type' => $validated['device_type'],
            'name' => $validated['name'],
            'requested_by' => $user->id,
        ]);

        // Auto-approve for enterprise customers
        if ($customer->subscription_tier === 'enterprise') {
            $device = $registration->approve($user);
            
            return response()->json([
                'message' => 'Device registered and approved automatically',
                'device' => $device,
                'api_key' => $device->api_key,
                'setup_script' => $this->generateSetupScript($device)
            ]);
        }

        return response()->json([
            'message' => 'Device registration submitted for approval',
            'registration' => $registration
        ]);
    }

    public function approve(Request $request, DeviceRegistration $registration): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can approve (admin or customer owner)
        if (!$user->hasRole('admin') && $registration->customer_id !== $user->customer?->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$registration->isPending()) {
            return response()->json(['error' => 'Registration is not pending'], 400);
        }

        $device = $registration->approve($user);

        return response()->json([
            'message' => 'Device approved successfully',
            'device' => $device,
            'api_key' => $device->api_key,
            'setup_script' => $this->generateSetupScript($device)
        ]);
    }

    public function reject(Request $request, DeviceRegistration $registration): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user can reject (admin or customer owner)
        if (!$user->hasRole('admin') && $registration->customer_id !== $user->customer?->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $registration->reject($user, $validated['reason']);

        return response()->json(['message' => 'Device registration rejected']);
    }

    private function generateSetupScript($device): string
    {
        $endpoint = config('app.url') . '/iot/device/ws';
        
        return <<<SCRIPT
#!/bin/bash
# Oriana Device Setup Script
# Generated for: {$device->name}

CUSTOMER_ID="{$device->customer_id}"
DEVICE_ID="{$device->device_id}"
DEVICE_TYPE="{$device->device_type}"
API_KEY="{$device->api_key}"
ENDPOINT="{$endpoint}"

# Download and install Oriana agent
curl -sSL https://install.oriana.com.py/agent | bash -s -- \\
  --customer-id "\$CUSTOMER_ID" \\
  --device-id "\$DEVICE_ID" \\
  --device-type "\$DEVICE_TYPE" \\
  --api-key "\$API_KEY" \\
  --endpoint "\$ENDPOINT"

echo "✅ Device setup complete!"
echo "Your device should now appear as online in the Oriana dashboard."
SCRIPT;
    }
}
EOF

echo "🛡️ Creating middleware..."

# Device Authentication Middleware
cat > app/Http/Middleware/DeviceAuthMiddleware.php << 'EOF'
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\IoT\Device;

class DeviceAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $deviceId = $request->header('X-Device-ID');
        $customerId = $request->header('X-Customer-ID');
        $apiKey = $request->header('X-API-Key');

        if (!$deviceId || !$customerId || !$apiKey) {
            return response()->json(['error' => 'Missing device authentication headers'], 401);
        }

        $device = Device::where('device_id', $deviceId)
            ->where('customer_id', $customerId)
            ->where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Invalid device credentials'], 401);
        }

        // Update last seen timestamp
        $device->touch('last_seen');

        // Add device to request for use in controllers
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
EOF

echo "📡 Creating event classes..."

# Device Update Event
cat > app/Events/DeviceUpdate.php << 'EOF'
<?php

namespace App\Events;

use App\Models\IoT\Device;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;
    public $messageType;
    public $data;

    public function __construct(Device $device, string $messageType, array $data)
    {
        $this->device = $device;
        $this->messageType = $messageType;
        $this->data = $data;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('customer.' . $this->device->customer_id);
    }

    public function broadcastWith()
    {
        return [
            'device_id' => $this->device->device_id,
            'device_name' => $this->device->name,
            'message_type' => $this->messageType,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs()
    {
        return 'device.update';
    }
}
EOF

echo "🗺️ Adding routes..."

# Add IoT routes to api.php
cat >> routes/api.php << 'EOF'

// IoT Device Management Routes
Route::prefix('iot')->group(function () {
    
    // Device WebSocket endpoint (for agents)
    Route::middleware(['device.auth'])->group(function () {
        Route::post('/device/message', [App\Http\Controllers\IoT\WebSocketController::class, 'handleDeviceMessage']);
    });
    
    // Customer Dashboard API (requires authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('devices', App\Http\Controllers\IoT\DeviceController::class);
        Route::post('devices/{device}/regenerate-key', [App\Http\Controllers\IoT\DeviceController::class, 'regenerateApiKey']);
        
        Route::apiResource('device-registrations', App\Http\Controllers\IoT\DeviceRegistrationController::class);
        Route::post('device-registrations/{registration}/approve', [App\Http\Controllers\IoT\DeviceRegistrationController::class, 'approve']);
        Route::post('device-registrations/{registration}/reject', [App\Http\Controllers\IoT\DeviceRegistrationController::class, 'reject']);
    });
});
EOF

echo "⚙️ Registering middleware..."

# Add middleware to Kernel.php
echo "Add this to app/Http/Kernel.php in the \$middlewareAliases array:"
echo "'device.auth' => \App\Http\Middleware\DeviceAuthMiddleware::class,"

echo "🎭 Publishing configuration..."

# Publish WebSockets config
php artisan vendor:publish --provider="BeyondCode\LaravelWebSockets\WebSocketsServiceProvider" --tag="config"

echo "🗄️ Running migrations..."
php artisan migrate

echo "🌱 Creating seeders..."

# Device seeder for testing
cat > database/seeders/IoTSeeder.php << 'EOF'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\IoT\Device;
use App\Models\IoT\DeviceMetric;
use App\Models\IoT\SecurityEvent;

class IoTSeeder extends Seeder
{
    public function run()
    {
        // Create test customer if it doesn't exist
        $customer = Customer::firstOrCreate(
            ['email' => 'test@oriana.com.py'],
            [
                'name' => 'Test Customer',
                'subscription_tier' => 'professional',
                'max_devices' => 25,
                'status' => 'active'
            ]
        );

        // Create test devices
        $devices = [
            [
                'device_id' => 'device-test-001',
                'device_type' => 'hybrid',
                'name' => 'Test Server',
            ],
            [
                'device_id' => 'guard-test-001',
                'device_type' => 'network',
                'name' => 'Test Network Guard',
            ]
        ];

        foreach ($devices as $deviceData) {
            $device = Device::firstOrCreate(
                [
                    'customer_id' => $customer->id,
                    'device_id' => $deviceData['device_id']
                ],
                [
                    'device_type' => $deviceData['device_type'],
                    'name' => $deviceData['name'],
                    'status' => 'active',
                    'last_seen' => now(),
                    'metadata' => [
                        'location' => 'Test Environment',
                        'hardware' => 'Development Setup'
                    ]
                ]
            );

            $device->generateApiKey();

            // Create sample metrics
            DeviceMetric::create([
                'device_id' => $device->id,
                'metric_type' => 'system',
                'data' => [
                    'cpu' => ['usage' => 25.5, 'cores' => 4, 'temperature' => 45.2],
                    'memory' => ['total' => 8589934592, 'used' => 2147483648, 'usage_percent' => 25.0],
                    'disk' => ['total' => 500107862016, 'used' => 50010786202, 'usage_percent' => 10.0],
                    'system' => ['uptime' => 86400, 'load_average' => 1.2]
                ],
                'collected_at' => now()
            ]);

            if ($device->device_type === 'network') {
                DeviceMetric::create([
                    'device_id' => $device->id,
                    'metric_type' => 'network',
                    'data' => [
                        'summary' => [
                            'active_devices' => 12,
                            'total_devices' => 15,
                            'bandwidth_utilization' => 45.8,
                            'blocked_requests' => 3
                        ],
                        'bandwidth' => [
                            'total_upload' => 1024.5,
                            'total_download' => 5120.8
                        ]
                    ],
                    'collected_at' => now()
                ]);

                // Create sample security events
                SecurityEvent::create([
                    'device_id' => $device->id,
                    'event_type' => 'blocked_request',
                    'severity' => 'medium',
                    'source_ip' => '192.168.1.101',
                    'domain' => 'facebook.com',
                    'action' => 'blocked',
                    'reason' => 'Category blocked: social-media'
                ]);
            }
        }

        $this->command->info('IoT test data seeded successfully!');
        $this->command->info("Test customer: {$customer->email}");
        foreach ($devices as $i => $deviceData) {
            $device = Device::where('device_id', $deviceData['device_id'])->first();
            $this->command->info("Device {$deviceData['device_id']}: API Key = {$device->api_key}");
        }
    }
}
EOF

echo "✅ Laravel IoT Module Setup Complete!"
echo "====================================="
echo ""
echo "📋 Next steps:"
echo "1. Add middleware to app/Http/Kernel.php:"
echo "   'device.auth' => \App\Http\Middleware\DeviceAuthMiddleware::class,"
echo ""
echo "2. Run the seeder:"
echo "   php artisan db:seed --class=IoTSeeder"
echo ""
echo "3. Configure Laravel WebSockets in config/websockets.php"
echo ""
echo "4. Test the API endpoints:"
echo "   GET /api/iot/devices - List customer devices"
echo "   POST /api/iot/device-registrations - Register new device"
echo ""
echo "5. Update your React dashboard to use these endpoints"
echo ""
echo "🔧 Files created:"
echo "   • 5 database migrations"
echo "   • 4 Eloquent models"
echo "   • 3 controllers"
echo "   • 1 middleware"
echo "   • 1 event class"
echo "   • 1 seeder"
echo "   • API routes"
echo ""
echo "🎯 Test device credentials (after running seeder):"
echo "   Device ID: device-test-001"
echo "   Customer ID: [customer-uuid-from-db]"
echo "   API Key: [generated-key-from-seeder]"
echo