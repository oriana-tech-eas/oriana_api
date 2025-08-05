<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\DeviceRuleOverride;
use App\Models\IoT\FamilyRule;
use App\Models\IoT\FamilyDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceRuleOverrideController extends Controller
{
    /**
     * Get all overrides for a specific device
     */
    public function index(Request $request, string $deviceId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $query = DeviceRuleOverride::forDevice($deviceId)
                ->with(['familyRule', 'familyDevice']);

            // Apply filters
            if ($request->has('override_type')) {
                $query->byType($request->override_type);
            }

            if ($request->has('created_by')) {
                $query->createdBy($request->created_by);
            }

            if ($request->has('active_only') && $request->active_only) {
                $query->active();
            }

            if ($request->has('expired_only') && $request->expired_only) {
                $query->expired();
            }

            $overrides = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50))
                ->through(function ($override) {
                    return $override->toApiArray();
                });

            return response()->json([
                'success' => true,
                'overrides' => $overrides->items(),
                'pagination' => [
                    'current_page' => $overrides->currentPage(),
                    'per_page' => $overrides->perPage(),
                    'total' => $overrides->total(),
                    'last_page' => $overrides->lastPage(),
                ],
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch device overrides',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all overrides for a family rule
     */
    public function getByRule(Request $request, string $ruleId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the rule belongs to the customer
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $query = DeviceRuleOverride::forRule($ruleId)
                ->with(['familyDevice', 'familyRule']);

            // Apply filters
            if ($request->has('override_type')) {
                $query->byType($request->override_type);
            }

            if ($request->has('active_only') && $request->active_only) {
                $query->active();
            }

            $overrides = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50))
                ->through(function ($override) {
                    return $override->toApiArray();
                });

            return response()->json([
                'success' => true,
                'overrides' => $overrides->items(),
                'pagination' => [
                    'current_page' => $overrides->currentPage(),
                    'per_page' => $overrides->perPage(),
                    'total' => $overrides->total(),
                    'last_page' => $overrides->lastPage(),
                ],
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch rule overrides',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific override
     */
    public function show(Request $request, string $deviceId, string $overrideId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $override = DeviceRuleOverride::forDevice($deviceId)
                ->where('id', $overrideId)
                ->with(['familyRule', 'familyDevice'])
                ->first();

            if (!$override) {
                return response()->json([
                    'error' => 'Override not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'override' => $override->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch override',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new override for a device
     */
    public function store(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'family_rule_id' => 'required|uuid|exists:family_rules,id',
            'override_type' => 'required|in:allow_domain,block_domain,extend_time,restrict_time,disable_category,enable_category',
            'override_value' => 'required|string|max:255',
            'reason' => 'sometimes|nullable|string|max:500',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'duration_minutes' => 'sometimes|integer|min:1|max:1440' // Alternative to expires_at
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            // Verify the rule belongs to the customer
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $request->family_rule_id)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            // Calculate expiration time
            $expiresAt = null;
            if ($request->has('expires_at')) {
                $expiresAt = $request->expires_at;
            } elseif ($request->has('duration_minutes')) {
                $expiresAt = now()->addMinutes($request->duration_minutes);
            }

            $overrideData = [
                'family_device_id' => $deviceId,
                'family_rule_id' => $request->family_rule_id,
                'override_type' => $request->override_type,
                'override_value' => $request->override_value,
                'reason' => $request->reason,
                'expires_at' => $expiresAt,
                'created_by' => 'parent'
            ];

            $override = DeviceRuleOverride::create($overrideData);

            return response()->json([
                'success' => true,
                'message' => 'Override created successfully',
                'override' => $override->load(['familyRule', 'familyDevice'])->toApiArray()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create override',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update an existing override
     */
    public function update(Request $request, string $deviceId, string $overrideId): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|nullable|string|max:500',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'extend_minutes' => 'sometimes|integer|min:1|max:1440'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $override = DeviceRuleOverride::forDevice($deviceId)
                ->where('id', $overrideId)
                ->first();

            if (!$override) {
                return response()->json([
                    'error' => 'Override not found'
                ], 404);
            }

            $updateData = [];

            if ($request->has('reason')) {
                $updateData['reason'] = $request->reason;
            }

            if ($request->has('expires_at')) {
                $updateData['expires_at'] = $request->expires_at;
            } elseif ($request->has('extend_minutes')) {
                $override->extendExpiration($request->extend_minutes);
            }

            if (!empty($updateData)) {
                $override->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Override updated successfully',
                'override' => $override->fresh()->load(['familyRule', 'familyDevice'])->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update override',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete an override
     */
    public function destroy(Request $request, string $deviceId, string $overrideId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $override = DeviceRuleOverride::forDevice($deviceId)
                ->where('id', $overrideId)
                ->first();

            if (!$override) {
                return response()->json([
                    'error' => 'Override not found'
                ], 404);
            }

            $override->delete();

            return response()->json([
                'success' => true,
                'message' => 'Override deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete override',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Expire an override (mark as expired)
     */
    public function expire(Request $request, string $deviceId, string $overrideId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $override = DeviceRuleOverride::forDevice($deviceId)
                ->where('id', $overrideId)
                ->first();

            if (!$override) {
                return response()->json([
                    'error' => 'Override not found'
                ], 404);
            }

            if ($override->isExpired()) {
                return response()->json([
                    'error' => 'Override is already expired'
                ], 400);
            }

            $override->markAsExpired();

            return response()->json([
                'success' => true,
                'message' => 'Override expired successfully',
                'override' => $override->fresh()->load(['familyRule', 'familyDevice'])->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to expire override',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get active overrides for a device
     */
    public function getActive(Request $request, string $deviceId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $overrides = DeviceRuleOverride::forDevice($deviceId)
                ->active()
                ->with(['familyRule', 'familyDevice'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($override) {
                    return $override->toApiArray();
                });

            return response()->json([
                'success' => true,
                'active_overrides' => $overrides,
                'total' => $overrides->count(),
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch active overrides',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get override statistics for a device
     */
    public function getStats(Request $request, string $deviceId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            // Verify the device belongs to the customer
            $device = FamilyDevice::forCustomer($customer->id)
                ->where('id', $deviceId)
                ->first();

            if (!$device) {
                return response()->json([
                    'error' => 'Device not found'
                ], 404);
            }

            $totalOverrides = DeviceRuleOverride::forDevice($deviceId)->count();
            $activeOverrides = DeviceRuleOverride::forDevice($deviceId)->active()->count();
            $expiredOverrides = DeviceRuleOverride::forDevice($deviceId)->expired()->count();

            // Count by type
            $overridesByType = DeviceRuleOverride::forDevice($deviceId)
                ->selectRaw('override_type, COUNT(*) as count')
                ->groupBy('override_type')
                ->pluck('count', 'override_type')
                ->toArray();

            // Count by creator
            $overridesByCreator = DeviceRuleOverride::forDevice($deviceId)
                ->selectRaw('created_by, COUNT(*) as count')
                ->groupBy('created_by')
                ->pluck('count', 'created_by')
                ->toArray();

            // Recent overrides
            $recentOverrides = DeviceRuleOverride::forDevice($deviceId)
                ->with(['familyRule'])
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($override) {
                    return [
                        'id' => $override->id,
                        'override_type' => $override->override_type,
                        'override_description' => $override->override_description,
                        'is_active' => $override->isActive(),
                        'created_at' => $override->created_at,
                        'created_at_human' => $override->created_at->diffForHumans()
                    ];
                });

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_overrides' => $totalOverrides,
                    'active_overrides' => $activeOverrides,
                    'expired_overrides' => $expiredOverrides,
                    'overrides_by_type' => $overridesByType,
                    'overrides_by_creator' => $overridesByCreator,
                    'recent_overrides' => $recentOverrides
                ],
                'device' => [
                    'id' => $device->id,
                    'name' => $device->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch override statistics',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}