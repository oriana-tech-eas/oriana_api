<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CompanyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('login');

Route::middleware(['auth:sanctum'])->group(function () {
    // Ruta para crear empresa (no requiere la verificación de empresa.required)
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies', [CompanyController::class, 'index']);
});

Route::group(['middleware' => ['auth:sanctum', 'company.required']], function () {
    Route::apiResource('contacts', 'App\Http\Controllers\Contacts\ContactsController');
    // Route::apiResource('companies', 'App\Http\Controllers\Customers\CompaniesController');
    Route::apiResource('categories', 'App\Http\Controllers\Categories\CategoriesController');
    Route::apiResource('products', 'App\Http\Controllers\Products\ProductsController');
    Route::apiResource('expenses', 'App\Http\Controllers\Expenses\ExpensesController');
    Route::apiResource('taxes', 'App\Http\Controllers\Taxes\TaxesController');

    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::put('/companies/{id}', [CompanyController::class, 'update']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
});

// Ruta solo para super-admin
Route::middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('/available-owners', [CompanyController::class, 'availableOwners']);
});
