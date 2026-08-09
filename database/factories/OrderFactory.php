<?php

namespace Database\Factories;

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use App\Support\Pricing\OrderTotalCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_hash' => fn (): string => hash('sha256', Str::uuid()->toString()),
            'product_offer_id' => ProductOffer::factory()->published()->withStandardCosts(),
            'offer_delivery_slot_id' => fn (array $attributes) => OfferDeliverySlot::factory()->create([
                'product_offer_id' => $attributes['product_offer_id'],
            ]),
            'channel' => OrderChannel::B2c,
            'status' => OrderStatus::Confirmed,
            'quantity_hundredths' => 500,
            'currency' => 'MAD',
            'unit_price_minor' => 550,
            'total_minor' => 2750,
            'offer_public_id_snapshot' => fn (array $attributes) => ProductOffer::query()->findOrFail($attributes['product_offer_id'])->public_id,
            'crop_snapshot' => fn (array $attributes) => ProductOffer::query()->findOrFail($attributes['product_offer_id'])->crop,
            'service_date' => fn (array $attributes) => OfferDeliverySlot::query()->findOrFail($attributes['offer_delivery_slot_id'])->starts_at->copy()->setTimezone('Africa/Casablanca')->toDateString(),
            'slot_starts_at' => fn (array $attributes) => OfferDeliverySlot::query()->findOrFail($attributes['offer_delivery_slot_id'])->starts_at,
            'slot_ends_at' => fn (array $attributes) => OfferDeliverySlot::query()->findOrFail($attributes['offer_delivery_slot_id'])->ends_at,
            'delivery_zone' => DeliveryZone::CasablancaCentre,
            'customer_name' => fake()->name(),
            'business_name' => null,
            'phone' => fake()->phoneNumber(),
            'email' => null,
            'delivery_address' => fake()->address(),
            'delivery_note' => null,
            'confirmed_at' => now(),
        ];
    }

    /**
     * A B2C consumer order with an optional delivery address.
     *
     * @return $this
     */
    public function b2c(): static
    {
        return $this->state([
            'channel' => OrderChannel::B2c,
            'business_name' => null,
            'delivery_address' => fake()->address(),
            'delivery_note' => null,
        ]);
    }

    /**
     * A B2B buyer order with a business name and no address or note.
     *
     * @return $this
     */
    public function b2b(): static
    {
        return $this->state([
            'channel' => OrderChannel::B2b,
            'business_name' => fake()->company(),
            'delivery_address' => null,
            'delivery_note' => null,
        ]);
    }

    /**
     * A cancelled order that still keeps its commercial snapshot.
     *
     * @return $this
     */
    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
        ]);
    }

    /**
     * Build the order against an existing offer and one of its slots.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function forOffer(ProductOffer $offer, OfferDeliverySlot $slot, array $attributes = []): static
    {
        return $this->state(array_merge([
            'product_offer_id' => $offer->id,
            'offer_delivery_slot_id' => $slot->id,
            'offer_public_id_snapshot' => $offer->public_id,
            'crop_snapshot' => $offer->crop,
            'unit_price_minor' => $offer->final_price_minor,
            'total_minor' => OrderTotalCalculator::totalMinor(
                $offer->final_price_minor,
                $attributes['quantity_hundredths'] ?? 500,
            ),
            'service_date' => $slot->starts_at->copy()->setTimezone('Africa/Casablanca')->toDateString(),
            'slot_starts_at' => $slot->starts_at,
            'slot_ends_at' => $slot->ends_at,
        ], $attributes));
    }
}
