<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\PublishBenchmarkComparisonAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\PublishBenchmarkComparisonRequest;
use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class BenchmarkComparisonPublicationController extends Controller
{
    /**
     * Publish a reviewed benchmark observation for an unchanged published offer.
     */
    public function store(
        PublishBenchmarkComparisonRequest $request,
        ProductOffer $productOffer,
        BenchmarkComparison $benchmarkComparison,
    ): RedirectResponse {
        try {
            app(PublishBenchmarkComparisonAction::class)->execute($request->user(), $productOffer, $benchmarkComparison);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'benchmark_comparison_id' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Benchmark published.')]);

        return to_route('operator.offers.edit', $productOffer);
    }
}
