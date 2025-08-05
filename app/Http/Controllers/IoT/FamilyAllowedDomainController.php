<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\FamilyAllowedDomain;
use App\Models\IoT\FamilyRule;
use App\Models\IoT\FamilyRuleActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FamilyAllowedDomainController extends Controller
{
    /**
     * Get all allowed domains for a family rule
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

            $query = FamilyAllowedDomain::forFamilyRule($ruleId);

            // Apply filters
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
                'error' => 'Failed to fetch allowed domains',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific allowed domain
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

            $domain = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Allowed domain not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'domain' => $domain->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch allowed domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Add a new allowed domain to a family rule
     */
    public function store(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:255',
            'reason' => 'sometimes|nullable|string|max:500'
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

            // Check if domain is already allowed
            $existingDomain = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->byDomain($request->domain)
                ->first();

            if ($existingDomain) {
                return response()->json([
                    'error' => 'Domain is already in the allowed list'
                ], 400);
            }

            $domainData = [
                'family_rule_id' => $ruleId,
                'domain' => $request->domain,
                'reason' => $request->reason,
                'added_by' => 'parent'
            ];

            $allowedDomain = FamilyAllowedDomain::create($domainData);

            // Log the domain allowing
            FamilyRuleActivityLog::logDomainAllowed(
                $ruleId,
                $request->domain,
                'parent',
                $request->ip()
            );

            return response()->json([
                'success' => true,
                'message' => 'Domain added to allowed list successfully',
                'domain' => $allowedDomain->toApiArray()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to allow domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update an allowed domain
     */
    public function update(Request $request, string $ruleId, string $domainId): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|nullable|string|max:500'
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

            $domain = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Allowed domain not found'
                ], 404);
            }

            $updateData = [];

            if ($request->has('reason')) {
                $updateData['reason'] = $request->reason;
            }

            $domain->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Allowed domain updated successfully',
                'domain' => $domain->fresh()->toApiArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update allowed domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove an allowed domain
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

            $domain = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->where('id', $domainId)
                ->first();

            if (!$domain) {
                return response()->json([
                    'error' => 'Allowed domain not found'
                ], 404);
            }

            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => 'Allowed domain removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove allowed domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Bulk add domains to allowed list
     */
    public function bulkStore(Request $request, string $ruleId): JsonResponse
    {
        $request->validate([
            'domains' => 'required|array|min:1|max:100',
            'domains.*' => 'required|string|max:255',
            'reason' => 'sometimes|nullable|string|max:500'
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

            $added = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->domains as $domainName) {
                // Check if domain is already allowed
                $existingDomain = FamilyAllowedDomain::forFamilyRule($ruleId)
                    ->byDomain($domainName)
                    ->first();

                if ($existingDomain) {
                    $skipped++;
                    continue;
                }

                try {
                    FamilyAllowedDomain::create([
                        'family_rule_id' => $ruleId,
                        'domain' => $domainName,
                        'reason' => $request->reason,
                        'added_by' => 'parent'
                    ]);

                    // Log the domain allowing
                    FamilyRuleActivityLog::logDomainAllowed(
                        $ruleId,
                        $domainName,
                        'parent',
                        $request->ip()
                    );

                    $added++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to allow {$domainName}: " . $e->getMessage();
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
                'error' => 'Failed to bulk allow domains',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if a domain is allowed
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
            $isAllowed = $rule->isDomainAllowed($domain);

            $allowedDomain = null;
            if ($isAllowed) {
                $allowedDomain = FamilyAllowedDomain::forFamilyRule($ruleId)
                    ->where(function ($query) use ($domain) {
                        $query->where('domain', $domain)
                              ->orWhere('domain', '*.' . $domain);
                    })
                    ->first();
            }

            return response()->json([
                'success' => true,
                'domain' => $domain,
                'is_allowed' => $isAllowed,
                'allowed_domain' => $allowedDomain ? $allowedDomain->toApiArray() : null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check domain',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get summary of allowed domains
     */
    public function summary(Request $request, string $ruleId): JsonResponse
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

            $totalDomains = FamilyAllowedDomain::forFamilyRule($ruleId)->count();
            $domainsAddedByParent = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->addedBy('parent')
                ->count();
            $domainsAddedByAdmin = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->addedBy('admin')
                ->count();

            // Recent activity
            $recentDomains = FamilyAllowedDomain::forFamilyRule($ruleId)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($domain) {
                    return $domain->toApiArray();
                });

            return response()->json([
                'success' => true,
                'summary' => [
                    'total_domains' => $totalDomains,
                    'domains_added_by_parent' => $domainsAddedByParent,
                    'domains_added_by_admin' => $domainsAddedByAdmin,
                    'recent_domains' => $recentDomains
                ],
                'rule' => [
                    'id' => $rule->id,
                    'name' => $rule->name
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch allowed domains summary',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}