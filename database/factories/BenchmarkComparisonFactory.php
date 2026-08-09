<?php

namespace Database\Factories;

use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BenchmarkComparison>
 */
class BenchmarkComparisonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_offer_id' => ProductOffer::factory(),
            'recorded_by_user_id' => User::factory(),
            'published_by_user_id' => null,
            'supersedes_comparison_id' => null,
            'benchmark_price_minor' => fake()->numberBetween(200, 1200),
            'market_name' => fake()->city().' traditional market',
            'source_type' => 'field_observation',
            'source_reference' => fake()->sentence(4),
            'observed_at' => now(),
            'is_demo' => false,
            'saving_minor' => null,
            'saving_percentage_bps' => null,
            'published_at' => null,
            'superseded_at' => null,
        ];
    }
}
