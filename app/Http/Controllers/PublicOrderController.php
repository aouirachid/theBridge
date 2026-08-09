<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrderAction;
use App\Http\Requests\Public\StoreOrderRequest;
use App\Models\ProductOffer;
use Illuminate\Http\JsonResponse;

class PublicOrderController extends Controller
{
    /**
     * Confirm one B2C or B2B order and return the safe confirmation.
     */
    public function store(StoreOrderRequest $request, ProductOffer $productOffer): JsonResponse
    {
        $confirmation = app(CreateOrderAction::class)->execute(
            (string) $productOffer->public_id,
            $request->validated(),
        );

        return response()->json($confirmation);
    }
}
