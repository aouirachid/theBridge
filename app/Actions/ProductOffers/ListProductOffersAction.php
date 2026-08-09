<?php

namespace App\Actions\ProductOffers;

use App\Models\ProductOffer;
use App\Support\Pricing\OfferPriceCalculator;

/**
 * List the fifty most recently created offers as operator summaries.
 */
final class ListProductOffersAction
{
    /**
     * @return array<int, array{
     *     id: int,
     *     publicId: string,
     *     crop: string,
     *     origin: string,
     *     status: string,
     *     finalPrice: string,
     *     publishedAt: string|null,
     *     canEdit: bool,
     *     canPublish: bool,
     *     canReplace: bool,
     *     publicUrlAvailable: bool,
     * }>
     */
    public function execute(): array
    {
        return ProductOffer::query()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ProductOffer $offer): array => [
                'id' => $offer->id,
                'publicId' => $offer->public_id,
                'crop' => $offer->crop,
                'origin' => $offer->origin,
                'status' => $offer->status(),
                'finalPrice' => OfferPriceCalculator::formatMoney($offer->final_price_minor),
                'publishedAt' => $offer->published_at?->toIso8601String(),
                'canEdit' => $offer->isDraft(),
                'canPublish' => $offer->isDraft(),
                'canReplace' => $offer->isPublished() && $offer->superseded_at === null,
                'publicUrlAvailable' => $offer->isPublished(),
            ])
            ->all();
    }
}
