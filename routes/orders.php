<?php

use App\Http\Controllers\Operator\OrderCancellationController;
use App\Http\Controllers\Operator\OrderController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\PublicOrderReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/offers/{productOffer:public_id}/orders/review', [PublicOrderReviewController::class, 'store'])
    ->middleware('throttle:public-order-previews')
    ->name('offers.orders.review');

Route::post('/offers/{productOffer:public_id}/orders', [PublicOrderController::class, 'store'])
    ->middleware('throttle:public-order-confirmations')
    ->name('offers.orders.store');

Route::prefix('operator/orders')
    ->middleware(['auth', 'verified', 'throttle:operator-orders'])
    ->name('operator.orders.')
    ->group(function (): void {
        Route::get('/', [OrderController::class, 'index'])
            ->name('index');

        Route::get('/{order:public_id}', [OrderController::class, 'show'])
            ->name('show');

        Route::post('/{order:public_id}/cancel', [OrderCancellationController::class, 'store'])
            ->name('cancellations.store');
    });
