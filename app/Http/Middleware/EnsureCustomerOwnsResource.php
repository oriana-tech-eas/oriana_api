<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\IoT\Device;
use App\Models\IoT\FamilyDevice;

class EnsureCustomerOwnsResource
{
    public function handle(Request $request, Closure $next, $resourceType = 'device')
    {
        $customer = $request->attributes->get('customer');
        if (!$customer) {
            return response()->json(['error' => 'Customer context missing'], 500);
        }

        if (in_array($resourceType, ['device', 'family_device'])) {
            return $this->checkDeviceOwnership($request, $next, $customer, $resourceType);
        }

        return $next($request);
    }

    private function checkDeviceOwnership(Request $request, Closure $next, $customer, $resourceType)
    {
        $routeKey = $resourceType === 'family_device' ? 'family_device' : 'device';
        $deviceId = $request->route($routeKey) ?? $request->route('id');

        if (!$deviceId) {
            return $next($request);
        }

        $model = $resourceType === 'family_device' ? FamilyDevice::class : Device::class;

        $device = $model::where('id', $deviceId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'Device not found or access denied',
                'message' => 'The requested device does not exist or you do not have access to it'
            ], 404);
        }

        $request->attributes->add(['device' => $device]);

        return $next($request);
    }
}
