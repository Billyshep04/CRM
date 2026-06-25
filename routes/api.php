<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminInvoiceSettingController;
use App\Http\Controllers\AdminMailSettingController;
use App\Http\Controllers\AdminProposalFormController;
use App\Http\Controllers\AdminStaffUserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BrandSettingController;
use App\Http\Controllers\CostController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WebsiteController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'request']);
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
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
        Route::get('jobs/{job}/photos', [JobController::class, 'photos']);
        Route::post('jobs/{job}/photos', [JobController::class, 'uploadPhotos']);
        Route::get('jobs/{job}/photos/download-all', [JobController::class, 'downloadAllPhotos']);
        Route::get('jobs/{job}/photos/{file}/download', [JobController::class, 'downloadPhoto']);
        Route::post('subscription-months/{subscriptionMonth}/payment', [SubscriptionController::class, 'updateMonthPaymentById']);
        Route::get('subscriptions/{subscription}/months', [SubscriptionController::class, 'months']);
        Route::patch('subscriptions/{subscription}/months/{subscriptionMonth}', [SubscriptionController::class, 'updateMonth']);
        Route::post('subscriptions/{subscription}/months/{subscriptionMonth}/payment', [SubscriptionController::class, 'updateMonthPayment']);
        Route::apiResource('subscriptions', SubscriptionController::class);
        Route::get('proposal-forms', [AdminProposalFormController::class, 'show']);
        Route::apiResource('proposals', ProposalController::class);
        Route::post('proposals/{proposal}/send', [ProposalController::class, 'send']);
        Route::get('proposals/{proposal}/download', [ProposalController::class, 'download']);
        Route::post('proposals/{proposal}/new-version', [ProposalController::class, 'createNewVersion']);
        Route::patch('proposals/{proposal}/status', [ProposalController::class, 'updateStatus']);
        Route::post('proposals/{proposal}/status', [ProposalController::class, 'updateStatus']);
        Route::apiResource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/payment', [InvoiceController::class, 'updatePaymentStatus']);
        Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'updatePaymentStatus']);
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download']);
        Route::apiResource('websites', WebsiteController::class);
        Route::get('stats/revenue', [StatsController::class, 'revenue']);
        Route::get('stats/profit-weekly', [StatsController::class, 'weeklyProfit']);
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::post('brand/logo', [BrandSettingController::class, 'updateLogo']);
        Route::patch('customers/{customer}/archive', [CustomerController::class, 'archive']);
        Route::patch('customers/{customer}/unarchive', [CustomerController::class, 'unarchive']);
        Route::get('admin/staff-users', [AdminStaffUserController::class, 'index']);
        Route::post('admin/staff-users', [AdminStaffUserController::class, 'store']);
        Route::get('admin/stats/monthly-finance', [StatsController::class, 'monthlyFinance']);
        Route::get('admin/invoice-settings', [AdminInvoiceSettingController::class, 'show']);
        Route::put('admin/invoice-settings', [AdminInvoiceSettingController::class, 'update']);
        Route::get('admin/mail-settings', [AdminMailSettingController::class, 'show']);
        Route::put('admin/mail-settings', [AdminMailSettingController::class, 'update']);
        Route::get('admin/proposal-forms', [AdminProposalFormController::class, 'show']);
        Route::put('admin/proposal-forms', [AdminProposalFormController::class, 'update']);
    });

    Route::prefix('portal')->middleware('role:customer')->group(function (): void {
        Route::get('jobs', [PortalController::class, 'jobs']);
        Route::get('subscriptions', [PortalController::class, 'subscriptions']);
        Route::get('invoices', [PortalController::class, 'invoices']);
        Route::get('invoices/{invoice}', [PortalController::class, 'invoice']);
        Route::patch('invoices/{invoice}/payment', [PortalController::class, 'updateInvoicePayment']);
        Route::post('invoices/{invoice}/payment', [PortalController::class, 'updateInvoicePayment']);
        Route::get('invoices/{invoice}/download', [PortalController::class, 'downloadInvoice']);
        Route::get('proposals', [PortalController::class, 'proposals']);
        Route::get('proposals/{proposal}', [PortalController::class, 'proposal']);
        Route::patch('proposals/{proposal}/status', [PortalController::class, 'updateProposalStatus']);
        Route::post('proposals/{proposal}/status', [PortalController::class, 'updateProposalStatus']);
        Route::get('proposals/{proposal}/download', [PortalController::class, 'downloadProposal']);
        Route::post('support', [PortalController::class, 'support']);
        Route::get('websites', [PortalController::class, 'websites']);
    });
});
