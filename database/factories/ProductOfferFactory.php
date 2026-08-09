<?php

namespace Database\Factories;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductOffer>
 */
class ProductOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $farmerPaymentMinor = 280;
        $platformMarginMinor = 100;
        $finalPriceMinor = OfferPriceCalculator::finalPriceMinor($farmerPaymentMinor, 170, $platformMarginMinor);

        return [
            'public_id' => Str::uuid(),
            'created_by_user_id' => User::factory(),
            'replaces_product_offer_id' => null,
            'crop' => 'Tomatoes',
            'origin' => 'Souss-Massa',
            'available_quantity_kg' => '100.00',
            'availability_starts_at' => now()->addDay(),
            'availability_ends_at' => now()->addDays(7),
            'farmer_payment_minor' => $farmerPaymentMinor,
            'platform_margin_minor' => $platformMarginMinor,
            'final_price_minor' => $finalPriceMinor,
            'farmer_share_bps' => OfferPriceCalculator::farmerShareBps($farmerPaymentMinor, $finalPriceMinor),
            'published_at' => null,
            'superseded_at' => null,
            'withdrawn_at' => null,
        ];
    }

    /**
     * Attach the four required standard cost components and keep the derived
     * offer values consistent with their sum.
     *
     * @param  array<int, int>  $amounts  collection, quality control, hub handling and storage, delivery allocation
     */
    public function withStandardCosts(array $amounts = [30, 20, 30, 90]): static
    {
        return $this->afterCreating(function (ProductOffer $offer) use ($amounts): void {
            $codes = array_keys(OfferCostComponent::STANDARD_POSITIONS);

            foreach ($codes as $index => $code) {
                $offer->costs()->create([
                    'standard_code' => $code,
                    'name' => OfferCostComponent::STANDARD_LABELS[$code],
                    'normalized_name' => OfferCostComponent::normalizeName(OfferCostComponent::STANDARD_LABELS[$code]),
                    'amount_minor' => $amounts[$index] ?? 0,
                    'position' => OfferCostComponent::STANDARD_POSITIONS[$code],
                ]);
            }

            $operatingCostMinor = array_sum($amounts);
            $finalPriceMinor = OfferPriceCalculator::finalPriceMinor(
                $offer->farmer_payment_minor,
                $operatingCostMinor,
                $offer->platform_margin_minor,
            );

            $offer->forceFill([
                'final_price_minor' => $finalPriceMinor,
                'farmer_share_bps' => OfferPriceCalculator::farmerShareBps($offer->farmer_payment_minor, $finalPriceMinor),
            ])->save();
        });
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
            'superseded_at' => null,
            'withdrawn_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'superseded_at' => null,
            'withdrawn_at' => null,
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'superseded_at' => now(),
            'withdrawn_at' => null,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'superseded_at' => null,
            'withdrawn_at' => now(),
        ]);
    }
}
