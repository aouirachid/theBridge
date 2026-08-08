<?php

namespace Database\Factories;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferCostComponent>
 */
class OfferCostComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'product_offer_id' => ProductOffer::factory(),
            'standard_code' => null,
            'name' => $name,
            'normalized_name' => OfferCostComponent::normalizeName($name),
            'amount_minor' => fake()->numberBetween(0, 200),
            'position' => 100,
        ];
    }

    /**
     * Create one of the four required standard cost components.
     */
    public function standard(string $code, int $amountMinor = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'standard_code' => $code,
            'name' => OfferCostComponent::STANDARD_LABELS[$code],
            'normalized_name' => OfferCostComponent::normalizeName(OfferCostComponent::STANDARD_LABELS[$code]),
            'amount_minor' => $amountMinor,
            'position' => OfferCostComponent::STANDARD_POSITIONS[$code],
        ]);
    }
}
