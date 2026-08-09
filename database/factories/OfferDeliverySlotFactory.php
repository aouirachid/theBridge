<?php

namespace Database\Factories;

use App\Models\OfferDeliverySlot;
use App\Models\ProductOffer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OfferDeliverySlot>
 */
class OfferDeliverySlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->addHours(2)->startOfMinute();

        return [
            'public_id' => Str::uuid(),
            'product_offer_id' => ProductOffer::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ];
    }
}
