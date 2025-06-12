<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IoT\Device;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * Simple health check for devices
     */
    public function health(Request $request)
    {
        $device = $request->attributes->get('device');
        
        return response()->json([
            'status' => 'healthy',
            'device_id' => $device->device_id,
            'timestamp' => now()->toISOString(),
            'server_time' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get device information and configuration
     */
    public function getInfo(Request $request)
    {
        $device = $request->attributes->get('device');
        $customer = $request->attributes->get('customer');
        
        return response()->json([
            'device' => [
                'id' => $device->device_id,
                'name' => $device->name,
                'type' => $device->device_type,
                'status' => $device->status,
                'last_seen' => $device->last_seen,
                'metadata' => $device->metadata,
            ],
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'tier' => $customer->subscription_tier,
                'status' => $customer->status,
            ],
            'configuration' => [
                'heartbeat_interval' => 60, // seconds
                'metrics_interval' => 30,   // seconds
                'security_enabled' => true,
                'debug_mode' => config('app.debug'),
            ]
        ]);
    }

    /**
     * Handle device heartbeat
     */
    public function heartbeat(Request $request)
    {
        $device = $request->attributes->get('device');
        
        // Validate heartbeat data
        $validated = $request->validate([
            'status' => 'required|string',
            'version' => 'required|string',
            'uptime' => 'nullable|integer',
            'load_average' => 'nullable|numeric',
        ]);

        // Update device last seen and any metadata
        $device->update([
            'last_seen' => now(),
            'metadata' => array_merge($device->metadata ?? [], [
                'last_heartbeat' => $validated,
                'last_heartbeat_at' => now()->toISOString()
            ])
        ]);

        Log::info('Device heartbeat received', [
            'device_id' => $device->device_id,
            'status' => $validated['status'],
            'version' => $validated['version']
        ]);

        return response()->json([
            'status' => 'acknowledged',
            'timestamp' => now()->toISOString(),
            'next_heartbeat' => now()->addSeconds(60)->toISOString(),
        ]);
    }

    /**
     * Get customer's devices (for dashboard)
     */
    public function getCustomerDevices(Request $request)
    {
        // TODO: Extract customer from JWT token
        $customerId = $request->user()->customer_id ?? 1; // Placeholder
        
        $devices = Device::where('customer_id', $customerId)
            ->with(['metrics' => function($query) {
                $query->latest()->limit(10);
            }])
            ->get();

        return response()->json([
            'devices' => $devices->map(function($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'type' => $device->device_type,
                    'status' => $device->status,
                    'last_seen' => $device->last_seen,
                    'metadata' => $device->metadata,
                    'is_online' => $device->last_seen && $device->last_seen->gt(now()->subMinutes(5)),
                    'latest_metrics' => $device->metrics->take(3),
                ];
            })
        ]);
    }

    /**
     * Send restart command to device
     */
    public function restartDevice(Request $request, Device $device)
    {
        // TODO: Send WebSocket message to device
        Log::info('Device restart requested', [
            'device_id' => $device->device_id,
            'requested_by' => $request->user()->id ?? 'unknown'
        ]);

        return response()->json([
            'status' => 'restart_command_sent',
            'device_id' => $device->device_id,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Update device settings
     */
    public function updateSettings(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
        ]);

        $device->update($validated);

        return response()->json([
            'status' => 'updated',
            'device' => $device->fresh(),
        ]);
    }
}
