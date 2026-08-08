<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\RecordBenchmarkComparisonAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreBenchmarkComparisonRequest;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BenchmarkComparisonController extends Controller
{
    /**
     * Record one benchmark observation without touching public state.
     */
    public function store(StoreBenchmarkComparisonRequest $request, ProductOffer $productOffer): RedirectResponse
    {
        app(RecordBenchmarkComparisonAction::class)->execute($request->user(), $productOffer, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Benchmark recorded.')]);

        return to_route('operator.offers.edit', $productOffer);
    }
}
