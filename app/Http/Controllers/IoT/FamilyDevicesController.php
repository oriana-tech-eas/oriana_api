<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\FamilyDevice;
use App\Models\IoT\DeviceProfile;
use App\Models\IoT\DeviceTrafficLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FamilyDevicesController extends Controller
{
    /**
     * Get all family devices for the authenticated customer
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
        
            $devices = FamilyDevice::where('customer_id', $customer->id)
                ->with(['profile', 'currentSession'])
                ->get()
                ->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'name' => $device->name,
                        'avatar' => $device->avatar,
                        'macAddress' => $device->mac_address,
                        'device' => $device->display_device, // Uses the accessor we created
                        'ip' => $device->current_ip,
                        'status' => $device->status, // online/offline
                        'timeConnected' => $device->time_connected,
                        'dataUsage' => $device->data_usage,
                        'profile' => [
                            'name' => $device->profile?->name ?: 'Sin perfil asignado'
                        ],
                        'sitesBlocked' => $device->sites_blocked,
                        'sitesAllowed' => $device->sites_allowed,
                        'isIdentified' => $device->is_identified,
                        // Additional metadata for dashboard
                        'type' => $device->device_type,
                        'manufacturer' => $device->manufacturer,
                        'lastSeen' => $device->last_seen?->diffForHumans(),
                        'firstSeen' => $device->first_seen?->diffForHumans(),
                    ];
                });

            // Calculate summary statistics
            $summary = [
                'total_devices' => $devices->count(),
                'online_devices' => $devices->where('status', 'online')->count(),
                'offline_devices' => $devices->where('status', 'offline')->count(),
                'total_data_usage' => $this->getTotalDataUsageForCustomer($customer->id),
                'most_active_device' => $this->getMostActiveDevice($customer->id),
            ];

            return response()->json([
                'success' => true,
                'devices' => $devices->values(), // Reset array keys
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch family devices',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific family device by ID
     */
    public function show(Request $request, string $deviceId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            $device = FamilyDevice::where('customer_id', $customer->id)
                ->where('id', $deviceId)
                ->with(['profile', 'currentSession', 'sessions' => function($query) {
                    $query->latest()->take(10);
                }])
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            // Get traffic history for the device (last 7 days)
            $trafficHistory = $this->getDeviceTrafficHistory($device->id);

            return response()->json([
                'success' => true,
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'avatar' => $device->avatar,
                    'macAddress' => $device->mac_address,
                    'device' => $device->display_device,
                    'ip' => $device->current_ip,
                    'status' => $device->status,
                    'timeConnected' => $device->time_connected,
                    'dataUsage' => $device->data_usage,
                    'profile' => [
                        'id' => $device->profile?->id,
                        'name' => $device->profile?->name ?: 'Sin perfil asignado'
                    ],
                    'sitesBlocked' => $device->sites_blocked,
                    'sitesAllowed' => $device->sites_allowed,
                    'type' => $device->device_type,
                    'manufacturer' => $device->manufacturer,
                    'lastSeen' => $device->last_seen,
                    'firstSeen' => $device->first_seen,
                    // This data should come from trafficLogs, not directly from the device
                    // 'totalDataUsage' => $device->data_usage_bytes,
                ],
                'trafficHistory' => $trafficHistory,
                'recentSessions' => $device->sessions->map(function($session) {
                    return [
                        'id' => $session->id,
                        'startedAt' => $session->started_at,
                        'endedAt' => $session->ended_at,
                        'duration' => $session->duration_human,
                        'dataUsage' => $session->formatted_data_usage,
                        'isActive' => $session->is_active
                    ];
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch device details',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Identify/rename a family device
     */
    public function identify(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'avatar' => 'sometimes|string|in:bear,crocodile,duck,elephant,flamingo,horse,koala,lion,moose,penguin,rabbit,raccoon,rhino,shark,tiger,toucan,wildboar,zebra',
            'profile_id' => 'sometimes|nullable|uuid|exists:device_profiles,id'
        ]);

        $response = null;
        $status = 200;

        try {
            $customer = $request->attributes->get('customer');
            
            $device = FamilyDevice::where('customer_id', $customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                $response = [
                    'error' => 'Device not found'
                ];
                $status = 404;
            } else {
                // Validate profile belongs to customer if provided
                if ($request->profile_id) {
                    $profile = DeviceProfile::where('customer_id', $customer->id)
                        ->where('id', $request->profile_id)
                        ->first();
                    
                    if (!$profile) {
                        $response = [
                            'error' => 'Profile not found or not accessible'
                        ];
                        $status = 400;
                    }
                }

                if ($response === null) {
                    $device->update([
                        'name' => $request->name,
                        'avatar' => $request->avatar ?? $device->avatar,
                        'profile_id' => $request->profile_id ?? $device->profile_id,
                        'is_identified' => true
                    ]);

                    $response = [
                        'success' => true,
                        'message' => 'Device identified successfully',
                        'device' => [
                            'id' => $device->id,
                            'name' => $device->name,
                            'avatar' => $device->avatar,
                            'profile' => [
                                'name' => $device->profile?->name ?: 'Sin perfil asignado'
                            ]
                        ]
                    ];
                }
            }
        } catch (\Exception $e) {
            $response = [
                'error' => 'Failed to identify device',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ];
            $status = 500;
        }

        return response()->json($response, $status);
    }

    /**
     * Update device profile
     */
    public function updateProfile(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'profile_id' => 'required|uuid|exists:device_profiles,id'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $device = FamilyDevice::where('customer_id', $customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            // Validate profile belongs to customer
            $profile = DeviceProfile::where('customer_id', $customer->id)
                ->where('id', $request->profile_id)
                ->first();
            
            if (!$profile) {
                return response()->json([
                    'error' => 'Profile not found or not accessible'
                ], 400);
            }

            $device->update(['profile_id' => $request->profile_id]);

            return response()->json([
                'success' => true,
                'message' => 'Device profile updated successfully',
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'profile' => [
                        'id' => $profile->id,
                        'name' => $profile->name
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update device profile',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unknown/unidentified devices that need naming
     */
    public function unidentified(Request $request): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            $devices = FamilyDevice::where('customer_id', $customer->id)
                ->where('is_identified', false)
                ->where('is_active', true) // Only show active unknown devices
                ->get()
                ->map(function ($device) {
                    return [
                        'id' => $device->id,
                        'suggestedName' => $this->generateSuggestedName($device),
                        'macAddress' => $device->mac_address,
                        'ip' => $device->current_ip,
                        'type' => $device->device_type,
                        'manufacturer' => $device->manufacturer,
                        'firstSeen' => $device->first_seen?->diffForHumans(),
                        'lastSeen' => $device->last_seen?->diffForHumans(),
                    ];
                });

            return response()->json([
                'success' => true,
                'devices' => $devices->values(),
                'count' => $devices->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch unidentified devices',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // Private helper methods
    private function getTotalDataUsageForCustomer(string $customerId): string
    {
        $totalBytes = DeviceTrafficLog::whereHas('familyDevice', function($query) use ($customerId) {
            $query->where('customer_id', $customerId);
        })
        ->whereDate('recorded_at', today())
        ->sum(DB::raw('bytes_downloaded + bytes_uploaded'));
        
        return $this->formatBytes($totalBytes);
    }

    private function getMostActiveDevice(string $customerId): ?array
    {
        $device = FamilyDevice::where('customer_id', $customerId)
            ->where('is_identified', true)
            ->whereHas('trafficLogs', function($query) {
                $query->whereDate('recorded_at', today());
            })
            ->withSum(['trafficLogs as total_usage' => function($query) {
                $query->whereDate('recorded_at', today());
            }], DB::raw('bytes_downloaded + bytes_uploaded'))
            ->orderBy('total_usage', 'desc')
            ->first();

        if (!$device) {
            return null;
        }

        return [
            'name' => $device->name,
            'usage' => $this->formatBytes($device->total_usage ?? 0)
        ];
    }

    private function getDeviceTrafficHistory(string $deviceId): array
    {
        $history = DeviceTrafficLog::where('family_device_id', $deviceId)
            ->where('recorded_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(recorded_at) as date,
                        SUM(bytes_downloaded + bytes_uploaded) as total_bytes')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($record) {
                return [
                    'date' => $record->date,
                    'usage' => $this->formatBytes($record->total_bytes),
                    'bytes' => $record->total_bytes
                ];
            });

        return $history->toArray();
    }

    private function generateSuggestedName(FamilyDevice $device): string
    {
        $manufacturer = $device->manufacturer ?: 'Unknown';
        $type = ucfirst($device->device_type);
        $macSuffix = substr(str_replace(':', '', $device->mac_address), -4);
        
        return "{$manufacturer} {$type} ({$macSuffix})";
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
