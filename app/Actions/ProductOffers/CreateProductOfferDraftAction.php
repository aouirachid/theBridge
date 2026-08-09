<?php

namespace App\Actions\ProductOffers;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Create one offer draft from validated operator input.
 *
 * The Action owns the normalized custom-cost uniqueness rules that depend on
 * other rows in the same submission; the dedicated Form Request owns format
 * validation, so a submission failing either layer persists nothing.
 */
final class CreateProductOfferDraftAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(User $operator, array $input): ProductOffer
    {
        $this->validateCustomCosts($input['custom_costs'] ?? []);

        $windowStart = CarbonImmutable::parse((string) $input['availability_starts_at']);
        $windowEnd = CarbonImmutable::parse((string) $input['availability_ends_at']);
        $this->validateDeliverySlots($input['delivery_slots'] ?? [], $windowStart, $windowEnd);

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

        return DB::transaction(function () use ($operator, $input, $standardCodes, $farmerMinor, $marginMinor, $finalMinor): ProductOffer {
            $offer = ProductOffer::create([
                'created_by_user_id' => $operator->id,
                'crop' => $input['crop'],
                'origin' => $input['origin'],
                'available_quantity_kg' => $input['available_quantity_kg'],
                'availability_starts_at' => $input['availability_starts_at'],
                'availability_ends_at' => $input['availability_ends_at'],
                'farmer_payment_minor' => $farmerMinor,
                'platform_margin_minor' => $marginMinor,
                'final_price_minor' => $finalMinor,
                'farmer_share_bps' => OfferPriceCalculator::farmerShareBps($farmerMinor, $finalMinor),
            ]);

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

            foreach ($input['delivery_slots'] as $slot) {
                $offer->deliverySlots()->create([
                    'starts_at' => CarbonImmutable::parse((string) $slot['starts_at']),
                    'ends_at' => CarbonImmutable::parse((string) $slot['ends_at']),
                ]);
            }

            return $offer;
        });
    }

    /**
     * @param  list<array{name?: mixed, amount_per_kg?: mixed}>  $customCosts
     */
    public function validateCustomCosts(array $customCosts): void
    {
        $validator = Validator::make(
            ['custom_costs' => $customCosts],
            [
                'custom_costs' => ['array', 'max:10'],
                'custom_costs.*.name' => ['required', 'string', 'max:120'],
                'custom_costs.*.amount_per_kg' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
            ],
        );

        $validator->after(function ($validator) use ($customCosts): void {
            $seen = [];

            foreach ($customCosts as $index => $custom) {
                if (! is_string($custom['name'] ?? null)) {
                    continue;
                }

                $normalized = OfferCostComponent::normalizeName($custom['name']);

                if (OfferCostComponent::isStandardName($normalized)) {
                    $validator->errors()->add("custom_costs.$index.name", 'This name matches a standard cost category.');
                }

                if (in_array($normalized, $seen, true)) {
                    $validator->errors()->add("custom_costs.$index.name", 'Custom cost names must be unique after normalization.');
                }

                $seen[] = $normalized;
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate the bounded draft delivery slot collection.
     *
     * @param  list<array{starts_at?: mixed, ends_at?: mixed}>  $slots
     */
    public function validateDeliverySlots(
        array $slots,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): void {
        if (count($slots) < 1 || count($slots) > 14) {
            throw ValidationException::withMessages([
                'delivery_slots' => 'An offer requires between 1 and 14 delivery slots.',
            ]);
        }

        $pairs = [];

        foreach ($slots as $index => $slot) {
            $startsAt = CarbonImmutable::parse((string) $slot['starts_at']);
            $endsAt = CarbonImmutable::parse((string) $slot['ends_at']);

            if (! $endsAt->greaterThan($startsAt)) {
                throw ValidationException::withMessages([
                    "delivery_slots.$index.ends_at" => 'The slot must end after it starts.',
                ]);
            }

            if ($startsAt->lessThan($windowStart) || $endsAt->greaterThan($windowEnd)) {
                throw ValidationException::withMessages([
                    "delivery_slots.$index.starts_at" => 'Delivery slots must fit inside the offer availability window.',
                ]);
            }

            $pair = $startsAt->format('Y-m-d H:i:s').'|'.$endsAt->format('Y-m-d H:i:s');

            if (in_array($pair, $pairs, true)) {
                throw ValidationException::withMessages([
                    "delivery_slots.$index.starts_at" => 'Duplicate delivery slots are not allowed.',
                ]);
            }

            $pairs[] = $pair;
        }
    }
}
