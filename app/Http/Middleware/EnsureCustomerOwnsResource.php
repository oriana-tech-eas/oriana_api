<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\IoT\Device;

class EnsureCustomerOwnsResource
{
    public function handle(Request $request, Closure $next, $resourceType = 'device')
    {
        // Get customer from request attributes (set by previous middleware)
        $customer = $request->attributes->get('customer');
        
        if (!$customer) {
            return response()->json(['error' => 'Customer context missing'], 500);
        }

        if ($resourceType === 'device') {
            return $this->checkDeviceOwnership($request, $next, $customer);
        }

        // Add other resource types as needed
        return $next($request);
    }

    private function checkDeviceOwnership(Request $request, Closure $next, $customer)
    {
        $deviceId = $request->route('device') ?? $request->route('id');
        
        if (!$deviceId) {
            return $next($request);
        }

        $device = Device::where('id', $deviceId)
            ->where('customer_id', $customer->id)
            ->first();
        
        if (!$device) {
            return response()->json([
                'error' => 'Device not found or access denied',
                'message' => 'The requested device does not exist or you do not have access to it'
            ], 404);
        }
        
        // Store device in request attributes (proper way)
        $request->attributes->add(['device' => $device]);
        
        return $next($request);
    }
}
