<?php

namespace App\Actions\ProductOffers;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Replace the inputs and complete cost collection of one draft.
 *
 * Only an unpublished, non-withdrawn draft can be edited. The published
 * snapshot and withdrawn offers are immutable.
 */
final class UpdateProductOfferDraftAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(User $operator, ProductOffer $offer, array $input): ProductOffer
    {
        if (! $offer->isDraft()) {
            throw new RuntimeException('Only a draft offer can be updated.');
        }

        $this->validateStandardCodes($input['standard_costs'] ?? []);
        (new CreateProductOfferDraftAction)->validateCustomCosts($input['custom_costs'] ?? []);

        $standardCodes = array_keys(OfferCostComponent::STANDARD_POSITIONS);
        $operatingMinor = collect($standardCodes)
            ->map(fn (string $code): int => OfferPriceCalculator::parseMinor((string) $input['standard_costs'][$code]))
            ->sum();

        foreach ($input['custom_costs'] ?? [] as $custom) {
            $operatingMinor += OfferPriceCalculator::parseMinor((string) $custom['amount_per_kg']);
        }

        $farmerMinor = OfferPriceCalculator::parseMinor((string) $input['farmer_payment_per_kg']);
        $marginMinor = OfferPriceCalculator::parseMinor((string) $input['platform_margin_per_kg']);
        $finalMinor = OfferPriceCalculator::finalPriceMinor($farmerMinor, $operatingMinor, $marginMinor);

        return DB::transaction(function () use ($offer, $input, $standardCodes, $farmerMinor, $marginMinor, $finalMinor): ProductOffer {
            $offer->forceFill([
                'crop' => $input['crop'],
                'origin' => $input['origin'],
                'available_quantity_kg' => $input['available_quantity_kg'],
                'availability_starts_at' => $input['availability_starts_at'],
                'availability_ends_at' => $input['availability_ends_at'],
                'farmer_payment_minor' => $farmerMinor,
                'platform_margin_minor' => $marginMinor,
                'final_price_minor' => $finalMinor,
                'farmer_share_bps' => OfferPriceCalculator::farmerShareBps($farmerMinor, $finalMinor),
            ])->save();

            $offer->costs()->delete();

            foreach ($standardCodes as $code) {
                $offer->costs()->create([
                    'standard_code' => $code,
                    'name' => OfferCostComponent::STANDARD_LABELS[$code],
                    'normalized_name' => OfferCostComponent::normalizeName(OfferCostComponent::STANDARD_LABELS[$code]),
                    'amount_minor' => OfferPriceCalculator::parseMinor((string) $input['standard_costs'][$code]),
                    'position' => OfferCostComponent::STANDARD_POSITIONS[$code],
                ]);
            }

            foreach ($input['custom_costs'] ?? [] as $index => $custom) {
                $name = Str::of((string) $custom['name'])->squish()->toString();

                $offer->costs()->create([
                    'standard_code' => null,
                    'name' => $name,
                    'normalized_name' => OfferCostComponent::normalizeName($name),
                    'amount_minor' => OfferPriceCalculator::parseMinor((string) $custom['amount_per_kg']),
                    'position' => 100 + $index,
                ]);
            }

            return $offer;
        });
    }

    /**
     * @param  array<string, mixed>  $standardCosts
     */
    protected function validateStandardCodes(array $standardCosts): void
    {
        $allowed = array_keys(OfferCostComponent::STANDARD_POSITIONS);
        $unknown = array_diff(array_keys($standardCosts), $allowed);

        if ($unknown !== []) {
            $validator = Validator::make([], [
                'standard_costs' => ['array'],
            ]);

            foreach ($unknown as $code) {
                $validator->errors()->add("standard_costs.$code", 'Unknown standard cost code.');
            }

            throw new ValidationException($validator);
        }
    }
}
