<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\DeviceProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceProfilesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');

            // Fetch device profiles for the customer
            $profiles = DeviceProfile::where('customer_id', $customer->id)
                ->withCount('familyDevices')
                ->orderBy('is_default', 'desc')
                ->orderBy('name')
                ->get()
                ->map(function ($profile) {
                    return [
                        'id' => $profile->id,
                        'name' => $profile->name,
                        'description' => $profile->description,
                        'isDefault' => $profile->is_default,
                        'deviceCount' => $profile->family_devices_count,
                        'timeLimits' => $profile->default_time_limits,
                    ];
                });

            return response()->json([
                'success' => true,
                'profiles' => $profiles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch device profiles',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
