<?php

use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\PublicOrderReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/offers/{productOffer:public_id}/orders/review', [PublicOrderReviewController::class, 'store'])
    ->middleware('throttle:public-order-previews')
    ->name('offers.orders.review');

Route::post('/offers/{productOffer:public_id}/orders', [PublicOrderController::class, 'store'])
    ->middleware('throttle:public-order-confirmations')
    ->name('offers.orders.store');
