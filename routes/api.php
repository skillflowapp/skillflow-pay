<?php

use App\Http\Controllers\Api\MalipoWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('webhooks/malipo', MalipoWebhookController::class);

    Route::middleware('firebase')->group(function (): void {
        Route::post('payments/content', [PaymentController::class, 'content']);
        Route::post('payments/ai-subscription', [PaymentController::class, 'aiSubscription']);
        Route::post('payments/ai-credits', [PaymentController::class, 'aiCredits']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/access', [PaymentController::class, 'access']);
        Route::post('payments/{paymentOrder}/cancel', [PaymentController::class, 'cancel']);
        Route::get('payments/{paymentOrder}', [PaymentController::class, 'show']);

        Route::get('wallet', WalletController::class);

        Route::get('withdrawals', [WithdrawalController::class, 'index']);
        Route::post('withdrawals/teacher', [WithdrawalController::class, 'teacher']);
        Route::post('withdrawals/referral', [WithdrawalController::class, 'referral']);
    });
});
