<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\CreateReplacementOfferDraftAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\CreateProductOfferReplacementRequest;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductOfferReplacementController extends Controller
{
    /**
     * Clone a published offer into a linked editable draft.
     */
    public function store(CreateProductOfferReplacementRequest $request, ProductOffer $productOffer): RedirectResponse
    {
        $draft = app(CreateReplacementOfferDraftAction::class)->execute($request->user(), $productOffer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Replacement draft created.')]);

        return to_route('operator.offers.edit', $draft);
    }
}
