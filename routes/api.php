<?php

use App\Http\Controllers\Categories\CategoriesController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Contacts\ContactsController;
use App\Http\Controllers\Expenses\ExpensesController;
use App\Http\Controllers\Products\ProductsController;
use App\Http\Controllers\Taxes\TaxesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import IoT Controllers
use App\Http\Controllers\IoT\DeviceController;
use App\Http\Controllers\IoT\MetricsController;
use App\Http\Controllers\IoT\WebSocketController;
use App\Http\Controllers\IoT\DashboardController;
use App\Http\Controllers\IoT\DeviceProfilesController;
use App\Http\Controllers\IoT\FamilyDevicesController;

/*
|--------------------------------------------------------------------------
| Existing Business Application Routes (Sanctum Auth)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Ruta para crear empresa (no requiere la verificación de empresa.required)
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies', [CompanyController::class, 'index']);

    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
    });

    Route::middleware('company.required')->group(function () {
        // Company-specific endpoints
        Route::prefix('companies')->group(function () {
            Route::get('/{id}', [CompanyController::class, 'show']);
            Route::put('/{id}', [CompanyController::class, 'update']);
            Route::delete('/{id}', [CompanyController::class, 'destroy']);
        });

        // Resource routes
        Route::apiResource('contacts', ContactsController::class);
        Route::apiResource('categories', CategoriesController::class);
        Route::apiResource('products', ProductsController::class);
        Route::apiResource('expenses', ExpensesController::class);
        Route::apiResource('taxes', TaxesController::class);
    });

    Route::middleware('role:super-admin')->group(function () {
        Route::get('/available-owners', [CompanyController::class, 'availableOwners']);
    });
});

/*
|--------------------------------------------------------------------------
| IoT Device Routes (Separate Authentication)
|--------------------------------------------------------------------------
| These routes use device authentication, not Sanctum
| Available at: /api/iot/*
*/

Route::prefix('iot')->group(function () {
    
    // Test routes (no auth required) - for debugging
    Route::get('/test', function () {
        return response()->json([
            'message' => 'IoT routes are working!',
            'timestamp' => now()->toISOString(),
            'server' => 'Laravel Oriana IoT API',
            'version' => '1.0.0'
        ]);
    });

    Route::get('/info', function () {
        return response()->json([
            'service' => 'Oriana IoT API',
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'routes_loaded' => true,
            'timestamp' => now()->toISOString(),
            'available_endpoints' => [
                'GET /api/iot/test - Test endpoint',
                'GET /api/iot/info - API information',
                'GET /api/iot/device/health - Device health check',
                'POST /api/iot/device/heartbeat - Device heartbeat',
                'POST /api/iot/device/metrics/system - System metrics',
                'POST /api/iot/device/metrics/network - Network metrics',
                'POST /api/iot/device/metrics/security - Security events',
            ]
        ]);
    });

    // Device routes (require device authentication via middleware)
    Route::prefix('device')->middleware('device.auth')->group(function () {
        
        // Basic device endpoints
        Route::get('/health', [DeviceController::class, 'health']);
        Route::get('/info', [DeviceController::class, 'getInfo']);
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);
        
        // Metrics submission endpoints
        Route::post('/metrics/network', [MetricsController::class, 'storeNetworkData']);
        Route::post('/metrics/system', [MetricsController::class, 'storeSystemData']);
        Route::post('/metrics/security', [MetricsController::class, 'storeSecurityEvent']);
        Route::post('/metrics/bulk', [MetricsController::class, 'storeBulkMetrics']);
    });

    // WebSocket endpoint for devices
    Route::get('/device/ws', [WebSocketController::class, 'handleDeviceConnection'])
        ->middleware('device.auth');

    // Dashboard API routes (for React frontend - will use JWT/Sanctum later)
    Route::prefix('dashboard')
    ->middleware(['customer.auth']) // Apply customer resolution to all routes
    ->group(function () {
        Route::get('/devices', [DashboardController::class, 'devices']);
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/security-events', [DashboardController::class, 'securityEvents']);
        // Device-specific routes with ownership verification
        Route::middleware(['customer.owns:device'])->group(function () {
            Route::get('/devices/{device}', [DashboardController::class, 'device']);
            Route::get('/devices/{device}/metrics', [DashboardController::class, 'deviceMetrics']);
            // Execute device actions
            Route::post('/devices/{device}/actions', [DashboardController::class, 'deviceAction']);

        });

        Route::get('/device-profiles', [DeviceProfilesController::class, 'index']);

        Route::prefix('family-devices')->group(function () {
            Route::get('/', [FamilyDevicesController::class, 'index']);
            Route::get('/unidentified', [FamilyDevicesController::class, 'unidentified']);
            Route::get('/{deviceId}', [FamilyDevicesController::class, 'show']);
            Route::put('/{deviceId}/identify', [FamilyDevicesController::class, 'identify']);
            Route::put('/{deviceId}/profile', [FamilyDevicesController::class, 'updateProfile']);
        });
    });
});
