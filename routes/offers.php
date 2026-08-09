<?php

use App\Http\Controllers\Operator\BenchmarkComparisonController;
use App\Http\Controllers\Operator\BenchmarkComparisonPublicationController;
use App\Http\Controllers\Operator\ProductOfferController;
use App\Http\Controllers\Operator\ProductOfferPublicationController;
use App\Http\Controllers\Operator\ProductOfferReplacementController;
use App\Http\Controllers\Operator\ProductOfferWithdrawalController;
use App\Http\Controllers\PublicProductOfferController;
use Illuminate\Support\Facades\Route;

Route::get('/offers/{productOffer:public_id}', [PublicProductOfferController::class, 'show'])
    ->middleware('throttle:public-offers')
    ->name('offers.show');

Route::prefix('operator/offers')
    ->name('operator.offers.')
    ->middleware(['auth', 'verified', 'throttle:operator-offers'])
    ->group(function () {
        Route::get('/', [ProductOfferController::class, 'index'])->name('index');
        Route::get('/create', [ProductOfferController::class, 'create'])->name('create');
        Route::post('/', [ProductOfferController::class, 'store'])->name('store');
        Route::get('/{productOffer}/edit', [ProductOfferController::class, 'edit'])->name('edit');
        Route::patch('/{productOffer}', [ProductOfferController::class, 'update'])->name('update');
        Route::post('/{productOffer}/withdraw', [ProductOfferWithdrawalController::class, 'store'])->name('withdrawals.store');
        Route::post('/{productOffer}/replacement', [ProductOfferReplacementController::class, 'store'])->name('replacements.store');
        Route::post('/{productOffer}/benchmarks', [BenchmarkComparisonController::class, 'store'])->name('benchmarks.store');
        Route::post('/{productOffer}/benchmarks/{benchmarkComparison}/publish', [BenchmarkComparisonPublicationController::class, 'store'])
            ->scopeBindings()
            ->name('benchmarks.publications.store');
        Route::post('/{productOffer}/publish', [ProductOfferPublicationController::class, 'store'])->name('publications.store');
    });
