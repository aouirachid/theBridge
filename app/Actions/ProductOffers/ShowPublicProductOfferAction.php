<?php

namespace App\Actions\ProductOffers;

use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Support\Pricing\OfferPriceCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Load the public, privacy-safe breakdown for one offer.
 *
 * The visibility decision uses one captured clock value: drafts, withdrawn
 * offers, and superseded offers older than thirty days all resolve to the same
 * not-found outcome. Only allowlisted fields are returned, and the "current"
 * comparison is only exposed while its observation is at most twenty-four hours
 * old.
 */
final class ShowPublicProductOfferAction
{
    /**
     * @return array{
     *     offer: array{
     *         publicId: string,
     *         crop: string,
     *         origin: string,
     *         availableQuantityKg: string,
     *         availabilityStartsAt: string,
     *         availabilityEndsAt: string,
     *         farmerPayment: array{minor: int, formatted: string},
     *         costs: array<int, array{code: string|null, name: string, amount: array{minor: int, formatted: string}}>,
     *         platformMargin: array{minor: int, formatted: string},
     *         finalPrice: array{minor: int, formatted: string},
     *         farmerSharePercentage: string,
     *         publishedAt: string,
     *         supersededAt: string|null,
     *         isSuperseded: bool,
     *         replacesPublicId: string|null,
     *         replacementPublicId: string|null,
     *     },
     *     currentComparison: array<string, mixed>|null,
     *     freshComparisonUnavailable: bool,
     *     comparisonHistory: array<int, array<string, mixed>>,
     * }
     */
    public function execute(string $publicId, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();

        $offer = ProductOffer::query()
            ->with(['costs', 'replaces:id,public_id', 'replacement:id,public_id,replaces_product_offer_id'])
            ->where('public_id', $publicId)
            ->first();

        if ($offer === null || ! $offer->isPubliclyVisible($now)) {
            throw new NotFoundHttpException;
        }

        $publishedComparisons = $offer->benchmarkComparisons()
            ->whereNotNull('published_at')
            ->get();

        [$currentComparison, $history] = $this->comparisons($publishedComparisons, $now);

        return [
            'offer' => $this->offer($offer),
            'currentComparison' => $currentComparison,
            'freshComparisonUnavailable' => $currentComparison === null,
            'comparisonHistory' => $history,
        ];
    }

    /**
     * @param  Collection<int, BenchmarkComparison>  $publishedComparisons
     * @return array{0: array<string, mixed>|null, 1: array<int, array<string, mixed>>}
     */
    private function comparisons($publishedComparisons, CarbonInterface $now): array
    {
        $current = $publishedComparisons
            ->first(fn (BenchmarkComparison $comparison): bool => $comparison->superseded_at === null);

        if ($current !== null && ! $this->isFresh($current, $now)) {
            $current = null;
        }

        $history = $publishedComparisons
            ->filter(fn (BenchmarkComparison $comparison): bool => $comparison->superseded_at !== null)
            ->filter(fn (BenchmarkComparison $comparison): bool => $comparison->superseded_at->gte($now->subDays(30)->startOfSecond()))
            ->take(30)
            ->map(fn (BenchmarkComparison $comparison): array => $this->comparison($comparison))
            ->values()
            ->all();

        return [$current === null ? null : $this->comparison($current), $history];
    }

    private function isFresh(BenchmarkComparison $comparison, CarbonInterface $now): bool
    {
        return ! $comparison->observed_at->greaterThan($now)
            && ! $comparison->observed_at->lessThan($now->subHours(24)->startOfSecond());
    }

    /**
     * @return array<string, mixed>
     */
    private function comparison(BenchmarkComparison $comparison): array
    {
        return [
            'marketName' => $comparison->market_name,
            'sourceType' => $comparison->source_type,
            'sourceReference' => $comparison->source_reference,
            'observedAt' => $comparison->observed_at->toIso8601String(),
            'isDemo' => $comparison->is_demo,
            'benchmarkPrice' => $this->money($comparison->benchmark_price_minor),
            'saving' => $this->money($comparison->saving_minor),
            'savingPercentage' => OfferPriceCalculator::formatPercentage($comparison->saving_percentage_bps),
            'publishedAt' => $comparison->published_at->toIso8601String(),
            'supersededAt' => $comparison->superseded_at?->toIso8601String(),
            'isSuperseded' => $comparison->superseded_at !== null,
        ];
    }

    /**
     * @return array{
     *     publicId: string,
     *     crop: string,
     *     origin: string,
     *     availableQuantityKg: string,
     *     availabilityStartsAt: string,
     *     availabilityEndsAt: string,
     *     farmerPayment: array{minor: int, formatted: string},
     *     costs: array<int, array{code: string|null, name: string, amount: array{minor: int, formatted: string}}>,
     *     platformMargin: array{minor: int, formatted: string},
     *     finalPrice: array{minor: int, formatted: string},
     *     farmerSharePercentage: string,
     *     publishedAt: string,
     *     supersededAt: string|null,
     *     isSuperseded: bool,
     *     replacesPublicId: string|null,
     *     replacementPublicId: string|null,
     * }
     */
    private function offer(ProductOffer $offer): array
    {
        return [
            'publicId' => (string) $offer->public_id,
            'crop' => $offer->crop,
            'origin' => $offer->origin,
            'availableQuantityKg' => $offer->available_quantity_kg,
            'availabilityStartsAt' => $offer->availability_starts_at->toIso8601String(),
            'availabilityEndsAt' => $offer->availability_ends_at->toIso8601String(),
            'farmerPayment' => $this->money($offer->farmer_payment_minor),
            'costs' => $offer->costs
                ->map(fn (OfferCostComponent $cost): array => [
                    'code' => $cost->standard_code,
                    'name' => $cost->name,
                    'amount' => $this->money($cost->amount_minor),
                ])
                ->values()
                ->all(),
            'platformMargin' => $this->money($offer->platform_margin_minor),
            'finalPrice' => $this->money($offer->final_price_minor),
            'farmerSharePercentage' => OfferPriceCalculator::formatPercentage($offer->farmer_share_bps),
            'publishedAt' => $offer->published_at->toIso8601String(),
            'supersededAt' => $offer->superseded_at?->toIso8601String(),
            'isSuperseded' => $offer->superseded_at !== null,
            'replacesPublicId' => $offer->replaces?->public_id !== null ? (string) $offer->replaces->public_id : null,
            'replacementPublicId' => $offer->replacement?->public_id !== null ? (string) $offer->replacement->public_id : null,
        ];
    }

    /**
     * @return array{minor: int, formatted: string}
     */
    private function money(int $minor): array
    {
        return [
            'minor' => $minor,
            'formatted' => OfferPriceCalculator::formatMoney($minor),
        ];
    }
}
