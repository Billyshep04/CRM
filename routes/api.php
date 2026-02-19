<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BrandSettingController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WebsiteController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('brand/logo', [BrandSettingController::class, 'logo']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/account/profile', [AccountController::class, 'updateProfile']);
    Route::put('/account/password', [AccountController::class, 'updatePassword']);

    Route::get('preferences', [UserPreferenceController::class, 'show']);
    Route::put('preferences', [UserPreferenceController::class, 'update']);

    Route::get('brand', [BrandSettingController::class, 'show']);

    Route::middleware('role:admin,staff')->group(function (): void {
        Route::apiResource('costs', CostController::class);
        Route::get('costs/{cost}/receipt', [CostController::class, 'downloadReceipt']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('jobs', JobController::class);
        Route::post('subscription-months/{subscriptionMonth}/payment', [SubscriptionController::class, 'updateMonthPaymentById']);
        Route::get('subscriptions/{subscription}/months', [SubscriptionController::class, 'months']);
        Route::patch('subscriptions/{subscription}/months/{subscriptionMonth}', [SubscriptionController::class, 'updateMonth']);
        Route::post('subscriptions/{subscription}/months/{subscriptionMonth}/payment', [SubscriptionController::class, 'updateMonthPayment']);
        Route::apiResource('subscriptions', SubscriptionController::class);
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download']);
        Route::apiResource('websites', WebsiteController::class);
        Route::get('stats/revenue', [StatsController::class, 'revenue']);
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::post('brand/logo', [BrandSettingController::class, 'updateLogo']);
    });

    Route::prefix('portal')->middleware('role:customer')->group(function (): void {
        Route::get('jobs', [PortalController::class, 'jobs']);
        Route::get('subscriptions', [PortalController::class, 'subscriptions']);
        Route::get('invoices', [PortalController::class, 'invoices']);
        Route::get('invoices/{invoice}', [PortalController::class, 'invoice']);
        Route::get('invoices/{invoice}/download', [PortalController::class, 'downloadInvoice']);
        Route::get('websites', [PortalController::class, 'websites']);
    });
});
