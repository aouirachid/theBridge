<?php

namespace App\Http\Controllers;

use App\Actions\ProductOffers\ShowPublicProductOfferAction;
use Inertia\Inertia;
use Inertia\Response;

class PublicProductOfferController extends Controller
{
    /**
     * Show the public, privacy-safe breakdown for one offer.
     */
    public function show(string $publicId): Response
    {
        $data = app(ShowPublicProductOfferAction::class)->execute($publicId);

        return Inertia::render('offers/show', [
            'offer' => $data['offer'],
            'currentComparison' => $data['currentComparison'],
            'freshComparisonUnavailable' => $data['freshComparisonUnavailable'],
            'comparisonHistory' => $data['comparisonHistory'],
            'order' => $data['order'],
        ]);
    }
}
