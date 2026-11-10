<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

// Public pages linked from Email Marketing campaigns. The per-recipient token is the only key.
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('email/unsubscribe/{token}', [\App\Http\Controllers\EmailMarketingPublicController::class, 'showUnsubscribe'])->name('email-marketing.unsubscribe');
    Route::post('email/unsubscribe/{token}', [\App\Http\Controllers\EmailMarketingPublicController::class, 'unsubscribe']);
    Route::get('email/view/{token}', [\App\Http\Controllers\EmailMarketingPublicController::class, 'view'])->name('email-marketing.view');
});
