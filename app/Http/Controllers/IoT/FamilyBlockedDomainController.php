<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\FamilyBlockedDomain;
use App\Models\IoT\FamilyRule;
use App\Models\IoT\FilteringCategory;
use App\Models\IoT\FamilyRuleActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FamilyBlockedDomainController extends Controller
{
    /**
     * Get all blocked domains for a family rule
     */
    public function index(Request $request, string $ruleId): JsonResponse
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

            $query = FamilyBlockedDomain::forFamilyRule($ruleId)
                ->with('category');

            // Apply filters
            if ($request->has('category')) {
                $query->byCategory($request->category);
            }

            if ($request->has('severity')) {
                $query->bySeverity($request->severity);
            }

            if ($request->has('added_by')) {
                $query->addedBy($request->added_by);
            }

            if ($request->has('search')) {
                $query->where('domain', 'like', '%' . $request->search . '%');
            }

            $domains = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 50))
                ->through(function ($domain) {
                    return $domain->toApiArray();
                });

            return response()->json([
                'success' => true,
                'domains' => $domains->items(),
                'pagination' => [
                    'current_page' => $domains->currentPage(),
                    'per_page' => $domains->perPage(),
                    'total' => $domains->total(),
                    'last_page' => $domains->lastPage(),
                ],
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch blocked domains',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific blocked domain
     */
    public function show(Request $request, string $ruleId, string $domainId): JsonResponse
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

            $domain = FamilyBlockedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->with('category')
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Blocked domain not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'domain' => $domain->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch blocked domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add a new blocked domain to a family rule
     */
    public function store(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'category_id' => 'sometimes|nullable|uuid|exists:filtering_categories,id',
            'reason' => 'sometimes|nullable|string|max:500',
            'severity' => 'sometimes|in:low,medium,high,critical'
        ]);

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

            // Check if domain is already blocked
            $existingDomain = FamilyBlockedDomain::forFamilyRule($ruleId)
                ->byDomain($request->domain)
                ->first();

            if ($existingDomain) {
                return response()->json([
                    'error' => 'Domain is already blocked'
                ], 400);
            }

            // Get category information if provided
            $category = null;
            $categorySlug = null;
            $severity = $request->get('severity', 'medium');

            if ($request->category_id) {
                $category = FilteringCategory::find($request->category_id);
                if ($category) {
                    $categorySlug = $category->slug;
                    $severity = $request->get('severity', $category->default_severity);
                }
            }

            $domainData = [
                'family_rule_id' => $ruleId,
                'domain' => $request->domain,
                'category_id' => $request->category_id,
                'category_slug' => $categorySlug,
                'reason' => $request->reason,
                'added_by' => 'parent',
                'severity' => $severity
            ];

            $blockedDomain = FamilyBlockedDomain::create($domainData);

            // Log the domain blocking
            FamilyRuleActivityLog::logDomainBlocked(
                $ruleId,
                $request->domain,
                'parent',
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain blocked successfully',
                'domain' => $blockedDomain->toApiArray()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to block domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update a blocked domain
     */
    public function update(Request $request, string $ruleId, string $domainId): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|nullable|uuid|exists:filtering_categories,id',
            'reason' => 'sometimes|nullable|string|max:500',
            'severity' => 'sometimes|in:low,medium,high,critical'
        ]);

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

            $domain = FamilyBlockedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Blocked domain not found'
                ], 404);
            }

            $updateData = [];

            if ($request->has('category_id')) {
                $updateData['category_id'] = $request->category_id;
                
                if ($request->category_id) {
                    $category = FilteringCategory::find($request->category_id);
                    if ($category) {
                        $updateData['category_slug'] = $category->slug;
                        // Update severity to category default if not explicitly provided
                        if (!$request->has('severity')) {
                            $updateData['severity'] = $category->default_severity;
                        }
                    }
                } else {
                    $updateData['category_slug'] = null;
                }
            }

            if ($request->has('reason')) {
                $updateData['reason'] = $request->reason;
            }

            if ($request->has('severity')) {
                $updateData['severity'] = $request->severity;
            }

            $domain->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Blocked domain updated successfully',
                'domain' => $domain->fresh()->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update blocked domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove a blocked domain
     */
    public function destroy(Request $request, string $ruleId, string $domainId): JsonResponse
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

            $domain = FamilyBlockedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Blocked domain not found'
                ], 404);
            }

            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => 'Blocked domain removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove blocked domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk add domains to blocked list
     */
    public function bulkStore(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'domains' => 'required|array|min:1|max:100',
            'domains.*' => 'required|string|max:255',
            'category_id' => 'sometimes|nullable|uuid|exists:filtering_categories,id',
            'reason' => 'sometimes|nullable|string|max:500',
            'severity' => 'sometimes|in:low,medium,high,critical'
        ]);

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

            // Get category information if provided
            $category = null;
            $categorySlug = null;
            $severity = $request->get('severity', 'medium');

            if ($request->category_id) {
                $category = FilteringCategory::find($request->category_id);
                if ($category) {
                    $categorySlug = $category->slug;
                    $severity = $request->get('severity', $category->default_severity);
                }
            }

            $added = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->domains as $domainName) {
                // Check if domain is already blocked
                $existingDomain = FamilyBlockedDomain::forFamilyRule($ruleId)
                    ->byDomain($domainName)
                    ->first();

                if ($existingDomain) {
                    $skipped++;
                    continue;
                }

                try {
                    FamilyBlockedDomain::create([
                        'family_rule_id' => $ruleId,
                        'domain' => $domainName,
                        'category_id' => $request->category_id,
                        'category_slug' => $categorySlug,
                        'reason' => $request->reason,
                        'added_by' => 'parent',
                        'severity' => $severity
                    ]);

                    // Log the domain blocking
                    FamilyRuleActivityLog::logDomainBlocked(
                        $ruleId,
                        $domainName,
                        'parent',
                        $request->ip()
                    );

                    $added++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to block {$domainName}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk operation completed: {$added} domains added, {$skipped} skipped",
                'summary' => [
                    'added' => $added,
                    'skipped' => $skipped,
                    'errors' => count($errors)
                ],
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to bulk block domains',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if a domain is blocked
     */
    public function checkDomain(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255'
        ]);

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

            $domain = $request->domain;
            $isBlocked = $rule->isDomainBlocked($domain);

            $blockedDomain = null;
            if ($isBlocked) {
                $blockedDomain = FamilyBlockedDomain::forFamilyRule($ruleId)
                    ->where(function ($query) use ($domain) {
                        $query->where('domain', $domain)
                              ->orWhere('domain', '*.' . $domain);
                    })
                    ->with('category')
                    ->first();
            }

            return response()->json([
                'success' => true,
                'domain' => $domain,
                'is_blocked' => $isBlocked,
                'blocked_domain' => $blockedDomain ? $blockedDomain->toApiArray() : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}