<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusTransition>
 */
class OrderStatusTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'actor_user_id' => null,
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
        ];
    }

    /**
     * The initial pending transition that opens an order lifecycle.
     *
     * @return $this
     */
    public function initial(): static
    {
        return $this->state([
            'from_status' => null,
            'to_status' => OrderStatus::Pending,
        ]);
    }

    /**
     * The confirmation transition from pending to confirmed.
     *
     * @return $this
     */
    public function toConfirmed(): static
    {
        return $this->state([
            'actor_user_id' => null,
            'from_status' => OrderStatus::Pending,
            'to_status' => OrderStatus::Confirmed,
        ]);
    }

    /**
     * The operator cancellation transition from confirmed to cancelled.
     *
     * @return $this
     */
    public function toCancelled(int $actorUserId): static
    {
        return $this->state([
            'actor_user_id' => $actorUserId,
            'from_status' => OrderStatus::Confirmed,
            'to_status' => OrderStatus::Cancelled,
        ]);
    }
}
