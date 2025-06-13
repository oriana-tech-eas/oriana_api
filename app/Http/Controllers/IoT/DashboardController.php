<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\Device;
use App\Models\IoT\DeviceMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Get all devices for the authenticated customer
     */
    public function devices(Request $request)
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You must be authenticated to access this resource'
            ], 401);
        }
        
        // Get devices with latest metrics, filtered by customer
        $devices = Device::where('customer_id', $customer->id)
            ->with(['metrics' => function($query) {
                $query->limit(5)->orderBy('collected_at', 'desc');
            }])
            ->get()
            ->map(function($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'type' => $device->device_type,
                    'status' => $device->status,
                    'last_seen' => $device->last_seen,
                    'metadata' => $device->metadata,
                    'is_online' => $device->isOnline(),
                    'latest_metrics' => $device->metrics->map(function($metric) {
                        return [
                            'id' => $metric->id,
                            'device_id' => $metric->device_id,
                            'metric_type' => $metric->metric_type,
                            'data' => $metric->data,
                            'collected_at' => $metric->collected_at,
                            'created_at' => $metric->created_at,
                            'updated_at' => $metric->updated_at,
                        ];
                    })
                ];
            });

        Log::info('Dashboard devices request', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'device_count' => $devices->count()
        ]);

        return response()->json([
            'devices' => $devices,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'subscription_tier' => $customer->subscription_tier,
                'max_devices' => $customer->max_devices
            ]
        ]);
    }

    /**
     * Get specific device details for the authenticated customer
     */
    public function device(Request $request, $deviceId)
    {
        $customer = $request->attributes->get('customer');
        
        $device = Device::where('customer_id', $customer->id)
            ->where('id', $deviceId)
            ->with(['metrics' => function($query) {
                $query->limit(10)->orderBy('collected_at', 'desc');
            }])
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
                'message' => 'The requested device does not exist or you do not have access to it'
            ], 404);
        }

        return response()->json([
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'type' => $device->device_type,
                'status' => $device->status,
                'last_seen' => $device->last_seen,
                'metadata' => $device->metadata,
                'is_online' => $device->isOnline(),
                'latest_metrics' => $device->metrics
            ]
        ]);
    }

    /**
     * Get device metrics with pagination and filtering
     */
    public function deviceMetrics(Request $request, $deviceId)
    {
        $customer = $request->attributes->get('customer');
        
        // Verify device ownership
        $device = Device::where('customer_id', $customer->id)
            ->where('id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
                'message' => 'The requested device does not exist or you do not have access to it'
            ], 404);
        }

        $query = DeviceMetric::where('device_id', $device->id);

        // Apply filters
        if ($request->has('metric_type')) {
            $query->where('metric_type', $request->metric_type);
        }

        if ($request->has('from_date')) {
            $query->where('collected_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('collected_at', '<=', $request->to_date);
        }

        // Pagination
        $limit = min($request->get('limit', 10), 100); // Max 100 records
        $offset = $request->get('offset', 0);

        $metrics = $query->orderBy('collected_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $total = $query->count();

        return response()->json([
            'metrics' => $metrics,
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    }

    /**
     * Execute device actions (restart, pause, etc.)
     */
    public function deviceAction(Request $request, $deviceId)
    {
        $customer = $request->attributes->get('customer');
        $tokenPayload = $request->attributes->get('token_payload');
        
        // Verify device ownership
        $device = Device::where('customer_id', $customer->id)
            ->where('id', $deviceId)
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found',
                'message' => 'The requested device does not exist or you do not have access to it'
            ], 404);
        }

        $request->validate([
            'action' => 'required|string|in:restart,pause_filtering,resume_filtering,update_config'
        ]);

        $action = $request->input('action');

        // Log the action
        Log::info('Device action requested', [
            'customer_id' => $customer->id,
            'device_id' => $device->id,
            'action' => $action,
            'requested_by' => $tokenPayload->email ?? 'unknown'
        ]);

        // Here you would typically queue a job to send the action to the device
        // For now, we'll just return a success response
        
        // You might want to create a DeviceAction model to track these
        
        return response()->json([
            'success' => true,
            'message' => "Action '{$action}' queued for device {$device->name}",
            'action_id' => uniqid(), // Generate a unique action ID for tracking
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->device_type,
            ]
        ]);
    }

    /**
     * Get customer summary/dashboard stats
     */
    public function summary(Request $request)
    {
        $customer = $request->attributes->get('customer');
        
        $devices = Device::where('customer_id', $customer->id)->get();
        
        $summary = [
            'total_devices' => $devices->count(),
            'online_devices' => $devices->where('status', 'active')->count(),
            'device_types' => $devices->groupBy('type')->map->count(),
            'last_activity' => $devices->max('last_seen'),
            'customer_info' => [
                'name' => $customer->name,
                'tier' => $customer->subscription_tier,
                'max_devices' => $customer->max_devices,
                'used_devices' => $devices->count(),
                'available_devices' => $customer->max_devices - $devices->count()
            ]
        ];

        return response()->json($summary);
    }
}
