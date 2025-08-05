<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\FamilyRule;
use App\Models\IoT\FamilyRuleActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class FamilyRuleController extends Controller
{
    /**
     * Get all family rules for the authenticated customer
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            $rules = FamilyRule::forCustomer($customer->id)
                ->withCount(['blockedDomains', 'allowedDomains', 'devices'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($rule) {
                    return [
                        'id' => $rule->id,
                        'name' => $rule->name,
                        'is_active' => $rule->is_active,
                        'blocked_categories' => $rule->blocked_category_slugs,
                        'global_time_restrictions' => $rule->global_time_restrictions,
                        'require_adult_approval' => $rule->require_adult_approval,
                        'has_adult_password' => !empty($rule->adult_override_password),
                        'blocked_domains_count' => $rule->blocked_domains_count,
                        'allowed_domains_count' => $rule->allowed_domains_count,
                        'devices_count' => $rule->devices_count,
                        'has_time_restrictions' => $rule->hasTimeRestrictions(),
                        'is_currently_restricted' => $rule->isCurrentlyRestricted(),
                        'created_at' => $rule->created_at,
                        'updated_at' => $rule->updated_at
                    ];
                });

            return response()->json([
                'success' => true,
                'rules' => $rules,
                'total' => $rules->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch family rules',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific family rule by ID
     */
    public function show(Request $request, string $ruleId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->withCount(['blockedDomains', 'allowedDomains', 'devices', 'deviceOverrides'])
                ->with(['blockedDomains.category', 'allowedDomains'])
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'is_active' => $rule->is_active,
                    'blocked_categories' => $rule->blocked_category_slugs,
                    'global_time_restrictions' => $rule->global_time_restrictions,
                    'require_adult_approval' => $rule->require_adult_approval,
                    'has_adult_password' => !empty($rule->adult_override_password),
                    'blocked_domains_count' => $rule->blocked_domains_count,
                    'allowed_domains_count' => $rule->allowed_domains_count,
                    'devices_count' => $rule->devices_count,
                    'device_overrides_count' => $rule->device_overrides_count,
                    'has_time_restrictions' => $rule->hasTimeRestrictions(),
                    'is_currently_restricted' => $rule->isCurrentlyRestricted(),
                    'blocked_domains' => $rule->blockedDomains->map(function ($domain) {
                        return $domain->toApiArray();
                    }),
                    'allowed_domains' => $rule->allowedDomains->map(function ($domain) {
                        return $domain->toApiArray();
                    }),
                    'created_at' => $rule->created_at,
                    'updated_at' => $rule->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch family rule',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a new family rule
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'blocked_categories' => 'sometimes|array',
            'blocked_categories.*' => 'string|max:50',
            'global_time_restrictions' => 'sometimes|array',
            'require_adult_approval' => 'sometimes|boolean',
            'adult_override_password' => 'sometimes|nullable|string|min:4|max:20'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $ruleData = [
                'customer_id' => $customer->id,
                'name' => $request->name,
                'is_active' => true,
                'blocked_categories' => $request->blocked_categories ?? [],
                'global_time_restrictions' => $request->global_time_restrictions ?? null,
                'require_adult_approval' => $request->require_adult_approval ?? false,
            ];

            if ($request->adult_override_password) {
                $ruleData['adult_override_password'] = hash('sha256', $request->adult_override_password);
            }

            $rule = FamilyRule::create($ruleData);

            // Log the rule creation
            FamilyRuleActivityLog::logRuleCreated(
                $rule->id,
                'parent',
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Family rule created successfully',
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'is_active' => $rule->is_active,
                    'blocked_categories' => $rule->blocked_category_slugs,
                    'global_time_restrictions' => $rule->global_time_restrictions,
                    'require_adult_approval' => $rule->require_adult_approval,
                    'has_adult_password' => !empty($rule->adult_override_password),
                    'created_at' => $rule->created_at
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create family rule',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update an existing family rule
     */
    public function update(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
            'blocked_categories' => 'sometimes|array',
            'blocked_categories.*' => 'string|max:50',
            'global_time_restrictions' => 'sometimes|nullable|array',
            'require_adult_approval' => 'sometimes|boolean',
            'adult_override_password' => 'sometimes|nullable|string|min:4|max:20'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $updateData = $request->only([
                'name', 'is_active', 'blocked_categories', 
                'global_time_restrictions', 'require_adult_approval'
            ]);

            if ($request->has('adult_override_password')) {
                if ($request->adult_override_password) {
                    $updateData['adult_override_password'] = hash('sha256', $request->adult_override_password);
                } else {
                    $updateData['adult_override_password'] = null;
                }
            }

            $rule->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Family rule updated successfully',
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'is_active' => $rule->is_active,
                    'blocked_categories' => $rule->blocked_category_slugs,
                    'global_time_restrictions' => $rule->global_time_restrictions,
                    'require_adult_approval' => $rule->require_adult_approval,
                    'has_adult_password' => !empty($rule->adult_override_password),
                    'updated_at' => $rule->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update family rule',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete a family rule
     */
    public function destroy(Request $request, string $ruleId): JsonResponse
    {
        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $rule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Family rule deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete family rule',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add a blocked category to a family rule
     */
    public function addBlockedCategory(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'category_slug' => 'required|string|max:50'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $success = $rule->addBlockedCategory($request->category_slug);
            
            if ($success) {
                // Log the category blocking
                FamilyRuleActivityLog::logCategoryBlocked(
                    $rule->id,
                    $request->category_slug,
                    'parent',
                    $request->ip()
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Category blocked successfully',
                    'blocked_categories' => $rule->fresh()->blocked_category_slugs
                ]);
            } else {
                return response()->json([
                    'error' => 'Category is already blocked'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to block category',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove a blocked category from a family rule
     */
    public function removeBlockedCategory(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'category_slug' => 'required|string|max:50'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $success = $rule->removeBlockedCategory($request->category_slug);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Category unblocked successfully',
                    'blocked_categories' => $rule->fresh()->blocked_category_slugs
                ]);
            } else {
                return response()->json([
                    'error' => 'Category was not blocked'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to unblock category',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify adult override password
     */
    public function verifyAdultPassword(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        try {
            $customer = $request->attributes->get('customer');
            
            $rule = FamilyRule::forCustomer($customer->id)
                ->where('id', $ruleId)
                ->first();

            if (!$rule) {
                return response()->json([
                    'error' => 'Family rule not found'
                ], 404);
            }

            $isValid = $rule->verifyAdultPassword($request->password);

            return response()->json([
                'success' => true,
                'valid' => $isValid,
                'message' => $isValid ? 'Password is correct' : 'Password is incorrect'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to verify password',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}