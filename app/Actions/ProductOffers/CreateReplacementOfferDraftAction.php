<?php

namespace App\Actions\ProductOffers;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Clone one published offer and its complete cost collection into a single
 * linked, editable draft.
 *
 * Only a currently published, non-withdrawn, non-superseded offer can be
 * replaced, and only once. The clone never copies publication or supersession
 * state: the new record is always a plain draft whose derived values are
 * recomputed from the cloned inputs and costs in the same transaction.
 */
final class CreateReplacementOfferDraftAction
{
    public function execute(User $operator, ProductOffer $offer): ProductOffer
    {
        return DB::transaction(function () use ($operator, $offer): ProductOffer {
            $locked = ProductOffer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($locked->isDraft() || $locked->isWithdrawn() || $locked->superseded_at !== null) {
                throw new RuntimeException('Only a currently published offer can be replaced.');
            }

            if ($locked->replacement()->exists()) {
                throw new RuntimeException('This offer already has a replacement.');
            }

            $costs = $locked->costs()->get();
            $operatingMinor = $costs->sum(fn (OfferCostComponent $cost): int => $cost->amount_minor);
            $finalMinor = OfferPriceCalculator::finalPriceMinor(
                $locked->farmer_payment_minor,
                $operatingMinor,
                $locked->platform_margin_minor,
            );

            $draft = ProductOffer::create([
                'created_by_user_id' => $operator->id,
                'replaces_product_offer_id' => $locked->id,
                'crop' => $locked->crop,
                'origin' => $locked->origin,
                'available_quantity_kg' => $locked->available_quantity_kg,
                'availability_starts_at' => $locked->availability_starts_at,
                'availability_ends_at' => $locked->availability_ends_at,
                'farmer_payment_minor' => $locked->farmer_payment_minor,
                'platform_margin_minor' => $locked->platform_margin_minor,
                'final_price_minor' => $finalMinor,
                'farmer_share_bps' => OfferPriceCalculator::farmerShareBps($locked->farmer_payment_minor, $finalMinor),
            ]);

            foreach ($costs as $cost) {
                $draft->costs()->create([
                    'standard_code' => $cost->standard_code,
                    'name' => $cost->name,
                    'normalized_name' => $cost->normalized_name,
                    'amount_minor' => $cost->amount_minor,
                    'position' => $cost->position,
                ]);
            }

            foreach ($locked->deliverySlots()->get() as $slot) {
                $draft->deliverySlots()->create([
                    'starts_at' => $slot->starts_at,
                    'ends_at' => $slot->ends_at,
                ]);
            }

            return $draft;
        });
    }
}
