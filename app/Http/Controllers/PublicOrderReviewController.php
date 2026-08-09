<?php

namespace App\Http\Controllers;

use App\Actions\Orders\ReviewOrderAction;
use App\Http\Requests\Public\ReviewOrderRequest;
use App\Models\ProductOffer;
use Illuminate\Http\JsonResponse;

class PublicOrderReviewController extends Controller
{
    /**
     * Produce the bounded, privacy-safe review for one order attempt.
     */
    public function store(ReviewOrderRequest $request, ProductOffer $productOffer): JsonResponse
    {
        $review = app(ReviewOrderAction::class)->execute(
            (string) $productOffer->public_id,
            $request->validated(),
        );

        return response()->json($review);
    }
}
