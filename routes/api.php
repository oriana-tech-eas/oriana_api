<?php

use App\Http\Controllers\Categories\CategoriesController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Contacts\ContactsController;
use App\Http\Controllers\Expenses\ExpensesController;
use App\Http\Controllers\Products\ProductsController;
use App\Http\Controllers\Taxes\TaxesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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