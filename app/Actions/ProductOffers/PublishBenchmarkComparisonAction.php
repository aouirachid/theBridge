<?php

namespace App\Actions\ProductOffers;

use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Publish one explicitly reviewed benchmark observation for a published offer.
 *
 * The offer, candidate comparison, and current comparison rows are locked.
 * Only recorded observations observed within the last twenty-four hours can be
 * published. Publishing stores the exact signed savings, links the candidate
 * to the previous current comparison, and supersedes that comparison once
 * without ever touching the offer's economics. Repeating publication of an
 * already published row is idempotent.
 */
final class PublishBenchmarkComparisonAction
{
    public function execute(User $operator, ProductOffer $offer, BenchmarkComparison $candidate): BenchmarkComparison
    {
        return DB::transaction(function () use ($operator, $offer, $candidate): BenchmarkComparison {
            $lockedOffer = ProductOffer::query()->lockForUpdate()->findOrFail($offer->id);
            $lockedCandidate = BenchmarkComparison::query()->lockForUpdate()->findOrFail($candidate->id);

            if ($lockedCandidate->product_offer_id !== $lockedOffer->id) {
                throw new RuntimeException('The comparison does not belong to this offer.');
            }

            if ($lockedCandidate->published_at !== null) {
                return $lockedCandidate;
            }

            if ($lockedOffer->isDraft() || $lockedOffer->isWithdrawn() || $lockedOffer->superseded_at !== null) {
                throw new RuntimeException('Only a currently published offer can receive a benchmark refresh.');
            }

            $now = now();

            if ($lockedCandidate->observed_at->greaterThan($now)
                || $lockedCandidate->observed_at->lessThan($now->subHours(24)->startOfSecond())) {
                throw new RuntimeException('The benchmark must have been observed within the last 24 hours.');
            }

            $current = BenchmarkComparison::query()
                ->where('product_offer_id', $lockedOffer->id)
                ->whereNull('superseded_at')
                ->whereNotNull('published_at')
                ->lockForUpdate()
                ->first();

            $savingMinor = OfferPriceCalculator::savingMinor($lockedCandidate->benchmark_price_minor, $lockedOffer->final_price_minor);
            $savingPercentageBps = OfferPriceCalculator::savingPercentageBps($savingMinor, $lockedCandidate->benchmark_price_minor);

            $lockedCandidate->forceFill([
                'published_by_user_id' => $operator->id,
                'supersedes_comparison_id' => $current?->id,
                'saving_minor' => $savingMinor,
                'saving_percentage_bps' => $savingPercentageBps,
                'published_at' => $now,
            ])->save();

            if ($current !== null) {
                $current->forceFill(['superseded_at' => $now])->save();
            }

            return $lockedCandidate;
        });
    }
}
