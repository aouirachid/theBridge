<?php

namespace App\Actions\ProductOffers;

use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Collection;

/**
 * Load the operator create/edit review for one offer.
 */
final class ShowProductOfferDraftAction
{
    /**
     * @return array{
     *     offer: array{
     *         id: int,
     *         publicId: string,
     *         crop: string,
     *         origin: string,
     *         availableQuantity: string,
     *         availabilityStartsAt: string,
     *         availabilityEndsAt: string,
     *         farmerPayment: string,
     *         platformMargin: string,
     *         finalPrice: string,
     *         farmerSharePercentage: string,
     *         status: string,
     *     },
     *     costs: array{
     *         standard: array<int, array{code: string, label: string, amount: string, position: int}>,
     *         custom: array<int, array{id: int, name: string, normalized_name: string, amount: string}>,
     *     },
     *     preview: array{farmerPayment: string, operatingCost: string, platformMargin: string, finalPrice: string, farmerShare: string},
     *     benchmarks: array{newest30: array<int, array{
     *         id: int,
     *         marketName: string,
     *         benchmarkPrice: string,
     *         observedAt: string,
     *         sourceType: string,
     *         sourceReference: string,
     *         isDemo: bool,
     *         isRecorded: bool,
     *         isPublished: bool,
     *         isSuperseded: bool,
     *         publishedAt: string|null,
     *         supersededAt: string|null,
     *     }>},
     *     capabilities: array{canUpdate: bool, canPublish: bool, canReplace: bool, canWithdraw: bool, canRecordBenchmark: bool},
     * }
     */
    public function execute(User $operator, ProductOffer $offer): array
    {
        $costs = $offer->costs()->get();
        $standard = [];
        $custom = [];

        foreach ($costs as $cost) {
            if ($cost->standard_code === null) {
                $custom[] = [
                    'id' => $cost->id,
                    'name' => $cost->name,
                    'normalized_name' => $cost->normalized_name,
                    'amount' => OfferPriceCalculator::formatMinor($cost->amount_minor),
                ];

                continue;
            }

            $standard[] = [
                'code' => $cost->standard_code,
                'label' => OfferCostComponent::STANDARD_LABELS[$cost->standard_code] ?? $cost->name,
                'amount' => OfferPriceCalculator::formatMinor($cost->amount_minor),
                'position' => $cost->position,
            ];
        }

        return [
            'offer' => [
                'id' => $offer->id,
                'publicId' => $offer->public_id,
                'crop' => $offer->crop,
                'origin' => $offer->origin,
                'availableQuantity' => $offer->available_quantity_kg,
                'availabilityStartsAt' => $offer->availability_starts_at->toIso8601String(),
                'availabilityEndsAt' => $offer->availability_ends_at->toIso8601String(),
                'farmerPayment' => OfferPriceCalculator::formatMinor($offer->farmer_payment_minor),
                'platformMargin' => OfferPriceCalculator::formatMinor($offer->platform_margin_minor),
                'finalPrice' => OfferPriceCalculator::formatMinor($offer->final_price_minor),
                'farmerSharePercentage' => OfferPriceCalculator::formatPercentage($offer->farmer_share_bps),
                'status' => $offer->status(),
            ],
            'costs' => [
                'standard' => $standard,
                'custom' => $custom,
            ],
            'preview' => $this->preview($offer, $costs),
            'benchmarks' => [
                'newest30' => $offer->benchmarkComparisons()
                    ->limit(30)
                    ->get()
                    ->map(fn (BenchmarkComparison $comparison): array => [
                        'id' => $comparison->id,
                        'marketName' => $comparison->market_name,
                        'benchmarkPrice' => OfferPriceCalculator::formatMinor($comparison->benchmark_price_minor),
                        'observedAt' => $comparison->observed_at->toIso8601String(),
                        'sourceType' => $comparison->source_type,
                        'sourceReference' => $comparison->source_reference,
                        'isDemo' => $comparison->is_demo,
                        'isRecorded' => $comparison->isRecorded(),
                        'isPublished' => $comparison->isPublished(),
                        'isSuperseded' => $comparison->isSuperseded(),
                        'publishedAt' => $comparison->published_at?->toIso8601String(),
                        'supersededAt' => $comparison->superseded_at?->toIso8601String(),
                    ])
                    ->all(),
            ],
            'capabilities' => [
                'canUpdate' => $offer->isDraft(),
                'canPublish' => $offer->isDraft(),
                'canReplace' => $offer->isPublished() && $offer->superseded_at === null && ! $offer->replacement()->exists(),
                'canWithdraw' => $offer->isPublished() && $offer->superseded_at === null,
                'canRecordBenchmark' => ! $offer->isWithdrawn(),
            ],
        ];
    }

    /**
     * @param  Collection<int, OfferCostComponent>  $costs
     * @return array{farmerPayment: string, operatingCost: string, platformMargin: string, finalPrice: string, farmerShare: string}
     */
    private function preview(ProductOffer $offer, $costs): array
    {
        $operatingMinor = $costs->sum(fn (OfferCostComponent $cost): int => $cost->amount_minor);
        $finalMinor = OfferPriceCalculator::finalPriceMinor(
            $offer->farmer_payment_minor,
            $operatingMinor,
            $offer->platform_margin_minor,
        );

        return [
            'farmerPayment' => OfferPriceCalculator::formatMinor($offer->farmer_payment_minor),
            'operatingCost' => OfferPriceCalculator::formatMinor($operatingMinor),
            'platformMargin' => OfferPriceCalculator::formatMinor($offer->platform_margin_minor),
            'finalPrice' => OfferPriceCalculator::formatMinor($finalMinor),
            'farmerShare' => OfferPriceCalculator::formatPercentage(
                OfferPriceCalculator::farmerShareBps($offer->farmer_payment_minor, $finalMinor),
            ),
        ];
    }
}
