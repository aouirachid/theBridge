<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\PublishProductOfferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\PublishProductOfferRequest;
use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class ProductOfferPublicationController extends Controller
{
    /**
     * Publish one draft with its initial recorded benchmark.
     */
    public function store(PublishProductOfferRequest $request, ProductOffer $productOffer): RedirectResponse
    {
        $benchmark = BenchmarkComparison::query()
            ->findOrFail($request->integer('benchmark_comparison_id'));

        try {
            app(PublishProductOfferAction::class)->execute($request->user(), $productOffer, $benchmark);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'benchmark_comparison_id' => $exception->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer published.')]);

        return to_route('operator.offers.edit', $productOffer);
    }
}
