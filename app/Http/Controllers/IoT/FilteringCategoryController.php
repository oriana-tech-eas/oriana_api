<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\IoT\FilteringCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FilteringCategoryController extends Controller
{
    /**
     * Get all active filtering categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $categories = FilteringCategory::active()
                ->withCount('blockedDomains')
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name' => $category->name,
                        'description' => $category->description,
                        'default_severity' => $category->default_severity,
                        'severity_color' => $category->severity_color,
                        'icon' => $category->icon,
                        'is_active' => $category->is_active,
                        'blocked_domains_count' => $category->blocked_domains_count,
                        'is_high_risk' => $category->isHighRisk(),
                        'display_name' => $category->display_name
                    ];
                });

            return response()->json([
                'success' => true,
                'categories' => $categories,
                'total' => $categories->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch filtering categories',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific filtering category by ID or slug
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        try {
            // Try to find by ID first, then by slug
            $category = FilteringCategory::where('id', $identifier)
                ->orWhere('slug', $identifier)
                ->withCount('blockedDomains')
                ->first();

            if (!$category) {
                return response()->json([
                    'error' => 'Filtering category not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    'default_severity' => $category->default_severity,
                    'severity_color' => $category->severity_color,
                    'icon' => $category->icon,
                    'is_active' => $category->is_active,
                    'blocked_domains_count' => $category->blocked_domains_count,
                    'is_high_risk' => $category->isHighRisk(),
                    'display_name' => $category->display_name,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch filtering category',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get categories by severity level
     */
    public function getBySeverity(Request $request, string $severity): JsonResponse
    {
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        
        if (!in_array($severity, $validSeverities)) {
            return response()->json([
                'error' => 'Invalid severity level',
                'valid_severities' => $validSeverities
            ], 400);
        }

        try {
            $categories = FilteringCategory::active()
                ->bySeverity($severity)
                ->withCount('blockedDomains')
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name' => $category->name,
                        'description' => $category->description,
                        'default_severity' => $category->default_severity,
                        'severity_color' => $category->severity_color,
                        'icon' => $category->icon,
                        'blocked_domains_count' => $category->blocked_domains_count,
                        'is_high_risk' => $category->isHighRisk()
                    ];
                });

            return response()->json([
                'success' => true,
                'severity' => $severity,
                'categories' => $categories,
                'total' => $categories->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch categories by severity',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get high-risk categories (high and critical severity)
     */
    public function getHighRisk(Request $request): JsonResponse
    {
        try {
            $categories = FilteringCategory::active()
                ->whereIn('default_severity', ['high', 'critical'])
                ->withCount('blockedDomains')
                ->orderBy('default_severity', 'desc')
                ->orderBy('name')
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'slug' => $category->slug,
                        'name' => $category->name,
                        'description' => $category->description,
                        'default_severity' => $category->default_severity,
                        'severity_color' => $category->severity_color,
                        'icon' => $category->icon,
                        'blocked_domains_count' => $category->blocked_domains_count
                    ];
                });

            return response()->json([
                'success' => true,
                'categories' => $categories,
                'total' => $categories->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch high-risk categories',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}