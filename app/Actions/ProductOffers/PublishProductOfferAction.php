<?php

namespace App\Actions\ProductOffers;

use App\Models\BenchmarkComparison;
use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publish one offer draft together with its initial benchmark comparison.
 *
 * The transition is transactional and one-way: the offer and comparison rows
 * are locked, all derived snapshot values are computed from the recorded
 * inputs in the same transaction, and any repeat attempt is rejected without
 * creating a second snapshot.
 */
final class PublishProductOfferAction
{
    public function execute(User $operator, ProductOffer $offer, BenchmarkComparison $comparison): ProductOffer
    {
        return DB::transaction(function () use ($operator, $offer, $comparison): ProductOffer {
            $lockedOffer = ProductOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $lockedComparison = BenchmarkComparison::query()->lockForUpdate()->findOrFail($comparison->id);

            if (! $lockedOffer->isDraft()) {
                throw new RuntimeException('Only a draft offer can be published.');
            }

            if ($lockedComparison->product_offer_id !== $lockedOffer->id) {
                throw new RuntimeException('The selected benchmark does not belong to this offer.');
            }

            if (! $lockedComparison->isRecorded()) {
                throw new RuntimeException('Only a recorded benchmark can be published.');
            }

            $now = now();

            if ($lockedComparison->observed_at->greaterThan($now)
                || $lockedComparison->observed_at->lessThan($now->subHours(24)->startOfSecond())) {
                throw new RuntimeException('The benchmark must have been observed within the last 24 hours.');
            }

            $requiredCodes = array_keys(OfferCostComponent::STANDARD_POSITIONS);
            $costs = $lockedOffer->costs()->get();
            $missing = array_diff($requiredCodes, $costs->pluck('standard_code')->all());

            if ($missing !== []) {
                throw new RuntimeException('All four standard cost components are required for publication.');
            }

            if (! $lockedOffer->deliverySlots()->where('starts_at', '>', $now)->exists()) {
                throw new RuntimeException('At least one future delivery slot is required for publication.');
            }

            $operatingMinor = $costs->sum(fn (OfferCostComponent $cost): int => $cost->amount_minor);
            $finalMinor = OfferPriceCalculator::finalPriceMinor(
                $lockedOffer->farmer_payment_minor,
                $operatingMinor,
                $lockedOffer->platform_margin_minor,
            );
            $farmerShareBps = OfferPriceCalculator::farmerShareBps($lockedOffer->farmer_payment_minor, $finalMinor);
            $savingMinor = OfferPriceCalculator::savingMinor($lockedComparison->benchmark_price_minor, $finalMinor);
            $savingPercentageBps = OfferPriceCalculator::savingPercentageBps($savingMinor, $lockedComparison->benchmark_price_minor);

            $lockedOffer->forceFill([
                'final_price_minor' => $finalMinor,
                'farmer_share_bps' => $farmerShareBps,
                'published_at' => $now,
            ])->save();

            $lockedComparison->forceFill([
                'published_by_user_id' => $operator->id,
                'saving_minor' => $savingMinor,
                'saving_percentage_bps' => $savingPercentageBps,
                'published_at' => $now,
            ])->save();

            if ($lockedOffer->replaces_product_offer_id !== null) {
                $predecessor = ProductOffer::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedOffer->replaces_product_offer_id);

                if ($predecessor->superseded_at === null) {
                    $predecessor->forceFill(['superseded_at' => $now])->save();
                }
            }

            return $lockedOffer;
        });
    }
}
