<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\IoT\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class DeviceAuthMiddleware
{
    /**
     * Handle device authentication for IoT endpoints
     */
    public function handle(Request $request, Closure $next)
    {
        $response = null;

        // Extract headers that our Go agent sends
        $deviceId = $request->header('X-Device-ID');
        $customerId = $request->header('X-Customer-ID');
        $apiKey = $request->header('X-API-Key');

        // Validate all required headers are present
        if (!$deviceId || !$customerId || !$apiKey) {
            $response = response()->json([
                'error' => 'Missing authentication headers',
                'required' => ['X-Device-ID', 'X-Customer-ID', 'X-API-Key']
            ], 401);
        } else {
            // Find the device with all credentials
            $device = Device::where('device_id', $deviceId)
                ->where('customer_id', $customerId)
                ->where('api_key', $apiKey)
                ->where('status', 'active')
                ->with('customer') // Eager load customer for efficiency
                ->first();

            if (!$device) {
                Log::warning('Device authentication failed', [
                    'device_id' => $deviceId,
                    'customer_id' => $customerId,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                $response = response()->json([
                    'error' => 'Invalid device credentials or device inactive'
                ], 401);
            } elseif ($device->customer->status !== 'active') {
                $response = response()->json([
                    'error' => 'Customer account suspended'
                ], 403);
            } else {
                // Update last seen timestamp (async to avoid blocking)
                dispatch(function() use ($device) {
                    $device->touch('last_seen');
                });

                // Log successful authentication
                Log::info('Device authenticated successfully', [
                    'device_id' => $device->device_id,
                    'device_name' => $device->name,
                    'customer' => $device->customer->name,
                    'device_type' => $device->device_type
                ]);

                // Add device to request for use in controllers
                $request->attributes->set('device', $device);
                $request->attributes->set('customer', $device->customer);

                $response = $next($request);
            }
        }

        return $response;
    }
}
