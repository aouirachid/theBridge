<?php

namespace App\Http\Controllers\Operator;

use App\Actions\ProductOffers\WithdrawProductOfferAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\WithdrawProductOfferRequest;
use App\Models\ProductOffer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductOfferWithdrawalController extends Controller
{
    /**
     * Withdraw a published offer one-way.
     */
    public function store(WithdrawProductOfferRequest $request, ProductOffer $productOffer): RedirectResponse
    {
        app(WithdrawProductOfferAction::class)->execute($request->user(), $productOffer);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Offer withdrawn.')]);

        return to_route('operator.offers.index');
    }
}
